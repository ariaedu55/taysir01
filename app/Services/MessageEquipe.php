<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Appointment;

/**
 * Remplissage des modèles de messages de l'équipe et liens d'envoi (phase 7, lot 7A).
 * Aucun envoi automatique : le lien ouvre WhatsApp (wa.me) ou la messagerie (mailto:) de l'agent, qui envoie lui-même.
 */
final class MessageEquipe
{
    /** Valeurs des champs pour une personne, et un dossier facultatif (déjà contrôlés par l'appelant). */
    public static function contexte(array $personne, ?array $dossier): array
    {
        $db = Database::connection();
        $piece = '';
        $solde = '';
        if ($dossier !== null) {
            $s = $db->prepare("SELECT title FROM request_requirements WHERE request_id = ? AND status IN ('manquante', 'refusee') ORDER BY id LIMIT 3");
            $s->execute([(int) $dossier['id']]);
            $piece = implode(', ', $s->fetchAll(\PDO::FETCH_COLUMN));
            $q = $db->prepare('SELECT q.amount - COALESCE((SELECT SUM(p.amount) FROM finance_payments p LEFT JOIN finance_cancellations c ON c.payment_id = p.id
                WHERE p.request_id = q.request_id AND c.payment_id IS NULL), 0) FROM finance_quotes q WHERE q.request_id = ?');
            $q->execute([(int) $dossier['id']]);
            $reste = $q->fetchColumn();
            $solde = $reste === false ? '' : number_format(max(0, (int) $reste), 0, ',', ' ') . ' FCFA';
        }
        // Prochain rendez-vous confirmé, à l'heure de son agence
        $r = $db->prepare("SELECT starts_at_utc, agency_id FROM appointments WHERE person_id = ? AND status = 'confirme' AND starts_at_utc > UTC_TIMESTAMP() ORDER BY starts_at_utc LIMIT 1");
        $r->execute([(int) $personne['id']]);
        $rdv = $r->fetch();
        $dateRdv = $rdv ? Appointment::local($rdv['starts_at_utc'], Appointment::zone($rdv['agency_id'] ? (int) $rdv['agency_id'] : null), 'd/m/Y à H:i') : '';

        return [
            '{prenom}' => (string) $personne['first_name'],
            '{nom}' => (string) $personne['last_name'],
            '{reference}' => (string) ($dossier['reference'] ?? ''),
            '{piece}' => $piece,
            '{solde}' => $solde,
            '{date_rdv}' => $dateRdv,
            '{agence}' => Appointment::city(isset($personne['agency_id']) && $personne['agency_id'] ? (int) $personne['agency_id'] : null),
        ];
    }

    public static function remplir(string $texte, array $contexte): string
    {
        return strtr($texte, $contexte);
    }

    /** Numéro au format international pour wa.me (Sénégal par défaut pour un numéro local à 9 chiffres), ou null. */
    public static function numeroWhatsApp(?string $telephone): ?string
    {
        $chiffres = preg_replace('/\D/', '', (string) $telephone);
        if ($chiffres === '') {
            return null;
        }
        if (str_starts_with($chiffres, '00')) {
            $chiffres = substr($chiffres, 2);
        }
        if (strlen($chiffres) === 9 && $chiffres[0] === '7') {
            $chiffres = '221' . $chiffres;
        }

        return strlen($chiffres) >= 10 && strlen($chiffres) <= 15 ? $chiffres : null;
    }

    public static function lienWhatsApp(string $numero, string $texte): string
    {
        return 'https://wa.me/' . $numero . '?text=' . rawurlencode($texte);
    }

    public static function lienEmail(string $email, string $sujet, string $texte): string
    {
        return 'mailto:' . $email . '?subject=' . rawurlencode($sujet) . '&body=' . rawurlencode($texte);
    }
}
