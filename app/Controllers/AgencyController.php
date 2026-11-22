<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Session;
use App\Models\ContactMessage;
use App\Services\FormInput;
use App\Services\Notifier;

/** Pages de l'agence : présentation, contact, FAQ, règles, confidentialité, témoignages. Voir docs/pages-agence.md. */
final class AgencyController extends Controller
{
    /** auth.css : styles des champs de formulaire (.field), utilisés par la page Contact. */
    private const STYLES = ['css/destination.css', 'css/content.css', 'css/auth.css', 'css/portal.css', 'css/agency.css'];
    /** Messages de contact envoyés par session et par heure. */
    private const HOURLY_LIMIT = 5;

    public function about(): void
    {
        $this->render('pages/about', [
            'about' => self::content('about'),
            'testimonials' => self::content('testimonials'),
            'title' => 'Qui sommes-nous ?',
            'description' => 'Taysir Orientation Voyage accompagne élèves, étudiants et professionnels dans leurs projets d’études et de voyage : orientation, admission, visa, logement et voyage.',
            'canonical' => url('/about-us/'),
            'styles' => self::STYLES,
        ]);
    }

    public function contact(): void
    {
        $this->render('pages/contact', [
            'user' => Auth::user(),
            'offices' => config('site.offices'),
            'title' => 'Contact',
            'description' => 'Contactez Taysir Orientation Voyage à Dakar ou à Ziguinchor : téléphone, WhatsApp, e-mail, horaires et formulaire de contact.',
            'canonical' => url('/contact/'),
            'styles' => self::STYLES,
            'scripts' => ['js/portal.js'],
        ]);
    }

    public function sendMessage(): void
    {
        if (FormInput::isBot()) {
            redirect_to('/contact/');
        }
        $data = [
            'name' => FormInput::text('name', 160),
            'email' => strtolower(FormInput::text('email', 190)),
            'phone' => FormInput::phone('phone'),
            'message' => FormInput::multiline('message'),
        ];
        $errors = [];
        if ($data['name'] === '') {
            $errors['name'] = 'Indiquez votre nom.';
        }
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Indiquez une adresse e-mail valide.';
        }
        if (!FormInput::validPhone($data['phone'])) {
            $errors['phone'] = 'Indiquez un numéro de téléphone complet.';
        }
        if (strlen($data['message']) < 10) {
            $errors['message'] = 'Écrivez votre message (10 caractères au moins).';
        }
        $sent = array_filter((array) Session::get('_contact_sent', []), static fn ($time) => $time > time() - 3600);
        if ($errors === [] && count($sent) >= self::HOURLY_LIMIT) {
            $errors['message'] = 'Vous avez déjà envoyé plusieurs messages. Écrivez-nous sur WhatsApp si c’est urgent.';
        }
        if ($errors !== []) {
            $this->back($errors, '/contact/#formulaire');
        }

        ContactMessage::create($data, Auth::id());
        Notifier::contactReceived($data);
        $sent[] = time();
        Session::set('_contact_sent', array_values($sent));
        Session::flash('_success', 'Merci ' . $data['name'] . ', votre message est bien arrivé. Nous vous répondons au plus vite.');
        redirect_to('/contact/#formulaire');
    }

    public function faq(): void
    {
        $this->render('pages/faq', [
            'groups' => config('faq'),
            'title' => 'Questions fréquentes',
            'description' => 'Services, tarifs, visa, compte en ligne, documents, agences et horaires : les réponses aux questions les plus fréquentes sur Taysir Orientation Voyage.',
            'canonical' => url('/faq/'),
            'styles' => self::STYLES,
        ]);
    }

    public function rules(): void
    {
        $this->render('pages/rules', [
            'html' => self::content('rules')['html'],
            'title' => 'Nos règles et valeurs',
            'description' => 'Transparence, confidentialité, professionnalisme, éthique : les règles et les valeurs qui guident Taysir Orientation Voyage.',
            'canonical' => url('/nos-regles-et-valeurs/'),
            'styles' => self::STYLES,
        ]);
    }

    public function privacy(): void
    {
        $this->render('pages/privacy', [
            'title' => 'Conditions et confidentialité',
            'description' => 'Comment Taysir Orientation Voyage recueille, utilise et protège vos données personnelles.',
            'canonical' => url('/terms-conditions/'),
            'styles' => self::STYLES,
        ]);
    }

    /** /temoignage/ (tous) et /avis-etudiants/ (étudiants seulement). */
    public function testimonials(bool $studentsOnly = false): void
    {
        $testimonials = self::content('testimonials');
        if ($studentsOnly) {
            $testimonials = array_values(array_filter($testimonials, static fn (array $t) => $t['student']));
        }

        $this->render('pages/testimonials', [
            'testimonials' => $testimonials,
            'photos' => $studentsOnly ? [] : self::content('gallery'),
            'studentsOnly' => $studentsOnly,
            'title' => $studentsOnly ? 'Conseils des anciens étudiants' : 'Témoignages',
            'description' => $studentsOnly
                ? 'Ils étudient au Canada ou en France avec l’accompagnement de Taysir : leurs retours d’expérience.'
                : 'Visas obtenus, admissions, voyages : nos clients racontent leur expérience avec Taysir Orientation Voyage.',
            'canonical' => url($studentsOnly ? '/avis-etudiants/' : '/temoignage/'),
            'styles' => self::STYLES,
        ]);
    }

    private static function content(string $name): array
    {
        return require BASE_PATH . '/database/content/agency/' . $name . '.php';
    }
}
