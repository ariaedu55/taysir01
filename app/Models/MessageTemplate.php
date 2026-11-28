<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Modèles de messages de l'équipe (phase 7, lot 7A ; docs/gestion-phase-7.md) et journal des messages ouverts.
 * Champs remplis automatiquement : {prenom}, {nom}, {reference}, {piece}, {solde}, {date_rdv}, {agence}.
 */
final class MessageTemplate extends Model
{
    protected static string $table = 'message_templates';

    public const CANAUX = ['les-deux' => 'WhatsApp et e-mail', 'whatsapp' => 'WhatsApp', 'email' => 'E-mail'];
    public const CHAMPS = ['{prenom}', '{nom}', '{reference}', '{piece}', '{solde}', '{date_rdv}', '{agence}'];

    public static function tous(): array
    {
        return static::query('SELECT * FROM message_templates ORDER BY active DESC, title')->fetchAll();
    }

    public static function actifs(): array
    {
        return static::query('SELECT * FROM message_templates WHERE active = 1 ORDER BY title')->fetchAll();
    }

    public static function enregistrer(?int $id, string $titre, string $texte, string $canal, bool $actif, int $auteur): void
    {
        if ($id === null) {
            static::query('INSERT INTO message_templates (title, body, channel, active, created_by) VALUES (?, ?, ?, ?, ?)', [$titre, $texte, $canal, $actif ? 1 : 0, $auteur]);
        } else {
            static::query('UPDATE message_templates SET title = ?, body = ?, channel = ?, active = ? WHERE id = ?', [$titre, $texte, $canal, $actif ? 1 : 0, $id]);
        }
    }

    /** Journal d'un message ouvert (jamais le texte). */
    public static function journaliser(int $personId, ?int $requestId, ?int $templateId, string $canal, int $userId): void
    {
        static::query('INSERT INTO message_logs (person_id, request_id, template_id, channel, user_id) VALUES (?, ?, ?, ?, ?)', [$personId, $requestId, $templateId, $canal, $userId]);
    }

    /** Derniers messages ouverts pour une personne (fiche). */
    public static function historique(int $personId, int $limite = 8): array
    {
        return static::query(
            'SELECT l.channel, l.created_at, t.title, u.first_name, u.last_name, r.reference
             FROM message_logs l JOIN users u ON u.id = l.user_id LEFT JOIN message_templates t ON t.id = l.template_id
             LEFT JOIN service_requests r ON r.id = l.request_id
             WHERE l.person_id = ? ORDER BY l.id DESC LIMIT ' . (int) $limite,
            [$personId]
        )->fetchAll();
    }
}
