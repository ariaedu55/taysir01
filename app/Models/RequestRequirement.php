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

    /** Enregistre un fichier validé par DocumentStorage et conserve chaque dépôt antérieur. */
    public static function deposit(int $requestId, int $id, int $userId, array $file, int $previousDocumentId): int
    {
        $stored = null;
        try {
            return self::withLockedRequest($requestId, static function (array $request) use ($requestId, $id, $userId, $file, $previousDocumentId, &$stored): int {
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
                $recentDeposits = (int) static::query(
                    'SELECT COUNT(*) FROM request_documents WHERE request_id = ? AND requirement_id = ? AND created_at > CURRENT_TIMESTAMP - INTERVAL 24 HOUR',
                    [$requestId, $id]
                )->fetchColumn();
                if ($recentDeposits >= self::MAX_DEPOSITS_PER_DAY) {
                    throw new DomainException('Limite de 10 dépôts par pièce sur 24 heures atteinte. Réessayez plus tard.');
                }

                $stored = DocumentStorage::store($file);
                static::query(
                    'INSERT INTO request_documents (request_id, requirement_id, original_name, stored_name, mime, size) VALUES (?, ?, ?, ?, ?, ?)',
                    [$requestId, $id, $file['name'], $stored, $file['mime'], $file['size']]
                );
                $documentId = (int) static::db()->lastInsertId();
                static::query(
                    "UPDATE request_requirements SET document_id = ?, status = 'deposee', refusal_reason = NULL, expires_on = NULL WHERE request_id = ? AND id = ?",
                    [$documentId, $requestId, $id]
                );

                return $documentId;
            });
        } catch (Throwable $exception) {
            if ($stored !== null && !@unlink(DocumentStorage::directory() . '/' . $stored)) {
                error_log('Impossible de nettoyer un dépôt de pièce attendue après un échec.');
            }
            throw $exception;
        }
    }

    public static function isExpired(array $requirement): bool
    {
        return $requirement['status'] === 'validee' && !empty($requirement['expires_on'])
            && $requirement['expires_on'] < gmdate('Y-m-d');
    }

    private static function rows(int $requestId): array
    {
        $rows = static::query(
            'SELECT id, title, status, document_id, refusal_reason, expires_on, minimum_valid_until FROM request_requirements WHERE request_id = ? ORDER BY id',
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
