<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Services\DocumentStorage;
use DomainException;
use OutOfBoundsException;
use PDOException;
use Throwable;

/** Pièces attendues d'un dossier et cycle de vie de leur dépôt courant. */
final class RequestRequirement extends Model
{
    protected static string $table = 'request_requirements';

    public const TITLE_MAX = 190;
    public const REASON_MAX = 2000;
    private const MAX_DEPOSITS_PER_DAY = 10;

    public static function isOpen(array $request): bool
    {
        return !in_array($request['status'], ['terminee', 'annulee'], true);
    }

    public static function ensureOpen(array $request): void
    {
        if (!self::isOpen($request)) {
            throw new DomainException('Ce dossier est fermé.');
        }
    }

    public static function forRequest(int $requestId): array
    {
        $rows = self::rows($requestId);
        if ($rows !== []) {
            return $rows;
        }
        $titles = self::frozenTitles($requestId);
        if ($titles === []) {
            return [];
        }

        // Les étapes sont figées à l'ouverture. La seconde lecture, sous verrou,
        // couvre une initialisation ou un ajout manuel par une autre requête.
        return self::withLockedRequest($requestId, static function () use ($requestId, $titles): array {
            if (self::rows($requestId) === []) {
                self::seed($requestId, $titles);
            }

            return self::rows($requestId);
        });
    }

    /** Exécute une opération sous le verrou du dossier actuel, avec ses affectations pour les écritures équipe. */
    public static function withLockedRequest(int $requestId, callable $operation, bool $lockAssignments = false): mixed
    {
        $db = static::db();
        $db->beginTransaction();
        try {
            // Même ordre que les transmissions : affectations puis dossier (mis à jour ou référencé par un événement).
            $assignments = $lockAssignments ? static::query(
                'SELECT poste, user_id FROM request_assignments WHERE request_id = ? FOR UPDATE',
                [$requestId]
            )->fetchAll() : [];
            $request = static::query('SELECT * FROM service_requests WHERE id = ? FOR UPDATE', [$requestId])->fetch();
            if ($request === false) {
                throw new OutOfBoundsException('Dossier introuvable.');
            }
            $result = $operation($request, array_column($assignments, null, 'poste'));
            $db->commit();

            return $result;
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    }

    /** Ajout manuel, sous le verrou du dossier. */
    public static function add(int $requestId, string $title, ?string $minimumValidUntil = null): int
    {
        if (self::rows($requestId) === []) {
            self::seed($requestId, self::frozenTitles($requestId));
        }
        try {
            static::query('INSERT INTO request_requirements (request_id, title, minimum_valid_until) VALUES (?, ?, ?)', [$requestId, $title, $minimumValidUntil]);
        } catch (PDOException $exception) {
            if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
                throw new DomainException('Une pièce attendue porte déjà cet intitulé.');
            }
            throw $exception;
        }

        return (int) static::db()->lastInsertId();
    }

    /** Décision sur le dépôt affiché dans le formulaire, sous le verrou du dossier. */
    public static function review(int $requestId, int $id, int $documentId, int $reviewerId, string $decision, string $reason, ?string $expiresOn): void
    {
        $requirement = self::inRequest($requestId, $id);
        if ($requirement['status'] !== 'deposee' || (int) $requirement['document_id'] !== $documentId) {
            throw new DomainException('Ce dépôt a changé ou a déjà été examiné. Vérifiez la pièce affichée.');
        }
        if ($decision === 'validee' && $requirement['minimum_valid_until'] !== null
            && ($expiresOn === null || $expiresOn < $requirement['minimum_valid_until'])) {
            throw new DomainException('La pièce doit être valable au moins jusqu’au ' . $requirement['minimum_valid_until'] . '.');
        }

        static::query(
            'UPDATE request_requirements SET status = ?, refusal_reason = ?, expires_on = ? WHERE request_id = ? AND id = ?',
            [$decision, $decision === 'refusee' ? $reason : null, $decision === 'validee' ? $expiresOn : null, $requestId, $id]
        );
        if ($decision === 'refusee') {
            ServiceRequest::addEvent($requestId, $reviewerId, 'Pièce refusée : « ' . $requirement['title'] . " »\n" . $reason);
        }
    }

    /**
     * Enregistre un envoi de 1 à DocumentStorage::MAX_FILES fichiers validés et conserve chaque dépôt antérieur.
     * Les fichiers d'un même envoi partagent deposit_batch ; le premier sert d'ancre (document_id) à la version examinée.
     *
     * @param list<array> $files Fichiers validés par DocumentStorage::collect()
     * @return int Identifiant de l'ancre
     */
    public static function deposit(int $requestId, int $id, int $userId, array $files, int $previousDocumentId): int
    {
        if ($files === [] || count($files) > DocumentStorage::MAX_FILES) {
            throw new DomainException('Joignez entre 1 et ' . DocumentStorage::MAX_FILES . ' fichiers pour cette pièce.');
        }
        $stored = [];
        try {
            return self::withLockedRequest($requestId, static function (array $request) use ($requestId, $id, $userId, $files, $previousDocumentId, &$stored): int {
                // Octroi, révocation et dépôt prennent tous le verrou du même dossier.
                if (!(ServiceRequest::clientPermissions($request, $userId, true)['deposit'] ?? false)) {
                    throw new OutOfBoundsException('Dossier introuvable.');
                }
                self::ensureOpen($request);
                $requirement = self::inRequest($requestId, $id);
                if ((int) $requirement['document_id'] !== $previousDocumentId) {
                    throw new DomainException('Le dépôt a changé. Rechargez la page avant de joindre une nouvelle version.');
                }
                if (!in_array($requirement['status'], ['manquante', 'refusee'], true) && !self::isExpired($requirement)) {
                    throw new DomainException('Cette pièce a déjà été déposée. Un autre dépôt est possible après un refus.');
                }
                // Limite par envoi : un envoi de plusieurs fichiers compte pour un
                $recentDeposits = (int) static::query(
                    'SELECT COUNT(DISTINCT COALESCE(deposit_batch, CAST(id AS CHAR))) FROM request_documents
                     WHERE request_id = ? AND requirement_id = ? AND created_at > CURRENT_TIMESTAMP - INTERVAL 24 HOUR',
                    [$requestId, $id]
                )->fetchColumn();
                if ($recentDeposits >= self::MAX_DEPOSITS_PER_DAY) {
                    throw new DomainException('Limite de 10 dépôts par pièce sur 24 heures atteinte. Réessayez plus tard.');
                }

                $batch = bin2hex(random_bytes(16));
                $anchor = null;
                foreach ($files as $file) {
                    $name = DocumentStorage::store($file);
                    $stored[] = $name;
                    static::query(
                        'INSERT INTO request_documents (request_id, requirement_id, deposit_batch, original_name, stored_name, mime, size) VALUES (?, ?, ?, ?, ?, ?, ?)',
                        [$requestId, $id, $batch, $file['name'], $name, $file['mime'], $file['size']]
                    );
                    $anchor ??= (int) static::db()->lastInsertId();
                }
                static::query(
                    "UPDATE request_requirements SET document_id = ?, status = 'deposee', refusal_reason = NULL, expires_on = NULL WHERE request_id = ? AND id = ?",
                    [$anchor, $requestId, $id]
                );

                return $anchor;
            });
        } catch (Throwable $exception) {
            // Échec : les fichiers de cet envoi, écrits avant l'annulation de la transaction, sont retirés du disque
            foreach ($stored as $name) {
                if (!@unlink(DocumentStorage::directory() . '/' . $name)) {
                    error_log('Impossible de nettoyer un dépôt de pièce attendue après un échec.');
                }
            }
            throw $exception;
        }
    }

    /**
     * Fichiers de la version courante de chaque pièce : l'ancre et les fichiers du même envoi, dans l'ordre d'envoi.
     *
     * @param list<array> $requirements Lignes de forRequest()
     * @return array<int, list<array>> id de la pièce => fichiers (id, original_name, mime, size)
     */
    public static function versionFiles(int $requestId, array $requirements): array
    {
        $anchors = array_values(array_filter(array_map(static fn (array $r): ?int => $r['document_id'] ?? null, $requirements)));
        if ($anchors === []) {
            return [];
        }
        $in = implode(',', array_fill(0, count($anchors), '?'));
        $rows = static::query(
            "SELECT a.id AS anchor, d.id, d.original_name, d.mime, d.size FROM request_documents a
             JOIN request_documents d ON d.request_id = a.request_id
                AND (d.id = a.id OR (a.deposit_batch IS NOT NULL AND d.deposit_batch = a.deposit_batch))
             WHERE a.request_id = ? AND a.id IN ({$in}) ORDER BY d.id",
            [$requestId, ...$anchors]
        )->fetchAll();
        $parAncre = [];
        foreach ($rows as $row) {
            $parAncre[(int) $row['anchor']][] = $row;
        }
        $files = [];
        foreach ($requirements as $r) {
            if (!empty($r['document_id'])) {
                $files[(int) $r['id']] = $parAncre[(int) $r['document_id']] ?? [];
            }
        }

        return $files;
    }

    /**
     * Phase 12 : ne pas demander une pièce de la démarche pour ce dossier (motif noté), ou la redemander.
     * Appelée sous le verrou du dossier (changeRequirements). Seule une pièce encore à fournir ou à corriger se retire.
     */
    public static function waive(int $requestId, int $id, bool $waive, string $reason): void
    {
        $requirement = self::inRequest($requestId, $id);
        // Seule une pièce encore à fournir, sans fichier, se retire (règles de la base : 017 et 026)
        if ($waive && $requirement['status'] !== 'manquante') {
            throw new DomainException('Seule une pièce encore à fournir peut être marquée « non demandée ».');
        }
        if (!$waive && $requirement['status'] !== 'non-demandee') {
            throw new DomainException('Cette pièce est déjà demandée.');
        }
        static::query(
            'UPDATE request_requirements SET status = ?, waived_reason = ? WHERE request_id = ? AND id = ?',
            [$waive ? 'non-demandee' : 'manquante', $waive ? mb_substr($reason, 0, 255) : null, $requestId, $id]
        );
    }

    public static function isExpired(array $requirement): bool
    {
        return $requirement['status'] === 'validee' && !empty($requirement['expires_on'])
            && $requirement['expires_on'] < gmdate('Y-m-d');
    }

    private static function rows(int $requestId): array
    {
        $rows = static::query(
            'SELECT id, title, hint, optional, provider, status, document_id, refusal_reason, waived_reason, expires_on, minimum_valid_until
             FROM request_requirements WHERE request_id = ? ORDER BY status = "non-demandee", optional, id',
            [$requestId]
        )->fetchAll();

        foreach ($rows as &$row) {
            $row['id'] = (int) $row['id'];
            $row['document_id'] = $row['document_id'] === null ? null : (int) $row['document_id'];
        }

        return $rows;
    }

    private static function inRequest(int $requestId, int $id): array
    {
        $row = static::query('SELECT title, status, document_id, expires_on, minimum_valid_until FROM request_requirements WHERE request_id = ? AND id = ?', [$requestId, $id])->fetch();
        if ($row === false) {
            throw new OutOfBoundsException('Pièce attendue introuvable.');
        }

        return $row;
    }

    private static function frozenTitles(int $requestId): array
    {
        $steps = static::query(
            'SELECT documents FROM request_steps WHERE request_id = ? AND JSON_LENGTH(documents) > 0 ORDER BY position',
            [$requestId]
        )->fetchAll(\PDO::FETCH_COLUMN);

        $titles = [];
        foreach ($steps as $documents) {
            $values = json_decode($documents, true);
            if (!is_array($values)) {
                continue;
            }
            foreach ($values as $value) {
                if (!is_string($value)) {
                    continue;
                }
                $title = trim((string) preg_replace('/\s+/u', ' ', $value));
                if ($title !== '') {
                    $titles[] = mb_substr($title, 0, self::TITLE_MAX);
                }
            }
        }

        return $titles;
    }

    private static function seed(int $requestId, array $titles): void
    {
        foreach ($titles as $title) {
            static::query(
                "INSERT INTO request_requirements (request_id, title, source) VALUES (?, ?, 'procedure') ON DUPLICATE KEY UPDATE id = id",
                [$requestId, $title]
            );
        }
    }
}
