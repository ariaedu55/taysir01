<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Services\Access;

/** Rendez-vous (table appointments). */
final class Appointment extends Model
{
    protected static string $table = 'appointments';

    public const MOTIVES = [
        'orientation-filieres' => 'Orientation : choisir une filière',
        'orientation-metiers' => 'Orientation : choisir un métier',
        'admission' => 'Admission dans un établissement',
        'visa' => 'Visa',
        'logement' => 'Logement',
        'prise-en-charge' => 'Prise en charge',
        'voyage' => 'Billet d’avion ou hôtel',
        'renseignement' => 'Demande de renseignement',
    ];

    public const MODES = [
        'agence-dakar' => 'À l’agence de Dakar',
        'agence-ziguinchor' => 'À l’agence de Ziguinchor',
        'telephone' => 'Par téléphone',
        'whatsapp' => 'Par WhatsApp',
        'visio' => 'En visio',
    ];

    public const SLOTS = ['matin' => 'Matin (9 h – 13 h)', 'apres-midi' => 'Après-midi (14 h – 18 h)'];

    public const STATUSES = [
        'demande' => ['Demandé', 'blue'],
        'confirme' => ['Confirmé', 'green'],
        'honore' => ['Honoré', 'green'],
        'absent' => ['Absent', 'grey'],
        'reporte' => ['Reporté', 'grey'],
        'annule' => ['Annulé', 'grey'],
    ];

    public static function directEnabled(): bool
    {
        return env('AGENDA_RESERVATION_DIRECTE', false) === '1';
    }

    private const DIRECT_MOTIVES = ['orientation-filieres', 'orientation-metiers', 'renseignement'];

    public static function durations(): array
    {
        return static::query('SELECT motive, minutes FROM appointment_durations')->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    public static function setDuration(string $motive, int $minutes): void
    {
        static::query('UPDATE appointment_durations SET minutes = ? WHERE motive = ?', [$minutes, $motive]);
    }

    /**
     * Fuseau d'une agence (agencies.timezone, 012) ; Dakar par défaut. Condition B2 de la décision 2B : les plages
     * sont en heure locale de l'agence, les rendez-vous en UTC, l'affichage dans le fuseau de l'agence.
     */
    public static function zone(?int $agencyId): \DateTimeZone
    {
        static $zones = [];
        $key = (int) $agencyId;
        if (!isset($zones[$key])) {
            $name = $agencyId ? static::query('SELECT timezone FROM agencies WHERE id = ?', [$agencyId])->fetchColumn() : false;
            $zones[$key] = new \DateTimeZone(is_string($name) && in_array($name, \DateTimeZone::listIdentifiers(), true) ? $name : 'Africa/Dakar');
        }

        return $zones[$key];
    }

    /** Identifiant d'une agence par son code (dakar, ziguinchor…), ou null. */
    public static function agencyId(string $code): ?int
    {
        $id = static::query('SELECT id FROM agencies WHERE code = ?', [$code])->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    /** Ville de l'agence, pour les libellés « heure de … ». */
    public static function city(?int $agencyId): string
    {
        $city = $agencyId ? static::query('SELECT city FROM agencies WHERE id = ?', [$agencyId])->fetchColumn() : false;

        return is_string($city) && $city !== '' ? $city : 'Dakar';
    }

    /** Date et heure UTC (Y-m-d H:i:s) dans le fuseau donné. */
    public static function local(?string $utc, \DateTimeZone $zone, string $format = 'Y-m-d H:i'): string
    {
        return $utc ? (new \DateTimeImmutable($utc, new \DateTimeZone('UTC')))->setTimezone($zone)->format($format) : '';
    }

    public static function slots(string $day, string $motive, string $mode, ?int $member = null): array
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $day, new \DateTimeZone('UTC'));
        $today = new \DateTimeImmutable('today', new \DateTimeZone('UTC'));
        if (!$date || $date->format('Y-m-d') !== $day || $date <= $today || $date > $today->modify('+6 months')
            || !in_array($motive, self::DIRECT_MOTIVES, true) || !in_array($mode, ['agence-dakar', 'agence-ziguinchor', 'visio'], true)) {
            return [];
        }
        $agency = $mode === 'agence-ziguinchor' ? 'ziguinchor' : 'dakar';
        // Journée locale de l'agence, en UTC : bornes des occupations et des absences
        $zone = self::zone((int) static::query('SELECT id FROM agencies WHERE code = ?', [$agency])->fetchColumn() ?: null);
        $utc = new \DateTimeZone('UTC');
        $dayStart = (new \DateTimeImmutable($day . ' 00:00:00', $zone))->setTimezone($utc);
        $dayEnd = (new \DateTimeImmutable($day . ' 00:00:00', $zone))->modify('+1 day')->setTimezone($utc);
        $rules = static::query(
            "SELECT r.* FROM availability_rules r JOIN users u ON u.id = r.user_id
             JOIN agencies g ON g.id = r.agency_id AND g.id = u.agency_id
             WHERE g.code = ? AND g.active = 1 AND u.active = 1 AND u.role IN ('conseiller', 'orientation')
             AND r.weekday = ? AND r.mode = ?" . ($member === null ? '' : ' AND u.id = ?') . ' ORDER BY u.id, r.starts_at',
            [$agency, $date->format('N'), $mode === 'visio' ? 'visio' : 'agence', ...($member === null ? [] : [$member])]
        )->fetchAll();
        $busy = static::query(
            "SELECT assigned_to AS user_id, starts_at_utc, ends_at_utc FROM appointments
             WHERE starts_at_utc < ? AND ends_at_utc > ? AND status IN ('demande', 'confirme')
             UNION ALL SELECT user_id, starts_at_utc, ends_at_utc FROM availability_exceptions
             WHERE starts_at_utc < ? AND ends_at_utc > ?",
            [$dayEnd->format('Y-m-d H:i:s'), $dayStart->format('Y-m-d H:i:s'), $dayEnd->format('Y-m-d H:i:s'), $dayStart->format('Y-m-d H:i:s')]
        )->fetchAll();
        $byMember = [];
        foreach ($busy as $entry) {
            $byMember[$entry['user_id']][] = $entry;
        }
        $duration = (int) static::query('SELECT minutes FROM appointment_durations WHERE motive = ?', [$motive])->fetchColumn();
        if ($duration < 15 || $duration > 180) {
            return [];
        }
        $minimum = new \DateTimeImmutable('+24 hours', new \DateTimeZone('UTC'));
        $slots = [];
        foreach ($rules as $rule) {
            // Plage en heure locale de l'agence de la règle, convertie en UTC (changement d'heure compris)
            $ruleZone = self::zone((int) $rule['agency_id']);
            $start = (new \DateTimeImmutable($day . ' ' . $rule['starts_at'], $ruleZone))->setTimezone($utc);
            $limit = ($rule['ends_at'] === '24:00:00' ? (new \DateTimeImmutable($day . ' 00:00:00', $ruleZone))->modify('+1 day') : new \DateTimeImmutable($day . ' ' . $rule['ends_at'], $ruleZone))->setTimezone($utc);
            for (; ($end = $start->modify("+$duration minutes")) <= $limit; $start = $start->modify('+15 minutes')) {
                if ($start < $minimum) {
                    continue;
                }
                $from = $start->format('Y-m-d H:i:s');
                $to = $end->format('Y-m-d H:i:s');
                foreach ($byMember[$rule['user_id']] ?? [] as $entry) {
                    if ($entry['starts_at_utc'] < $to && $entry['ends_at_utc'] > $from) {
                        continue 2;
                    }
                }
                $slots[$start->setTimezone($ruleZone)->format('H:i')][(int) $rule['user_id']] = [$from, $to, (int) $rule['agency_id']];
            }
        }
        ksort($slots);
        return $slots;
    }

    public static function create(array $data, ?int $userId, ?string $time = null): array
    {
        // C5 : compte => sa personne ; sans compte => jamais rattaché à une personne qui a un compte (Person::pourAnonyme)
        $user = $userId !== null ? User::find($userId) : null;
        $person = $user !== null ? Person::pourCompte($user) : null;
        if ($time === null) {
            return self::insertAppointment($data, $user, $person ?? Person::pourAnonyme($data));
        }
        $db = static::db();
        foreach (array_keys(self::slots($data['preferred_date'], $data['motive'], $data['mode'])[$time] ?? []) as $member) {
            $db->beginTransaction();
            try {
                static::query('SELECT id FROM users WHERE id = ? FOR UPDATE', [$member])->fetchColumn();
                $slot = self::slots($data['preferred_date'], $data['motive'], $data['mode'], $member)[$time][$member] ?? null;
                if ($slot === null) {
                    $db->rollBack();
                    continue;
                }
                [$start, $end, $agency] = $slot;
                $appointment = self::insertAppointment($data, $user, $person ?? Person::pourAnonyme($data));
                static::query("UPDATE appointments SET status = 'confirme', assigned_to = ?, agency_id = ?,
                    scheduled_at = ?, starts_at_utc = ?, ends_at_utc = ? WHERE id = ?", [$member, $agency, $start, $start, $end, $appointment['id']]);
                static::query("INSERT INTO appointment_events (appointment_id, user_id, type, status, created_at)
                    VALUES (?, ?, 'creation', 'confirme', UTC_TIMESTAMP())", [$appointment['id'], $userId]);
                $db->commit();
                return static::find((int) $appointment['id']);
            } catch (\Throwable $exception) {
                $db->rollBack();
                throw $exception;
            }
        }
        throw new \DomainException('Ce créneau vient d’être pris. Choisissez-en un autre ou envoyez une demande.');
    }

    private static function insertAppointment(array $data, ?array $user, array $person): array
    {
        static::query(
            'INSERT INTO appointments (reference, user_id, person_id, motive, mode, preferred_date, preferred_slot, first_name, last_name, email, phone, message)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            ['TMP-' . bin2hex(random_bytes(6)), $user['id'] ?? null, $person['id'], $data['motive'], $data['mode'], $data['preferred_date'], $data['preferred_slot'],
                $data['first_name'], $data['last_name'], $data['email'], $data['phone'], $data['message']]
        );
        $id = (int) static::db()->lastInsertId();
        $reference = sprintf('RDV-%s-%06d', date('Y'), $id);
        static::query('UPDATE appointments SET reference = ? WHERE id = ?', [$reference, $id]);

        return static::find($id);
    }

    /** Proposition d'équipe sous la transaction du prospect. La confirmation reste dans l'agenda. */
    public static function proposeFor(array $person, int $agency, int $actor, array $preferred): array
    {
        $appointment = self::insertAppointment($preferred + [
            'first_name' => $person['first_name'], 'last_name' => $person['last_name'], 'email' => $person['email'] ?? '',
            'phone' => $person['phone'] ?? '', 'motive' => 'renseignement', 'message' => 'Rendez-vous proposé par la prospection.',
        ], null, $person);
        static::query('UPDATE appointments SET agency_id = ? WHERE id = ?', [$agency, $appointment['id']]);
        static::query("INSERT INTO appointment_events (appointment_id, user_id, type, status) VALUES (?, ?, 'creation', 'demande')", [$appointment['id'], $actor]);
        return $appointment;
    }

    public static function findByReference(string $reference): ?array
    {
        $row = static::query('SELECT * FROM appointments WHERE reference = ? LIMIT 1', [$reference])->fetch();

        return $row === false ? null : $row;
    }

    public static function forUser(int $userId): array
    {
        return static::query('SELECT * FROM appointments WHERE ' . ServiceRequest::CLIENT_SCOPE . ' ORDER BY preferred_date DESC, id DESC', [$userId, $userId])->fetchAll();
    }

    public static function cancelForUser(string $reference, int $userId): bool
    {
        $db = static::db();
        $db->beginTransaction();
        try {
            $changed = static::query("UPDATE appointments SET status = 'annule'
            WHERE reference = ? AND " . ServiceRequest::CLIENT_SCOPE . "
            AND ((status = 'demande' AND preferred_date >= UTC_DATE())
                OR (status = 'confirme' AND starts_at_utc > UTC_TIMESTAMP()))",
                [$reference, $userId, $userId])->rowCount() === 1;
            if ($changed) {
                static::query("INSERT INTO appointment_events (appointment_id, user_id, type, status)
                    SELECT id, ?, 'statut', 'annule' FROM appointments WHERE reference = ?", [$userId, $reference]);
            }
            $db->commit();
            return $changed;
        } catch (\Throwable $exception) {
            $db->rollBack();
            throw $exception;
        }
    }

    public static function belongsToUser(string $reference, int $userId): bool
    {
        return (bool) static::query('SELECT 1 FROM appointments WHERE reference = ? AND ' . ServiceRequest::CLIENT_SCOPE,
            [$reference, $userId, $userId])->fetchColumn();
    }

    public static function guestCancellationLink(array $appointment): ?string
    {
        if ($appointment['user_id'] !== null) {
            return null;
        }
        $token = bin2hex(random_bytes(32));
        static::query('INSERT INTO appointment_cancellation_tokens (appointment_id, token_hash, expires_at)
            VALUES (?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 7 MONTH))',
            [$appointment['id'], hash('sha256', $token)]);
        return absolute_url('/rendez_vous_orientation/annuler/?jeton=' . $token);
    }

    /** Rendez-vous visé par un lien d'annulation encore valide, en LECTURE seule (le jeton n'est pas consommé). */
    public static function findByCancellationToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $token)) {
            return null;
        }
        $row = static::query("SELECT a.* FROM appointments a JOIN appointment_cancellation_tokens t ON t.appointment_id = a.id
            WHERE t.token_hash = ? AND t.expires_at > UTC_TIMESTAMP() AND a.user_id IS NULL
              AND ((a.status = 'demande' AND a.preferred_date >= UTC_DATE()) OR (a.status = 'confirme' AND a.starts_at_utc > UTC_TIMESTAMP()))", [hash('sha256', $token)])->fetch();

        return $row === false ? null : $row;
    }

    public static function cancelGuest(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $token)) {
            return null;
        }
        $db = static::db();
        $db->beginTransaction();
        try {
            $appointment = static::query("SELECT a.* FROM appointments a JOIN appointment_cancellation_tokens t ON t.appointment_id = a.id
                WHERE t.token_hash = ? AND t.expires_at > UTC_TIMESTAMP() AND a.user_id IS NULL
                AND ((a.status = 'demande' AND a.preferred_date >= UTC_DATE())
                    OR (a.status = 'confirme' AND a.starts_at_utc > UTC_TIMESTAMP())) FOR UPDATE", [hash('sha256', $token)])->fetch();
            if (!$appointment) {
                $db->rollBack();
                return null;
            }
            static::query("UPDATE appointments SET status = 'annule' WHERE id = ?", [$appointment['id']]);
            static::query('DELETE FROM appointment_cancellation_tokens WHERE appointment_id = ?', [$appointment['id']]);
            static::query("INSERT INTO appointment_events (appointment_id, type, status) VALUES (?, 'statut', 'annule')", [$appointment['id']]);
            $db->commit();
            return $appointment;
        } catch (\Throwable $exception) {
            $db->rollBack();
            throw $exception;
        }
    }

    public static function search(?string $status, ?array $actor = null): array
    {
        [$scope, $params] = $actor === null ? ['1 = 1', []] : Access::rendezVousSql($actor);
        $sql = "SELECT a.*, p.timezone AS person_timezone,
                   (a.status = 'confirme' AND a.assigned_to IS NOT NULL AND (
                       u.active = 0 OR EXISTS (SELECT 1 FROM availability_exceptions x
                           WHERE x.user_id = a.assigned_to AND x.starts_at_utc < a.ends_at_utc AND x.ends_at_utc > a.starts_at_utc)
                   )) AS needs_reschedule
                FROM appointments a LEFT JOIN people p ON p.id = a.person_id LEFT JOIN users u ON u.id = a.assigned_to WHERE {$scope}";
        if ($status !== null && isset(self::STATUSES[$status])) {
            $sql .= ' AND a.status = ?';
            $params[] = $status;
        }

        return static::query($sql . " ORDER BY a.status = 'demande' DESC, a.preferred_date, a.id LIMIT 200", $params)->fetchAll();
    }

    public static function countsByStatus(?array $actor = null): array
    {
        [$scope, $params] = $actor === null ? ['1 = 1', []] : Access::rendezVousSql($actor);
        return static::query("SELECT a.status, COUNT(*) FROM appointments a WHERE {$scope} GROUP BY a.status", $params)->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    public static function calendar(array $actor, string $from, string $until): array
    {
        [$scope, $params] = Access::rendezVousSql($actor);
        return static::query(
            "SELECT a.* FROM appointments a WHERE {$scope} AND a.starts_at_utc >= ? AND a.starts_at_utc < ?
             AND a.status NOT IN ('annule', 'reporte') ORDER BY a.starts_at_utc, a.id",
            [...$params, $from, $until]
        )->fetchAll();
    }

    public static function planifier(array $actor, int $id, string $status, ?int $assignee, ?string $startUtc, ?string $endUtc): ?array
    {
        $db = static::db();
        $db->beginTransaction();
        try {
            // Sérialise les confirmations, y compris lorsque les horaires de début diffèrent.
            $member = $assignee === null ? null : static::query('SELECT * FROM users WHERE id = ? FOR UPDATE', [$assignee])->fetch();
            [$scope, $params] = Access::rendezVousSql($actor);
            $current = static::query("SELECT a.* FROM appointments a WHERE a.id = ? AND {$scope} FOR UPDATE", [$id, ...$params])->fetch();
            if (!$current) {
                throw new \DomainException('Ce rendez-vous ne fait plus partie de votre agenda.');
            }
            $report = $status === 'reporte' || ($current['status'] === 'confirme' && $status === 'confirme'
                && $current['starts_at_utc'] !== $startUtc && $current['ends_at_utc'] !== null);
            if ($current['status'] === 'reporte' || ($current['status'] === 'annule' && $status !== 'annule')) {
                throw new \DomainException('Ce rendez-vous est clos. Ouvrez celui qui le remplace ou créez une nouvelle demande.');
            }
            if ($report && ($current['status'] !== 'confirme' || $startUtc === $current['starts_at_utc'])) {
                throw new \DomainException('Choisissez une nouvelle heure pour reporter un rendez-vous confirmé.');
            }
            if (!isset(self::STATUSES[$status]) || (($startUtc === null) !== ($endUtc === null)) || ($startUtc !== null && $endUtc <= $startUtc)) {
                throw new \DomainException('Indiquez un intervalle valide.');
            }
            if (!self::peutAttribuer($actor) && $assignee !== (int) $current['assigned_to']) {
                throw new \DomainException('L’attribution est réservée à l’accueil.');
            }
            if (($status === 'confirme' || $report) && ($assignee === null || $startUtc === null)) {
                throw new \DomainException('Choisissez un responsable et indiquez le début et la fin.');
            }
            if ($report && ($startUtc <= gmdate('Y-m-d H:i:s')
                || !static::query('SELECT 1 FROM agencies WHERE id = ? AND active = 1', [$member['agency_id'] ?? $current['agency_id']])->fetchColumn())) {
                throw new \DomainException('Le nouveau créneau doit être à venir et rattaché à une agence active.');
            }
            if ($assignee !== null && (!$member || (!$member['active'] && ($status === 'confirme' || $report)) || !Access::peutTenirRendezVous($member))) {
                throw new \DomainException('Choisissez un membre habilité à tenir ce rendez-vous.');
            }
            if ($assignee !== null && $startUtc !== null && in_array($status, ['demande', 'confirme', 'reporte'], true) && static::query(
                "SELECT id FROM appointments WHERE assigned_to = ? AND status IN ('demande', 'confirme') AND id <> ?
                 AND starts_at_utc < ? AND ends_at_utc > ? LIMIT 1 FOR UPDATE",
                [$assignee, $id, $endUtc, $startUtc]
            )->fetchColumn()) {
                throw new \DomainException('Ce créneau est déjà pris. Choisissez un autre horaire.');
            }
            if (($status === 'confirme' || $report) && static::query(
                'SELECT id FROM availability_exceptions WHERE user_id = ? AND starts_at_utc < ? AND ends_at_utc > ? LIMIT 1',
                [$assignee, $endUtc, $startUtc]
            )->fetchColumn()) {
                throw new \DomainException('Le responsable est absent sur ce créneau.');
            }
            if ($report) {
                static::query("UPDATE appointments SET status = 'reporte' WHERE id = ?", [$id]);
                // Date et demi-journée du remplacement en heure locale de l'agence (B2, A7)
                $localStart = self::local($startUtc, self::zone(isset($member['agency_id']) ? (int) $member['agency_id'] : ($current['agency_id'] ? (int) $current['agency_id'] : null)), 'Y-m-d H:i');
                $date = substr($localStart, 0, 10);
                $halfDay = substr($localStart, 11, 5) < '13:00' ? 'matin' : 'apres-midi';
                static::query(
                    "INSERT INTO appointments (reference, user_id, person_id, motive, mode, preferred_date, preferred_slot,
                        first_name, last_name, email, phone, message, status, assigned_to, agency_id, scheduled_at, starts_at_utc, ends_at_utc)
                     SELECT ?, user_id, person_id, motive, mode, ?, ?, first_name, last_name, email, phone, message,
                        'confirme', ?, COALESCE(?, agency_id), ?, ?, ?
                     FROM appointments WHERE id = ?",
                    ['TMP-' . bin2hex(random_bytes(6)), $date, $halfDay, $assignee, $member['agency_id'] ?? null, $startUtc, $startUtc, $endUtc, $id]
                );
                $replacementId = (int) $db->lastInsertId();
                $reference = sprintf('RDV-%s-%06d', date('Y'), $replacementId);
                static::query('UPDATE appointments SET reference = ? WHERE id = ?', [$reference, $replacementId]);
                static::query('UPDATE appointments SET rescheduled_to_id = ? WHERE id = ?', [$replacementId, $id]);
                static::query('INSERT INTO prospect_appointments (appointment_id, prospect_id, proposed_by)
                    SELECT ?, prospect_id, proposed_by FROM prospect_appointments WHERE appointment_id = ?', [$replacementId, $id]);
                static::query("INSERT INTO appointment_events (appointment_id, user_id, type, status, created_at) VALUES
                    (?, ?, 'deplacement', 'reporte', UTC_TIMESTAMP()), (?, ?, 'creation', 'confirme', UTC_TIMESTAMP())", [$id, $actor['id'], $replacementId, $actor['id']]);
                $replacement = static::find($replacementId);
                $db->commit();
                return $replacement;
            }
            $deplace = $startUtc !== $current['starts_at_utc'];
            if ($deplace) {
                // Un ancien message reste périmé même si un autre déplacement rétablit le créneau initial.
                static::query("UPDATE mail_outbox SET expires_at_utc = UTC_TIMESTAMP()
                    WHERE kind = 'appointment-reminder' AND related = ? AND status <> 'envoye'", [$current['reference']]);
            }
            // G2 : un rendez-vous dont l'heure change doit être rappelé de nouveau (reminded_at remis à NULL)
            static::query('UPDATE appointments SET status = ?, assigned_to = ?, starts_at_utc = ?, ends_at_utc = ?, scheduled_at = ?, agency_id = COALESCE(?, agency_id),
                    reminded_at = CASE WHEN ? = 1 THEN NULL ELSE reminded_at END WHERE id = ?',
                [$status, $assignee, $startUtc, $endUtc, $startUtc, $member['agency_id'] ?? null, $deplace ? 1 : 0, $id]);
            // G3 : un événement par nature de changement (la règle des 24 h des rappels s'appuie sur eux)
            $types = array_keys(array_filter([
                'statut' => $status !== $current['status'],
                'affectation' => $assignee !== ($current['assigned_to'] === null ? null : (int) $current['assigned_to']),
                'deplacement' => $deplace,
            ]));
            foreach ($types as $type) {
                static::query('INSERT INTO appointment_events (appointment_id, user_id, type, status, created_at) VALUES (?, ?, ?, ?, UTC_TIMESTAMP())', [$id, $actor['id'], $type, $status]);
            }
            $db->commit();
            return null;
        } catch (\Throwable $exception) {
            $db->rollBack();
            throw $exception;
        }
    }

    public static function peutAttribuer(array $actor): bool
    {
        return in_array($actor['role'], ['admin', 'secretaire'], true);
    }

    public static function statusLabel(string $status): string
    {
        return self::STATUSES[$status][0] ?? $status;
    }

    public static function statusTone(string $status): string
    {
        return self::STATUSES[$status][1] ?? 'grey';
    }
}
