<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Modèles de procédure versionnés (migration 014 ; docs/gestion-phase-2.md, 2C, E8, E9, N5).
 * - Une version = une ligne (clé + numéro) ; statuts brouillon, publie, retire ; jamais supprimée.
 * - Une seule version publiée par clé : garantie par la base (colonne générée published_key, index unique).
 * - Seul un brouillon se modifie ; une version publiée ou retirée n'accepte qu'une revérification.
 * - Publication refusée sans date et auteur de vérification ni au moins une source officielle.
 */
final class ProcedureTemplate extends Model
{
    protected static string $table = 'procedure_templates';

    public const STATUTS = ['brouillon' => ['Brouillon', 'grey'], 'publie' => ['Publié', 'green'], 'retire' => ['Retiré', 'amber']];

    /** Toutes les versions, groupées par clé, la plus récente d'abord. */
    public static function toutes(): array
    {
        return array_map([self::class, 'decode'], static::query(
            'SELECT t.*, v.first_name AS verified_first_name, v.last_name AS verified_last_name
             FROM procedure_templates t LEFT JOIN users v ON v.id = t.verified_by
             ORDER BY t.template_key, t.version DESC'
        )->fetchAll());
    }

    public static function get(int $id): ?array
    {
        $row = static::query('SELECT * FROM procedure_templates WHERE id = ?', [$id])->fetch();

        return $row === false ? null : self::decode($row);
    }

    /** Versions publiées, seules proposées à l'ouverture d'un dossier (D5). */
    public static function publiees(): array
    {
        return array_map([self::class, 'decode'], static::query("SELECT * FROM procedure_templates WHERE status = 'publie' ORDER BY title")->fetchAll());
    }

    /** Version publiée plus récente que celle d'un dossier (bandeau « nouvelle version », informatif seulement). */
    public static function versionPlusRecente(int $templateId): ?array
    {
        $row = static::query(
            "SELECT p.* FROM procedure_templates t JOIN procedure_templates p ON p.template_key = t.template_key
             WHERE t.id = ? AND p.status = 'publie' AND p.version > t.version",
            [$templateId]
        )->fetch();

        return $row === false ? null : self::decode($row);
    }

    /** Vérification trop ancienne (E9) ? */
    public static function aRevoir(array $template, ?\DateTimeImmutable $maintenant = null): bool
    {
        if ($template['verified_at'] === null) {
            return true;
        }
        $mois = max(1, (int) config('gestion.fraicheur_mois', 6));
        $limite = ($maintenant ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify("-{$mois} months");

        return new \DateTimeImmutable($template['verified_at'], new \DateTimeZone('UTC')) < $limite;
    }

    public static function cleExiste(string $cle): bool
    {
        return (bool) static::query('SELECT 1 FROM procedure_templates WHERE template_key = ? LIMIT 1', [$cle])->fetchColumn();
    }

    /** Nouveau modèle (version 1, brouillon). */
    public static function creer(array $data, int $auteur): array
    {
        static::query(
            "INSERT INTO procedure_templates (template_key, version, title, service, destination, status, steps, sources, created_by)
             VALUES (?, 1, ?, ?, ?, 'brouillon', ?, ?, ?)",
            [$data['template_key'], $data['title'], $data['service'], $data['destination'], json_encode($data['steps'], JSON_UNESCAPED_UNICODE), json_encode($data['sources'], JSON_UNESCAPED_SLASHES), $auteur]
        );

        return self::get((int) static::db()->lastInsertId());
    }

    /** Modifier un BROUILLON (une version publiée ou retirée est figée). */
    public static function modifierBrouillon(array $template, array $data): void
    {
        if ($template['status'] !== 'brouillon') {
            throw new \DomainException('Seul un brouillon se modifie. Créez une nouvelle version.');
        }
        static::query(
            "UPDATE procedure_templates SET title = ?, service = ?, destination = ?, steps = ?, sources = ? WHERE id = ? AND status = 'brouillon'",
            [$data['title'], $data['service'], $data['destination'], json_encode($data['steps'], JSON_UNESCAPED_UNICODE), json_encode($data['sources'], JSON_UNESCAPED_SLASHES), $template['id']]
        );
    }

    /** Nouvelle version (brouillon) copiée de celle-ci ; numéro suivant de la clé. */
    public static function nouvelleVersion(array $template, int $auteur): array
    {
        $db = static::db();
        $db->beginTransaction();
        try {
            $suivante = (int) static::query('SELECT MAX(version) FROM procedure_templates WHERE template_key = ? FOR UPDATE', [$template['template_key']])->fetchColumn() + 1;
            static::query(
                "INSERT INTO procedure_templates (template_key, version, title, service, destination, status, steps, sources, created_by)
                 SELECT template_key, ?, title, service, destination, 'brouillon', steps, sources, ? FROM procedure_templates WHERE id = ?",
                [$suivante, $auteur, $template['id']]
            );
            $id = (int) $db->lastInsertId();
            $db->commit();
        } catch (\Throwable $exception) {
            $db->rollBack();
            throw $exception;
        }

        return self::get($id);
    }

    /** Vérification par l'agence : date et auteur (permis sur toute version, seule modification d'une version figée). */
    public static function verifier(array $template, int $auteur): void
    {
        static::query('UPDATE procedure_templates SET verified_at = UTC_TIMESTAMP(), verified_by = ? WHERE id = ?', [$auteur, $template['id']]);
    }

    /** Publier : refus sans vérification ni source ; la version publiée précédente est retirée dans la même transaction. */
    public static function publier(array $template): void
    {
        if ($template['status'] !== 'brouillon') {
            throw new \DomainException('Seul un brouillon se publie.');
        }
        if ($template['verified_at'] === null || $template['verified_by'] === null || $template['sources'] === []) {
            throw new \DomainException('Publication refusée : faites vérifier le modèle (date et auteur) et indiquez au moins une source officielle.');
        }
        $db = static::db();
        $db->beginTransaction();
        try {
            static::query("UPDATE procedure_templates SET status = 'retire' WHERE template_key = ? AND status = 'publie'", [$template['template_key']]);
            static::query("UPDATE procedure_templates SET status = 'publie' WHERE id = ? AND status = 'brouillon'", [$template['id']]);
            $db->commit();
        } catch (\Throwable $exception) {
            $db->rollBack();
            if ($exception instanceof \PDOException && (int) ($exception->errorInfo[1] ?? 0) === 1062) {
                throw new \DomainException('Une autre version vient d’être publiée. Rechargez la page.');
            }
            throw $exception;
        }
    }

    /** Retirer une version publiée (jamais de suppression). */
    public static function retirer(array $template): void
    {
        static::query("UPDATE procedure_templates SET status = 'retire' WHERE id = ? AND status = 'publie'", [$template['id']]);
    }

    private static function decode(array $row): array
    {
        $row['steps'] = json_decode((string) $row['steps'], true) ?: [];
        $row['sources'] = json_decode((string) ($row['sources'] ?? ''), true) ?: [];

        return $row;
    }
}
