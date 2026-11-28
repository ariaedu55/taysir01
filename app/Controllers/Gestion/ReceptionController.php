<?php

declare(strict_types=1);

namespace App\Controllers\Gestion;

use App\Core\Auth;
use App\Core\Session;
use App\Models\Reception;
use App\Services\FormInput;
use DomainException;

final class ReceptionController extends EspaceController
{
    public function index(): void
    {
        $this->page('accueil-agence', 'accueil-agence', 'Accueil de l’agence', [
            'visits' => Reception::visits(gmdate('Y-m-d')),
        ]);
    }

    public function register(): void
    {
        $fields = [FormInput::text('first_name', 80), FormInput::text('last_name', 80), FormInput::text('purpose', 190)];
        if (in_array('', $fields, true)) {
            $this->finish('/gestion/accueil/', 'Indiquez le prénom, le nom et l’objet de la visite.', false);
        }
        // Revue du 28/11, écart 4 : un moyen de contact au moins, repris sur la fiche
        $telephone = FormInput::phone('phone');
        $email = mb_strtolower(FormInput::text('email', 190));
        if ($telephone === '' && $email === '') {
            $this->finish('/gestion/accueil/', 'Indiquez un téléphone ou une adresse e-mail.', false);
        }
        if (($telephone !== '' && !FormInput::validPhone($telephone)) || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))) {
            $this->finish('/gestion/accueil/', 'Téléphone ou adresse e-mail invalide.', false);
        }
        Reception::register($fields[0], $fields[1], $fields[2], (int) Auth::id(), $telephone !== '' ? $telephone : null, $email !== '' ? $email : null);
        $this->finish('/gestion/accueil/', 'Visite enregistrée.');
    }

    public function update(string $id): void
    {
        $action = FormInput::text('action', 20);
        if (!ctype_digit($id) || !in_array($action, ['recu', 'termine', 'convertir'], true)) {
            $this->abort(404);
        }
        // Choix explicite : une fiche proposée, ou « Créer une nouvelle fiche » (valeur vide) ; jamais de création par défaut
        if ($action === 'convertir' && !array_key_exists('person_id', $_POST)) {
            $this->finish('/gestion/accueil/', 'Choisissez une fiche proposée, ou « Créer une nouvelle fiche ».', false);
        }
        $person = FormInput::text('person_id', 20);
        if ($person !== '' && (!ctype_digit($person) || (int) $person < 1)) {
            $this->finish('/gestion/accueil/', 'Choisissez une fiche proposée, ou « Créer une nouvelle fiche ».', false);
        }
        $me = Auth::user();
        try {
            Reception::handleVisit((int) $id, $action, (int) $person, (int) $me['id'], isset($me['agency_id']) ? (int) $me['agency_id'] : null);
        } catch (DomainException $exception) {
            $this->finish('/gestion/accueil/', $exception->getMessage(), false);
        }
        $this->finish('/gestion/accueil/', 'Visite mise à jour.');
    }

    public function duplicates(): void
    {
        $left = $this->filter('a');
        $right = $this->filter('b');
        $pair = $left !== null && $right !== null && ctype_digit($left) && ctype_digit($right)
            ? Reception::compare((int) $left, (int) $right) : [];
        $this->page('doublons', 'doublons', 'Fiches à rapprocher', ['pairs' => Reception::candidates(), 'pair' => $pair]);
    }

    public function merge(): void
    {
        if (($_POST['confirmed'] ?? null) !== '1') {
            $this->finish('/gestion/doublons/', 'Confirmez la comparaison des fiches.', false);
        }
        try {
            Reception::merge((int) FormInput::text('source', 20), (int) FormInput::text('target', 20), (int) Auth::id());
        } catch (DomainException $exception) {
            $this->finish('/gestion/doublons/', $exception->getMessage(), false);
        }
        $this->finish('/gestion/doublons/', 'Les fiches ont été rapprochées.');
    }

    private function finish(string $path, string $message, bool $success = true): never
    {
        Session::flash($success ? '_success' : '_notice', $message);
        redirect_to($path);
    }
}
