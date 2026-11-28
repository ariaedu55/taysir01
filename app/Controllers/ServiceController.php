<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Session;
use App\Models\ServiceRequest;
use App\Services\Demarches;
use App\Services\DocumentStorage;
use App\Services\FormInput;
use App\Services\Notifier;

/**
 * Pages de service (admission, logement, visa, prise en charge, billet, hôtel) à leurs adresses WordPress,
 * avec le formulaire de demande réservé aux comptes connectés. Voir docs/demandes-rendez-vous.md.
 */
final class ServiceController extends Controller
{
    public const CIVILITIES = ['M.' => 'Monsieur', 'Mme' => 'Madame'];

    public function show(string $slug): void
    {
        $service = ServiceRequest::service($slug) ?? $this->abort(404);

        $this->render('pages/service', [
            'service' => $service,
            'services' => ServiceRequest::services(),
            'user' => Auth::user(),
            'countries' => FormInput::countries(),
            'coveredCountries' => Demarches::pays(),
            'purposes' => Demarches::motifs($service['key']),
            'generalPieces' => ($general = Demarches::choisir($service['key'], null, null)) !== null && $general['destination'] === null && $general['purpose'] === null
                ? Demarches::pieces($general) : [],
            'civilities' => self::CIVILITIES,
            'title' => $service['title'],
            'description' => $service['lead'],
            'canonical' => url('/' . $service['slug'] . '/'),
            'styles' => ['css/destination.css', 'css/content.css', 'css/auth.css', 'css/portal.css'],
            'scripts' => ['js/portal.js'],
        ]);
    }

    public function store(string $slug): void
    {
        $service = ServiceRequest::service($slug) ?? $this->abort(404);
        $fallback = '/' . $service['slug'] . '/#formulaire';
        $today = FormInput::today();

        $data = [
            'service' => $service['key'],
            'civility' => FormInput::text('civility', 10),
            'first_name' => FormInput::text('first_name', 80),
            'last_name' => FormInput::text('last_name', 80),
            'email' => strtolower(FormInput::text('email', 190)),
            'phone' => FormInput::phone('phone'),
            'birth_date' => FormInput::text('birth_date', 10),
            'destination' => FormInput::text('destination', 120),
            'message' => FormInput::multiline('message'),
            'details' => [],
        ];

        $errors = [];
        if (!array_key_exists($data['civility'], self::CIVILITIES)) {
            $data['civility'] = null;
        }
        if ($data['first_name'] === '') {
            $errors['first_name'] = 'Indiquez votre prénom.';
        }
        if ($data['last_name'] === '') {
            $errors['last_name'] = 'Indiquez votre nom.';
        }
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Indiquez une adresse e-mail valide.';
        }
        if (!FormInput::validPhone($data['phone'])) {
            $errors['phone'] = 'Indiquez un numéro de téléphone complet, de préférence joignable sur WhatsApp.';
        }
        if ($data['birth_date'] === '') {
            $data['birth_date'] = null;
        } elseif (($birth = FormInput::date($data['birth_date'])) === null || $birth >= $today || $birth < $today->modify('-100 years')) {
            $errors['birth_date'] = 'Cette date de naissance n’est pas valide.';
        }
        if ($service['identity']) {
            $identity = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', FormInput::text('identity_number', 30)));
            if ($identity !== '') {
                $data['details']['identity_number'] = $identity;
            }
        }

        // Champs propres au service (config/services.php).
        $dates = [];
        $data['purpose'] = null;
        foreach ($service['fields'] as [$name, $label, $type, $required, $extra]) {
            $value = FormInput::text($name, $type === 'textarea' ? 2000 : 120);
            // Phase 12 : pays couverts + « Autre pays » (texte libre), motif de la démarche
            if ($type === 'country') {
                $value = $value === '' ? '' : Demarches::paysSaisi($value, FormInput::text($name . '_autre', 120));
                if ($value !== '') {
                    $data['destination'] = $value;
                    continue;
                }
            }
            if ($type === 'purpose') {
                if ($value !== '' && !isset(Demarches::motifs($service['key'])[$value])) {
                    $errors[$name] = 'Choisissez une option de la liste.';
                } elseif ($value !== '') {
                    $data['purpose'] = $value;
                    continue;
                }
            }
            if ($value === '') {
                if ($required) {
                    $errors[$name] = $type === 'select' ? 'Choisissez une option.' : 'Ce champ est obligatoire.';
                }
                continue;
            }
            if ($type === 'select' && !in_array($value, $extra, true)) {
                $errors[$name] = 'Choisissez une option de la liste.';
            } elseif ($type === 'number' && (!ctype_digit($value) || (int) $value < 1 || (int) $value > 20)) {
                $errors[$name] = 'Indiquez un nombre entre 1 et 20.';
            } elseif ($type === 'date') {
                $date = FormInput::date($value);
                if ($date === null) {
                    $errors[$name] = 'Cette date n’est pas valide.';
                } elseif ($date < $today) {
                    $errors[$name] = 'Choisissez une date à venir.';
                } else {
                    $dates[$name] = $date;
                }
            }
            if ($name !== 'destination') {
                $data['details'][$name] = $type === 'number' ? (int) $value : $value;
            }
        }
        if (isset($dates['departure'], $dates['return']) && $dates['return'] < $dates['departure']) {
            $errors['return'] = 'Le retour doit suivre le départ.';
        }
        if (isset($dates['check_in'], $dates['check_out']) && $dates['check_out'] <= $dates['check_in']) {
            $errors['check_out'] = 'La fin du séjour doit suivre son début.';
        }

        $files = [];
        if ($service['documents']) {
            [$files, $fileError] = DocumentStorage::collect('documents');
            if ($fileError !== null) {
                $errors['documents'] = $fileError;
            }
        }
        if ($errors !== [] && $files !== []) {
            // Les fichiers ne peuvent pas être conservés entre deux envois du formulaire.
            $errors['documents'] ??= 'Sélectionnez à nouveau vos documents avant d’envoyer.';
        }
        if ($errors !== []) {
            Session::flash('_notice', 'Certains champs sont à compléter ou à corriger.');
            $this->back($errors, $fallback);
        }

        $documents = [];
        foreach ($files as $file) {
            $documents[] = ['name' => $file['name'], 'stored' => DocumentStorage::store($file), 'mime' => $file['mime'], 'size' => $file['size']];
        }
        $request = ServiceRequest::create($data, (int) Auth::id(), $documents);
        Notifier::requestCreated($request, count($documents));

        Session::flash('_success', 'Votre demande ' . $request['reference'] . ' est bien enregistrée. Un conseiller Taysir revient vers vous rapidement.');
        redirect_to('/mon-espace/demandes/' . $request['reference'] . '/');
    }
}
