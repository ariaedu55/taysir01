<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Services\Access;
use App\Services\Audit;
use App\Services\FormInput;
use DomainException;
use PDO;
use Throwable;

final class Prospect extends Model
{
    public const PAGE = 30;
    public const CALLS = ['contacte' => 'Joignable', 'interesse' => 'Intéressé', 'a-rappeler' => 'À rappeler', 'non-interesse' => 'Pas intéressé'];
    public const STATES = ['nouveau' => 'Nouveau', ...self::CALLS, 'transmis' => 'Transmis à l’accueil'];
    public const MODES = ['telephone', 'visio', 'whatsapp', 'agence-dakar', 'agence-ziguinchor'];

    public static function today(array $actor): string
    {
        return (new \DateTimeImmutable('today', Appointment::zone((int) ($actor['agency_id'] ?? 0))))->format('Y-m-d');
    }

    public static function search(array $actor, string $query, string $filter, int $campaign, int $page = 1, int $limit = self::PAGE, int $school = 0, string $level = ''): array
    {
        [$scope, $params] = Access::prospectsSql($actor);
        [$text, $textParams] = Person::filtre($query);
        $where = "{$scope} AND p.merged_into IS NULL{$text}";
        $params = [...$params, ...$textParams];
        if ($campaign > 0) {
            $where .= ' AND pr.campaign_id = ?';
            $params[] = $campaign;
        }
        if ($school > 0) {
            $where .= ' AND pr.school_id = ?';
            $params[] = $school;
        }
        if ($level !== '') {
            $where .= ' AND pr.level = ?';
            $params[] = $level;
        }
        if ($filter === 'libres') {
            $where .= " AND pr.assigned_to IS NULL AND pr.state <> 'transmis'";
        } elseif ($filter === 'relances') {
            $where .= " AND pr.state = 'a-rappeler' AND pr.follow_up_on <= ?";
            $params[] = self::today($actor);
        } elseif (isset(self::STATES[$filter])) {
            $where .= ' AND pr.state = ?';
            $params[] = $filter;
        }
        $total = (int) static::query("SELECT COUNT(*) FROM prospects pr JOIN people p ON p.id = pr.person_id WHERE {$where}", $params)->fetchColumn();
        $page = min(max(1, $page), max(1, (int) ceil($total / $limit)));
        $offset = ($page - 1) * $limit;
        $rows = static::query(self::select() . " WHERE {$where}
            ORDER BY pr.state = 'a-rappeler' AND pr.follow_up_on IS NOT NULL DESC, pr.follow_up_on, pr.id DESC LIMIT {$limit} OFFSET {$offset}", $params)->fetchAll();
        return [$rows, $total, $page];
    }

    public static function stats(array $actor): array
    {
        [$scope, $params] = Access::prospectsSql($actor);
        return static::query("SELECT COUNT(*) AS total, COALESCE(SUM(pr.assigned_to IS NULL AND pr.state <> 'transmis'), 0) AS libres,
            COALESCE(SUM(pr.state = 'a-rappeler' AND pr.follow_up_on <= ?), 0) AS relances,
            COALESCE(SUM(pr.state = 'transmis'), 0) AS transmis
            FROM prospects pr JOIN people p ON p.id = pr.person_id AND p.merged_into IS NULL WHERE {$scope}", [self::today($actor), ...$params])->fetch();
    }

    public static function scoped(array $actor, int $id): ?array
    {
        [$scope, $params] = Access::prospectsSql($actor);
        return static::query(self::select() . " WHERE pr.id = ? AND {$scope} AND p.merged_into IS NULL", [$id, ...$params])->fetch() ?: null;
    }

    private static function select(): string
    {
        return 'SELECT pr.*, p.first_name, p.last_name, p.email, p.phone, s.name AS school, c.name AS campaign, a.name AS agency,
            CONCAT(u.first_name, \' \', u.last_name) AS assignee
            FROM prospects pr JOIN people p ON p.id = pr.person_id JOIN prospect_schools s ON s.id = pr.school_id
            JOIN prospect_campaigns c ON c.id = pr.campaign_id JOIN agencies a ON a.id = pr.agency_id LEFT JOIN users u ON u.id = pr.assigned_to';
    }

    public static function catalog(array $actor): array
    {
        $agency = in_array('admin', Access::postes($actor), true) ? null : (int) ($actor['agency_id'] ?? 0);
        $where = $agency === null ? '' : ' AND a.id = ?';
        $params = $agency === null ? [] : [$agency];
        [$scope, $scopeParams] = Access::prospectsSql($actor);
        return [
            'agencies' => static::query("SELECT a.id, a.name FROM agencies a WHERE a.active = 1{$where} ORDER BY a.name", $params)->fetchAll(),
            'schools' => static::query("SELECT s.*, a.name AS agency FROM prospect_schools s JOIN agencies a ON a.id = s.agency_id
                WHERE a.active = 1{$where} ORDER BY s.name, s.city", $params)->fetchAll(),
            'campaigns' => static::query("SELECT c.*, a.name AS agency,
                (SELECT COUNT(*) FROM prospects pr WHERE pr.campaign_id = c.id AND {$scope}) AS collected,
                (SELECT COUNT(DISTINCT pr.school_id) FROM prospects pr WHERE pr.campaign_id = c.id AND {$scope}) AS schools
                FROM prospect_campaigns c JOIN agencies a ON a.id = c.agency_id WHERE a.active = 1{$where} ORDER BY c.starts_on DESC, c.name", [...$scopeParams, ...$scopeParams, ...$params])->fetchAll(),
        ];
    }

    public static function recentImports(array $actor): array
    {
        $admin = in_array('admin', Access::postes($actor), true);
        return static::query("SELECT l.created_at, l.detail, c.name FROM audit_log l
            LEFT JOIN prospect_campaigns c ON CAST(c.id AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci = l.subject_ref
            WHERE l.action = 'prospects_importes'" . ($admin ? '' : ' AND l.user_id = ? AND c.agency_id = ?') . ' ORDER BY l.id DESC LIMIT 10',
            $admin ? [] : [$actor['id'], $actor['agency_id'] ?? null])->fetchAll();
    }

    public static function createCatalog(array $actor, string $kind, int $agency, string $name, string $city, string $start, string $end): void
    {
        if (!Access::gereCampagnes($actor) || $name === '') {
            throw new DomainException('Indiquez un nom.');
        }
        if (!in_array('admin', Access::postes($actor), true)) {
            $agency = (int) ($actor['agency_id'] ?? 0);
        }
        if (!static::query('SELECT 1 FROM agencies WHERE id = ? AND active = 1', [$agency])->fetchColumn()) {
            throw new DomainException('Choisissez une agence active.');
        }
        try {
            if ($kind === 'school' && $city !== '') {
                static::query('INSERT INTO prospect_schools (agency_id, name, city) VALUES (?, ?, ?)', [$agency, $name, $city]);
                return;
            }
            if ($kind === 'campaign' && FormInput::date($start) && ($end === '' || (FormInput::date($end) && $end >= $start))) {
                static::query('INSERT INTO prospect_campaigns (agency_id, name, starts_on, ends_on) VALUES (?, ?, ?, ?)', [$agency, $name, $start, $end ?: null]);
                return;
            }
        } catch (\PDOException $exception) {
            if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
                throw new DomainException('Cette entrée existe déjà dans l’agence.');
            }
            throw $exception;
        }
        throw new DomainException('Renseignez la ville ou vérifiez les dates de la campagne.');
    }

    public static function context(array $actor, int $school, int $campaign): int
    {
        $agency = (int) static::query('SELECT s.agency_id FROM prospect_schools s JOIN prospect_campaigns c ON c.agency_id = s.agency_id
            JOIN agencies a ON a.id = s.agency_id AND a.active = 1 WHERE s.id = ? AND c.id = ?', [$school, $campaign])->fetchColumn();
        if (!$agency || (!in_array('admin', Access::postes($actor), true) && $agency !== (int) ($actor['agency_id'] ?? 0))) {
            throw new DomainException('Choisissez un établissement et une campagne de la même agence accessible.');
        }
        return $agency;
    }

    public static function duplicate(array $data): bool
    {
        $phone = ltrim($data['phone'], '+');
        return (bool) static::query("SELECT 1 FROM people WHERE merged_into IS NULL AND
            ((? <> '' AND email = ?) OR (? <> '' AND first_name = ? AND last_name = ? AND REGEXP_REPLACE(phone, '[^0-9]', '') = ?)) LIMIT 1",
            [$data['email'], $data['email'], $phone, $data['first_name'], $data['last_name'], $phone])->fetchColumn();
    }

    /** Chaque ligne écartée est conservée dans le rapport, jamais fusionnée ou rattachée automatiquement. */
    public static function collect(array $actor, int $school, int $campaign, array $rows, bool $import): array
    {
        return self::transaction(static function () use ($actor, $school, $campaign, $rows, $import): array {
            // Les collectes de toutes les agences vérifient le référentiel commun dans le même ordre.
            static::query('SELECT id FROM agencies ORDER BY id LIMIT 1 FOR UPDATE')->fetchColumn();
            $agency = self::context($actor, $school, $campaign);
            $ids = $duplicates = [];
            foreach ($rows as $line => $row) {
                if (self::duplicate($row)) {
                    $duplicates[] = $line;
                    continue;
                }
                $person = Person::create($row + ['agency_id' => $agency, 'profile' => 'eleve', 'timezone' => Appointment::zone($agency)->getName()],
                    $import ? 'import' : 'prospect', (int) $actor['id']);
                static::query('INSERT INTO prospects (person_id, agency_id, school_id, campaign_id, level, created_by, assigned_to)
                    VALUES (?, ?, ?, ?, ?, ?, ?)', [$person['id'], $agency, $school, $campaign, $row['level'], $actor['id'], $actor['role'] === 'commerciale' ? $actor['id'] : null]);
                $ids[] = (int) static::db()->lastInsertId();
            }
            Audit::log($import ? 'prospects_importes' : 'prospect_cree', 'campagne', (string) $campaign,
                ['ajoutes' => count($ids), 'doublons' => count($duplicates)], required: true);
            return [$ids, $duplicates];
        });
    }

    public static function assign(array $actor, array $ids, int $assignee): int
    {
        return self::transaction(static function () use ($actor, $ids, $assignee): int {
            $target = static::query("SELECT id, agency_id FROM users WHERE id = ? AND role = 'commerciale' AND active = 1 FOR UPDATE", [$assignee])->fetch();
            if (!$target) {
                throw new DomainException('Choisissez une commerciale active.');
            }
            sort($ids, SORT_NUMERIC);
            $count = 0;
            foreach ($ids as $id) {
                $row = self::lock($actor, $id);
                if ($row['state'] === 'transmis' || (int) $row['agency_id'] !== (int) $target['agency_id']) {
                    throw new DomainException('Vérifiez que les prospects sont en suivi et relèvent de la même agence que la commerciale.');
                }
                if ((int) $row['assigned_to'] !== $assignee) {
                    static::query('UPDATE prospects SET assigned_to = ? WHERE id = ?', [$assignee, $id]);
                    self::event($row, $actor, 'attribution', 'Commerciale attribuée', ['avant' => $row['assigned_to'], 'apres' => $assignee]);
                    $count++;
                }
            }
            return $count;
        });
    }

    public static function events(int $id): array
    {
        return static::query("SELECT e.*, CONCAT(u.first_name, ' ', u.last_name) AS author FROM prospect_events e JOIN users u ON u.id = e.author_id
            WHERE e.prospect_id = ? ORDER BY e.id DESC LIMIT 100", [$id])->fetchAll();
    }

    public static function appointments(int $id): array
    {
        return static::query('SELECT a.* FROM prospect_appointments pa JOIN appointments a ON a.id = pa.appointment_id
            WHERE pa.prospect_id = ? ORDER BY a.id DESC LIMIT 30', [$id])->fetchAll();
    }

    public static function update(array $actor, int $id, string $action, array $data): void
    {
        self::transaction(static function () use ($actor, $id, $action, $data): void {
            $row = self::lock($actor, $id);
            if ($actor['role'] !== 'admin' && ($actor['role'] !== 'commerciale' || (int) $row['assigned_to'] !== (int) $actor['id'])) {
                throw new \OutOfBoundsException('Hors périmètre de suivi.');
            }
            if ($action === 'transmission' && $row['state'] === 'transmis') {
                return;
            }
            if ($row['state'] === 'transmis') {
                throw new DomainException('Ce prospect a déjà été transmis à l’accueil.');
            }
            if ($action === 'appel') {
                $status = $data['state'];
                $date = $data['follow_up'];
                if (!isset(self::CALLS[$status]) || ($status === 'a-rappeler' && (!FormInput::date($date) || $date < self::today($actor)))) {
                    throw new DomainException('Choisissez un résultat valide et indiquez la date de relance si nécessaire.');
                }
                static::query('UPDATE prospects SET state = ?, follow_up_on = ? WHERE id = ?', [$status, $status === 'a-rappeler' ? $date : null, $id]);
                self::event($row, $actor, $action, self::CALLS[$status] . ($data['note'] !== '' ? "\n" . $data['note'] : ''), ['resultat' => $status]);
                return;
            }
            if ($action !== 'rendez-vous' && $action !== 'transmission') {
                throw new DomainException('Action inconnue.');
            }
            $pending = static::query("SELECT a.reference FROM prospect_appointments pa JOIN appointments a ON a.id = pa.appointment_id
                WHERE pa.prospect_id = ? AND (a.status = 'demande' OR (a.status = 'confirme' AND a.ends_at_utc >= UTC_TIMESTAMP()))
                ORDER BY a.id DESC LIMIT 1 FOR UPDATE", [$id])->fetchColumn();
            if (!$pending) {
                $person = Person::find((int) $row['person_id']);
                $date = $data['date'];
                $agency = (int) $row['agency_id'];
                $zone = Appointment::zone($agency);
                if (!FormInput::date($date) || $date < (new \DateTimeImmutable('today', $zone))->format('Y-m-d')
                    || !in_array($data['slot'], ['matin', 'apres-midi'], true) || !in_array($data['mode'], self::MODES, true)) {
                    throw new DomainException('Choisissez un jour à venir, une plage et un mode de rendez-vous.');
                }
                $agencyMode = ['agence-dakar' => 'dakar', 'agence-ziguinchor' => 'ziguinchor'][$data['mode']] ?? null;
                if ($agencyMode !== null && Appointment::agencyId($agencyMode) !== $agency) {
                    throw new DomainException('Choisissez un mode de rendez-vous disponible dans l’agence du prospect.');
                }
                if (in_array($data['mode'], ['telephone', 'whatsapp'], true) && !$person['phone']) {
                    throw new DomainException('Cette fiche ne contient pas de téléphone. Choisissez un autre mode.');
                }
                $appointment = Appointment::proposeFor($person, (int) $row['agency_id'], (int) $actor['id'], [
                    'preferred_date' => $date, 'preferred_slot' => $data['slot'], 'mode' => $data['mode'],
                ]);
                static::query('INSERT INTO prospect_appointments (appointment_id, prospect_id, proposed_by) VALUES (?, ?, ?)', [$appointment['id'], $id, $actor['id']]);
                $pending = $appointment['reference'];
                self::event($row, $actor, 'rendez-vous', 'Rendez-vous proposé · ' . $pending, ['rendez_vous' => $pending]);
            }
            if ($action === 'transmission') {
                static::query("UPDATE prospects SET state = 'transmis', follow_up_on = NULL WHERE id = ?", [$id]);
                self::event($row, $actor, $action, 'Fiche transmise à l’accueil · ' . $pending, ['rendez_vous' => $pending]);
            }
        });
    }

    private static function lock(array $actor, int $id): array
    {
        [$scope, $params] = Access::prospectsSql($actor);
        return static::query("SELECT pr.* FROM prospects pr WHERE pr.id = ? AND {$scope} FOR UPDATE", [$id, ...$params])->fetch()
            ?: throw new \OutOfBoundsException('Prospect hors périmètre.');
    }

    private static function event(array $row, array $actor, string $event, string $description, array $audit): void
    {
        static::query('INSERT INTO prospect_events (prospect_id, author_id, event_type, detail) VALUES (?, ?, ?, ?)', [$row['id'], $actor['id'], $event, $description]);
        Audit::log('suivi_prospect', 'prospect', (string) $row['id'], ['action' => $event, ...$audit], required: true);
    }

    private static function transaction(callable $operation): mixed
    {
        $db = static::db();
        $db->beginTransaction();
        try {
            $result = $operation();
            $db->commit();
            return $result;
        } catch (Throwable $exception) {
            $db->rollBack();
            throw $exception;
        }
    }
}
