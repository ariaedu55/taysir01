<?php

declare(strict_types=1);

namespace App\Controllers\Gestion;

use App\Core\Auth;
use App\Core\Session;
use App\Models\MessageTemplate;
use App\Models\Person;
use App\Models\ServiceRequest;
use App\Services\Access;
use App\Services\Audit;
use App\Services\FormInput;
use App\Services\MessageEquipe;

/**
 * Messages de l'équipe (phase 7, lot 7A ; docs/gestion-phase-7.md).
 * - /gestion/personnes/{id}/message : choisir un modèle, relire le texte rempli, ouvrir WhatsApp ou l'e-mail ;
 *   l'ouverture passe par le serveur pour être journalisée (sans le texte), puis la page ouvre le lien.
 * - /gestion/messages-modeles : modèles, administrateur seulement.
 * Droits : la fiche doit être dans le périmètre du compte (Access::personnesSql), le dossier aussi (Access::peutOuvrir).
 */
final class MessageEquipeController extends EspaceController
{
    public function form(string $id): void
    {
        [$personne, $dossier] = $this->cible($id, $_GET['dossier'] ?? null);
        $modeles = MessageTemplate::actifs();
        $choisi = null;
        foreach ($modeles as $m) {
            if ((string) $m['id'] === (string) ($_GET['modele'] ?? '')) {
                $choisi = $m;
            }
        }
        $contexte = MessageEquipe::contexte($personne, $dossier);
        $this->page('message-equipe', 'personnes', 'Message à ' . $personne['first_name'] . ' ' . $personne['last_name'], [
            'personne' => $personne,
            'dossier' => $dossier,
            'modeles' => $modeles,
            'choisi' => $choisi,
            'texte' => $choisi !== null ? MessageEquipe::remplir($choisi['body'], $contexte) : '',
            // Champs du modèle restés vides (pas de dossier, pas de pièce, pas de devis, pas de rendez-vous)
            'manquants' => $choisi !== null ? array_values(array_filter(array_keys($contexte), static fn (string $champ): bool => str_contains($choisi['body'], $champ) && $contexte[$champ] === '')) : [],
            'whatsapp' => MessageEquipe::numeroWhatsApp($personne['phone'] ?? null),
            'email' => filter_var((string) ($personne['email'] ?? ''), FILTER_VALIDATE_EMAIL) ? $personne['email'] : null,
            'historique' => MessageTemplate::historique((int) $personne['id']),
        ]);
    }

    /** Journalise puis affiche la page qui ouvre WhatsApp ou l'e-mail (aucun envoi par le serveur). */
    public function ouvrir(string $id): void
    {
        [$personne, $dossier] = $this->cible($id, $_POST['dossier'] ?? null);
        $canal = FormInput::text('canal', 10);
        $texte = FormInput::multiline('texte', 2000);
        $modele = ctype_digit((string) ($_POST['modele'] ?? '')) ? (int) $_POST['modele'] : null;
        $retour = '/gestion/personnes/' . $personne['id'] . '/message/' . ($dossier ? '?dossier=' . rawurlencode($dossier['reference']) : '');
        if ($texte === '' || !in_array($canal, ['whatsapp', 'email'], true)) {
            $this->back(['message' => 'Choisissez un modèle ou écrivez le message, puis le canal.'], $retour);
        }
        if ($canal === 'whatsapp') {
            $numero = MessageEquipe::numeroWhatsApp($personne['phone'] ?? null) ?? $this->back(['message' => 'Numéro de téléphone absent ou invalide sur la fiche.'], $retour);
            $lien = MessageEquipe::lienWhatsApp($numero, $texte);
        } else {
            $email = filter_var((string) ($personne['email'] ?? ''), FILTER_VALIDATE_EMAIL) ?: $this->back(['message' => 'Adresse e-mail absente ou invalide sur la fiche.'], $retour);
            $lien = MessageEquipe::lienEmail($email, 'Taysir Orientation Voyage' . ($dossier ? ' · dossier ' . $dossier['reference'] : ''), $texte);
        }
        MessageTemplate::journaliser((int) $personne['id'], $dossier ? (int) $dossier['id'] : null, $modele, $canal, (int) Auth::id());
        Audit::log('message_equipe', 'personne', (string) $personne['id'], ['canal' => $canal, 'modele' => $modele, 'dossier' => $dossier['reference'] ?? null]);
        $this->page('message-envoi', 'personnes', 'Ouverture de ' . ($canal === 'whatsapp' ? 'WhatsApp' : 'la messagerie'), [
            'lien' => $lien,
            'canal' => $canal,
            'retourFiche' => '/gestion/personnes/' . $personne['id'] . '/',
        ]);
    }

    public function modeles(): void
    {
        $this->page('messages-modeles', 'messages-modeles', 'Modèles de messages', ['modeles' => MessageTemplate::tous()]);
    }

    public function enregistrerModele(): void
    {
        $id = ctype_digit((string) ($_POST['id'] ?? '')) ? (int) $_POST['id'] : null;
        $titre = FormInput::text('title', 120);
        $texte = FormInput::multiline('body', 2000);
        $canal = FormInput::text('channel', 10);
        if ($titre === '' || $texte === '' || !isset(MessageTemplate::CANAUX[$canal])) {
            $this->back(['modele' => 'Indiquez un titre, un texte et un canal.'], '/gestion/messages-modeles/');
        }
        MessageTemplate::enregistrer($id, $titre, $texte, $canal, !empty($_POST['active']), (int) Auth::id());
        Audit::log('modele_message', 'modele', $id === null ? 'nouveau' : (string) $id, ['titre' => $titre]);
        Session::flash('_success', 'Modèle enregistré.');
        redirect_to('/gestion/messages-modeles/');
    }

    /** @return array{0: array, 1: ?array} Personne du périmètre et dossier facultatif de cette personne, du périmètre */
    private function cible(string $id, mixed $reference): array
    {
        $me = Auth::user();
        $personne = ctype_digit($id) ? Person::findScoped($me, (int) $id) : null;
        if ($personne === null) {
            ctype_digit($id) && Person::find((int) $id) !== null ? $this->refuser('personne', $id, 'message hors périmètre') : $this->abort(404);
        }
        $dossier = null;
        if (is_string($reference) && $reference !== '') {
            $dossier = ServiceRequest::findByReference($reference);
            if ($dossier === null || (int) $dossier['person_id'] !== (int) $personne['id'] || !Access::peutOuvrir($me, $dossier)) {
                $this->refuser('dossier', $reference, 'message sur un dossier hors périmètre');
            }
        }

        return [$personne, $dossier];
    }
}
