<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/** Demandes de service (table service_requests) avec documents et historique. */
final class ServiceRequest extends Model
{
    protected static string $table = 'service_requests';

    /** Statut => [libellé pour le client, libellé court, teinte du badge]. */
    public const STATUSES = [
        'nouvelle' => ['Reçue', 'Nouvelle', 'blue'],
        'en-cours' => ['En cours de traitement', 'En cours', 'amber'],
        'attente-client' => ['En attente de vos éléments', 'Attente client', 'violet'],
        'terminee' => ['Terminée', 'Terminée', 'green'],
        'annulee' => ['Annulée', 'Annulée', 'grey'],
    ];

    /** @return list<array> Services de config/services.php */
    public static function services(): array
    {
        return config('services');
    }

    public static function service(string $key): ?array
    {
        foreach (self::services() as $service) {
            if ($service['key'] === $key || $service['slug'] === $key) {
                return $service;
            }
        }

        return null;
    }

    /**
     * Crée une demande, ses documents (déjà enregistrés) et l'événement de création.
     *
     * @param list<array{name: string, stored: string, mime: string, size: int}> $documents
     */
    /**
     * Ce que voit un client (C5) : une SEULE règle pour les listes, les compteurs et la fiche, par la personne de son
     * compte ; user_id ne sert qu'en secours pour une ligne pas encore rattachée (avant la reprise 011).
     */
    public const CLIENT_SCOPE = '(person_id = (SELECT cu.person_id FROM users cu WHERE cu.id = ?) OR (person_id IS NULL AND user_id = ?))';

    public static function create(array $data, int $userId, array $documents): array
    {
        $person = Person::pourCompte(User::find($userId) ?? throw new \RuntimeException('Compte introuvable.'));
        $db = static::db();
        $db->beginTransaction();
        try {
            static::query(
                'INSERT INTO service_requests (reference, user_id, person_id, service, civility, first_name, last_name, email, phone, birth_date, destination, details, message)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    'TMP-' . bin2hex(random_bytes(6)), $userId, $person['id'], $data['service'], $data['civility'], $data['first_name'], $data['last_name'],
                    $data['email'], $data['phone'], $data['birth_date'], $data['destination'],
                    json_encode($data['details'], JSON_UNESCAPED_UNICODE), $data['message'],
                ]
            );
            $id = (int) $db->lastInsertId();
            $reference = sprintf('TAY-%s-%06d', date('Y'), $id);
            static::query('UPDATE service_requests SET reference = ? WHERE id = ?', [$reference, $id]);
            foreach ($documents as $document) {
                static::query(
                    'INSERT INTO request_documents (request_id, original_name, stored_name, mime, size) VALUES (?, ?, ?, ?, ?)',
                    [$id, $document['name'], $document['stored'], $document['mime'], $document['size']]
                );
            }
            static::query("INSERT INTO request_events (request_id, user_id, type, status, message, visible) VALUES (?, ?, 'creation', 'nouvelle', ?, 1)", [$id, $userId, 'Demande envoyée.']);
            $db->commit();
        } catch (\Throwable $exception) {
            $db->rollBack();
            throw $exception;
        }

        return self::findByReference($reference);
    }

    /** Même règle que CLIENT_SCOPE, pour une demande déjà chargée. */
    public static function appartientAuClient(array $request, int $userId): bool
    {
        $personId = static::query('SELECT person_id FROM users WHERE id = ?', [$userId])->fetchColumn();

        return $request['person_id'] !== null
            ? $personId !== false && $personId !== null && (int) $request['person_id'] === (int) $personId
            : (int) $request['user_id'] === $userId;
    }

    /** La délégation ne sert qu'à consulter ce dossier et à y déposer une pièce attendue. */
    public static function clientPermissions(array $request, int $userId, bool $lock = false): ?array
    {
        if (self::appartientAuClient($request, $userId)) {
            return ['owner' => true, 'deposit' => true];
        }
        $access = RequestTutorAccess::activeForParent((int) $request['id'], $userId, $lock);

        return $access === null ? null : ['owner' => false, 'deposit' => (bool) $access['can_deposit']];
    }

    public static function findByReference(string $reference): ?array
    {
        $row = static::query('SELECT * FROM service_requests WHERE reference = ? LIMIT 1', [$reference])->fetch();

        return $row === false ? null : self::decode($row);
    }

    public static function forUser(int $userId): array
    {
        return array_map([self::class, 'decode'], static::query('SELECT * FROM service_requests WHERE ' . self::CLIENT_SCOPE . ' ORDER BY created_at DESC', [$userId, $userId])->fetchAll());
    }

    /** Résumé du titulaire, avec une pièce à déposer lorsque le dossier est ouvert. */
    public static function dashboardForUser(int $userId): array
    {
        return static::query("SELECT service, reference, status, destination, created_at,
            (SELECT q.id FROM request_requirements q WHERE q.request_id = service_requests.id
                AND service_requests.status NOT IN ('terminee', 'annulee')
                AND (q.status IN ('manquante', 'refusee') OR (q.status = 'validee' AND q.expires_on < ?))
                ORDER BY q.status = 'refusee' DESC, q.id LIMIT 1) AS next_piece
            FROM service_requests WHERE " . self::CLIENT_SCOPE . "
            ORDER BY next_piece IS NOT NULL DESC, status = 'attente-client' DESC,
                status IN ('terminee', 'annulee'), created_at DESC, id DESC LIMIT 4", [gmdate('Y-m-d'), $userId, $userId])->fetchAll();
    }

    /**
     * Liste du back-office, filtrable par statut, par service et par conseiller.
     * $assigned : null (toutes), « moi » (attribuées à $staffId) ou « aucun » (non attribuées).
     */
    public static function search(?string $status, ?string $service, ?string $assigned = null, ?int $staffId = null): array
    {
        $sql = 'SELECT r.*, (SELECT COUNT(*) FROM request_documents d WHERE d.request_id = r.id) AS document_count,
                       a.first_name AS assignee_first_name, a.last_name AS assignee_last_name
                FROM service_requests r LEFT JOIN users a ON a.id = r.assigned_to WHERE 1 = 1';
        $params = [];
        if ($assigned === 'moi' && $staffId !== null) {
            $sql .= ' AND r.assigned_to = ?';
            $params[] = $staffId;
        } elseif ($assigned === 'aucun') {
            $sql .= ' AND r.assigned_to IS NULL';
        }
        if ($status !== null && isset(self::STATUSES[$status])) {
            $sql .= ' AND r.status = ?';
            $params[] = $status;
        }
        if ($service !== null && self::service($service) !== null) {
            $sql .= ' AND r.service = ?';
            $params[] = $service;
        }

        return array_map([self::class, 'decode'], static::query($sql . ' ORDER BY r.created_at DESC LIMIT 200', $params)->fetchAll());
    }

    /**
     * Liste de l'espace équipe, TOUJOURS limitée au périmètre du compte (Access::perimetre).
     * $vue : null (tout le périmètre), « mes » (affectés à ce compte), « a-prendre » (personne à son poste).
     */
    public static function searchScoped(array $user, ?string $status, ?string $service, ?string $vue = null, int $limit = 200): array
    {
        [$scope, $params] = \App\Services\Access::perimetre($user, 'r');
        $sql = "SELECT r.*, (SELECT COUNT(*) FROM request_documents d WHERE d.request_id = r.id) AS document_count,
                       (SELECT GROUP_CONCAT(CONCAT(ra.poste, ':', COALESCE(CONCAT(u.first_name, ' ', u.last_name), 'dans la file')) SEPARATOR '|')
                        FROM request_assignments ra LEFT JOIN users u ON u.id = ra.user_id WHERE ra.request_id = r.id) AS responsables
                FROM service_requests r WHERE {$scope}";
        $postes = array_values(array_intersect(\App\Services\Access::postes($user), array_keys(\App\Services\Access::AFFECTATIONS)));
        if ($vue === 'mes') {
            $sql .= ' AND EXISTS (SELECT 1 FROM request_assignments m WHERE m.request_id = r.id AND m.user_id = ?)';
            $params[] = (int) $user['id'];
        } elseif ($vue === 'a-prendre' && $postes !== []) {
            // Dans la file d'au moins un de ses postes (même règle que Access::fileSql)
            $files = [];
            foreach ($postes as $poste) {
                [$file, $fileParams] = \App\Services\Access::fileSql($poste, 'r');
                $files[] = $file;
                array_push($params, ...$fileParams);
            }
            $sql .= ' AND (' . implode(' OR ', $files) . ") AND r.status NOT IN ('terminee', 'annulee')";
        }
        if ($status !== null && isset(self::STATUSES[$status])) {
            $sql .= ' AND r.status = ?';
            $params[] = $status;
        }
        if ($service !== null && self::service($service) !== null) {
            $sql .= ' AND r.service = ?';
            $params[] = $service;
        }

        return array_map([self::class, 'decode'], static::query($sql . ' ORDER BY r.updated_at DESC LIMIT ' . max(1, min(500, $limit)), $params)->fetchAll());
    }

    /** @return array<string, int> Dossiers du périmètre par statut */
    public static function countsScoped(array $user): array
    {
        [$scope, $params] = \App\Services\Access::perimetre($user, 'r');

        return static::query("SELECT r.status, COUNT(*) FROM service_requests r WHERE {$scope} GROUP BY r.status", $params)->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    /** @return array<string, int> Nombre de demandes par statut (pour un client, ou pour toute l'agence). */
    public static function countsByStatus(?int $userId = null): array
    {
        $sql = 'SELECT status, COUNT(*) FROM service_requests' . ($userId !== null ? ' WHERE ' . self::CLIENT_SCOPE : '') . ' GROUP BY status';

        return static::query($sql, $userId !== null ? [$userId, $userId] : [])->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    public static function documents(int $requestId): array
    {
        return static::query('SELECT * FROM request_documents WHERE request_id = ? ORDER BY id', [$requestId])->fetchAll();
    }

    public static function document(int $requestId, int $documentId): ?array
    {
        $row = static::query('SELECT * FROM request_documents WHERE request_id = ? AND id = ? LIMIT 1', [$requestId, $documentId])->fetch();

        return $row === false ? null : $row;
    }

    public static function addDocument(int $requestId, string $name, string $stored, string $mime, int $size): void
    {
        static::query('INSERT INTO request_documents (request_id, original_name, stored_name, mime, size) VALUES (?, ?, ?, ?, ?)', [$requestId, $name, $stored, $mime, $size]);
    }

    /** Message du client (toujours visible), qui remet la demande en tête des listes du back-office. */
    public static function addEvent(int $requestId, int $userId, string $message): void
    {
        static::query("INSERT INTO request_events (request_id, user_id, type, message, visible) VALUES (?, ?, 'note', ?, 1)", [$requestId, $userId, $message]);
        static::query('UPDATE service_requests SET updated_at = NOW() WHERE id = ?', [$requestId]);
    }

    /** Attribue la demande à un membre de l'équipe (ou la libère), avec une trace interne dans l'historique. */
    public static function assign(array $request, ?array $assignee, int $authorId): void
    {
        static::query('UPDATE service_requests SET assigned_to = ? WHERE id = ?', [$assignee['id'] ?? null, $request['id']]);
        $text = $assignee !== null ? 'Demande attribuée à ' . $assignee['first_name'] . ' ' . $assignee['last_name'] . '.' : 'Demande remise sans conseiller attribué.';
        static::query("INSERT INTO request_events (request_id, user_id, type, message, visible) VALUES (?, ?, 'note', ?, 0)", [$request['id'], $authorId, $text]);
    }

    /** @return array{0: int, 1: int} Demandes ouvertes attribuées à ce membre, et demandes ouvertes sans conseiller */
    public static function assignmentCounts(int $staffId): array
    {
        $row = static::query(
            "SELECT SUM(assigned_to = ?) AS mine, SUM(assigned_to IS NULL) AS free FROM service_requests WHERE status NOT IN ('terminee', 'annulee')",
            [$staffId]
        )->fetch();

        return [(int) ($row['mine'] ?? 0), (int) ($row['free'] ?? 0)];
    }

    /** Historique ; $all = true pour l'équipe (notes internes comprises). */
    public static function events(int $requestId, bool $all = false): array
    {
        return static::query(
            'SELECT e.*, u.first_name AS author_first_name, u.role AS author_role FROM request_events e
             LEFT JOIN users u ON u.id = e.user_id
             WHERE e.request_id = ?' . ($all ? '' : ' AND e.visible = 1') . ' ORDER BY e.created_at, e.id',
            [$requestId]
        )->fetchAll();
    }

    /** Changement de statut et/ou note, par un membre de l'équipe. */
    public static function update(array $request, string $status, string $note, bool $visible, int $authorId): void
    {
        if ($status !== $request['status'] && isset(self::STATUSES[$status])) {
            static::query('UPDATE service_requests SET status = ? WHERE id = ?', [$status, $request['id']]);
            static::query(
                "INSERT INTO request_events (request_id, user_id, type, status, message, visible) VALUES (?, ?, 'statut', ?, ?, 1)",
                [$request['id'], $authorId, $status, $visible && $note !== '' ? $note : null]
            );
            if (!$visible && $note !== '') {
                static::query("INSERT INTO request_events (request_id, user_id, type, message, visible) VALUES (?, ?, 'note', ?, 0)", [$request['id'], $authorId, $note]);
            }

            return;
        }
        if ($note !== '') {
            static::query("INSERT INTO request_events (request_id, user_id, type, message, visible) VALUES (?, ?, 'note', ?, ?)", [$request['id'], $authorId, $note, $visible ? 1 : 0]);
            static::query('UPDATE service_requests SET updated_at = NOW() WHERE id = ?', [$request['id']]);
        }
    }

    public static function statusLabel(string $status, bool $short = false): string
    {
        return self::STATUSES[$status][$short ? 1 : 0] ?? $status;
    }

    public static function statusTone(string $status): string
    {
        return self::STATUSES[$status][2] ?? 'grey';
    }

    private static function decode(array $row): array
    {
        $row['details'] = json_decode($row['details'] ?? '[]', true) ?: [];

        return $row;
    }
}
