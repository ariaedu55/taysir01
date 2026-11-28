<?php

declare(strict_types=1);

use App\Controllers\ActivityController;
use App\Controllers\AgencyController;
use App\Controllers\AppointmentController;
use App\Controllers\AssistantController;
use App\Controllers\AuthController;
use App\Controllers\Client\DashboardController as ClientDashboard;
use App\Controllers\Client\RequestController as ClientRequests;
use App\Controllers\DestinationController;
use App\Controllers\Gestion\DossierController as GestionDossiers;
use App\Controllers\Gestion\EquipeController as GestionEquipe;
use App\Controllers\Gestion\AssistantSettingsController as GestionAssistant;
use App\Controllers\Gestion\JourneeController as GestionJournee;
use App\Controllers\Gestion\MaquetteController as GestionMaquette;
use App\Controllers\Gestion\MessageController as GestionMessages;
use App\Controllers\Gestion\PersonneController as GestionPersonnes;
use App\Controllers\Gestion\RechercheController as GestionRecherche;
use App\Controllers\Gestion\ModeleController as GestionModeles;
use App\Controllers\Gestion\MessageEquipeController as GestionMessagesEquipe;
use App\Controllers\Gestion\RendezVousController as GestionRendezVous;
use App\Controllers\Gestion\TaskController as GestionTaches;
use App\Controllers\Gestion\TutorAccessController as GestionTuteurs;
use App\Controllers\Gestion\ReceptionController as GestionAccueil;
use App\Controllers\Gestion\FinanceController as GestionFinance;
use App\Controllers\Gestion\ProspectController as GestionProspects;
use App\Controllers\Gestion\PilotageController as GestionPilotage;
use App\Controllers\Gestion\DonneesController as GestionDonnees;
use App\Controllers\HomeController;
use App\Controllers\ArticleController;
use App\Controllers\OrientationController;
use App\Controllers\PasswordController;
use App\Controllers\ServiceController;
use App\Controllers\UniversityController;
use App\Models\Article;
use App\Models\Destination;
use App\Models\Sector;
use App\Models\ServiceRequest;
use App\Models\University;

/** @var App\Core\Router $router */

$router->get('/', [HomeController::class, 'index']);

// Destinations : chaque pays garde son adresse WordPress (/aller-au-canada/…).
$router->get('/destination', [DestinationController::class, 'index']);
$router->redirect('/destinations', '/destination/');
foreach (Destination::all() as $country) {
    $slug = $country['slug'];
    $router->get('/' . $slug, static fn () => (new DestinationController())->show($slug));
    foreach ($country['aliases'] as $alias) {
        $router->redirect('/' . $alias, '/' . $slug . '/');
    }
}

// Universités du Sénégal : chaque fiche garde son adresse WordPress (/ucad/…).
$router->get('/universites', [UniversityController::class, 'index']);
foreach (University::all() as $university) {
    $universitySlug = $university['slug'];
    $router->get('/' . $universitySlug, static fn () => (new UniversityController())->show($universitySlug));
}

// Orientation : métiers, secteurs, écoles (voir docs/metiers-orientation.md).
$router->get('/apres-le-bac', [OrientationController::class, 'afterBac']);
$router->get('/metiers', [ArticleController::class, 'careers']);
$router->get('/formations-profesionnelles', [ArticleController::class, 'schools']);
$router->get('/blog', [ArticleController::class, 'blog']);
foreach (Sector::all() as $sector) {
    $sectorSlug = $sector['slug'];
    $router->get('/' . $sectorSlug, static fn () => (new ArticleController())->sector($sectorSlug));
}
foreach (array_keys(OrientationController::PAGES) as $orientationSlug) {
    $router->get('/' . $orientationSlug, static fn () => (new OrientationController())->show($orientationSlug));
}

// Portail : inscription, connexion, espace client (voir docs/vision-plateforme.md).
$router->get('/inscription', [AuthController::class, 'showRegister'], ['guest']);
$router->post('/inscription', [AuthController::class, 'register'], ['guest']);
$router->get('/connexion', [AuthController::class, 'showLogin'], ['guest']);
$router->post('/connexion', [AuthController::class, 'login'], ['guest']);
$router->post('/deconnexion', [AuthController::class, 'logout'], ['auth']);
// Mot de passe oublié (voir docs/mot-de-passe-oublie.md).
$router->get('/mot-de-passe-oublie', [PasswordController::class, 'showForgot'], ['guest']);
$router->post('/mot-de-passe-oublie', [PasswordController::class, 'sendLink'], ['guest']);
$router->get('/reinitialiser-mot-de-passe', [PasswordController::class, 'showReset'], ['guest']);
$router->post('/reinitialiser-mot-de-passe', [PasswordController::class, 'reset'], ['guest']);
$router->get('/reinitialiser-mot-de-passe/{token}', [PasswordController::class, 'open'], ['guest']);
$router->get('/mon-espace', [ClientDashboard::class, 'index'], ['auth']);
// L'ancienne page WordPress « Suivre son dossier » mène désormais à l'espace client.
$router->redirect('/suivre-son-dossier', '/mon-espace/');

// Demandes et rendez-vous (voir docs/demandes-rendez-vous.md). Pages de service aux adresses WordPress,
// formulaire réservé aux comptes connectés ; rendez-vous ouvert à tous.
foreach (ServiceRequest::services() as $service) {
    $serviceSlug = $service['slug'];
    $router->get('/' . $serviceSlug, static fn () => (new ServiceController())->show($serviceSlug));
    $router->post('/' . $serviceSlug, static fn () => (new ServiceController())->store($serviceSlug), ['auth']);
}
$router->get('/rendez_vous_orientation', [AppointmentController::class, 'create']);
$router->post('/rendez_vous_orientation', [AppointmentController::class, 'store']);
$router->get('/rendez_vous_orientation/confirmation', [AppointmentController::class, 'confirmation']);
$router->get('/rendez_vous_orientation/creneaux', [AppointmentController::class, 'slots']);
$router->get('/rendez_vous_orientation/annuler', [AppointmentController::class, 'guestCancellation']);
$router->post('/rendez_vous_orientation/annuler', [AppointmentController::class, 'confirmGuestCancellation']);
$router->post('/mon-espace/rendez-vous/{reference}/annuler', [AppointmentController::class, 'cancel'], ['auth']);
$router->get('/mon-espace/demandes', [ClientRequests::class, 'index'], ['auth']);
$router->get('/mon-espace/demandes/{reference}', [ClientRequests::class, 'show'], ['auth']);
$router->post('/mon-espace/demandes/{reference}', [ClientRequests::class, 'reply'], ['auth']);
$router->post('/mon-espace/demandes/{reference}/pieces-attendues/{id}', [ClientRequests::class, 'depositRequirement'], ['auth']);
$router->get('/mon-espace/demandes/{reference}/documents/{id}', [ClientRequests::class, 'document'], ['auth']);
$router->post('/mon-espace/demandes/{reference}/tuteurs/{id}/revoquer', [ClientRequests::class, 'revokeTutor'], ['auth']);
$router->get('/mon-espace/demandes/{reference}/recus/{number}', [ClientRequests::class, 'financeReceipt'], ['auth']);

// Offres et activités (voir docs/offres-activites.md).
foreach (ActivityController::pages() as $activity) {
    $activitySlug = $activity['slug'];
    $router->get('/' . $activitySlug, static fn () => (new ActivityController())->show($activitySlug));
}

// Pages de l'agence (voir docs/pages-agence.md). Les pages WordPress vides redirigent vers leur équivalent.
$router->get('/about-us', [AgencyController::class, 'about']);
$router->get('/contact', [AgencyController::class, 'contact']);
$router->post('/contact', [AgencyController::class, 'sendMessage']);
// Maquette navigable de l'espace équipe : locale uniquement (GESTION_MAQUETTE=1), docs/gestion-phase-1.md
$router->get('/gestion/maquette', [GestionMaquette::class, 'journee']);
$router->get('/gestion/maquette/dossiers', [GestionMaquette::class, 'dossiers']);
$router->get('/gestion/maquette/dossiers/{reference}', [GestionMaquette::class, 'dossier']);
$router->get('/gestion/maquette/prospects', [GestionMaquette::class, 'prospects']);
$router->get('/gestion/maquette/client', [GestionMaquette::class, 'client']);
$router->get('/gestion/maquette/agenda', [GestionMaquette::class, 'agenda']);
$router->get('/gestion/maquette/personnes', [GestionMaquette::class, 'personne']);
$router->get('/gestion/maquette/rendez-vous', [GestionMaquette::class, 'reservation']);
// Assistant IA : réponse en flux (docs/assistant-ia.md)
$router->post('/assistant', [AssistantController::class, 'message']);
$router->get('/faq', [AgencyController::class, 'faq']);
$router->get('/nos-regles-et-valeurs', [AgencyController::class, 'rules']);
$router->get('/terms-conditions', [AgencyController::class, 'privacy']);
$router->get('/temoignage', [AgencyController::class, 'testimonials']);
$router->get('/avis-etudiants', static fn () => (new AgencyController())->testimonials(true));
$router->redirect('/partir-a-letranger', '/destination/');
$router->redirect('/journal-ecoles', '/formations-profesionnelles/');

// Espace équipe (docs/gestion-phase-1.md, 1B) : chaque module est réservé aux postes de la matrice (middleware « acces »),
// et chaque dossier ou pièce est revérifié par le filtre de périmètre dans le contrôleur.
$router->get('/gestion', [GestionJournee::class, 'index'], ['auth', 'acces:gestion']);
$router->post('/gestion/taches', [GestionTaches::class, 'store'], ['auth', 'acces:gestion']);
$router->post('/gestion/taches/{id}/etat', [GestionTaches::class, 'etat'], ['auth', 'acces:gestion']);
$dossiers = ['auth', 'acces:dossiers'];
$router->get('/gestion/dossiers', [GestionDossiers::class, 'index'], $dossiers);
$router->get('/gestion/dossiers/{reference}', [GestionDossiers::class, 'show'], $dossiers);
$router->post('/gestion/dossiers/{reference}', [GestionDossiers::class, 'update'], $dossiers);
$router->post('/gestion/dossiers/{reference}/attribution', [GestionDossiers::class, 'assign'], $dossiers);
$router->get('/gestion/dossiers/{reference}/pieces/{id}', [GestionDossiers::class, 'document'], $dossiers);
// 2C (N10) : cocher une étape (traitement affecté ou administrateur, contrôlé dans l'action)
$router->post('/gestion/dossiers/{reference}/etapes/{id}', [GestionDossiers::class, 'etape'], $dossiers);
$router->post('/gestion/dossiers/{reference}/pieces-attendues', [GestionDossiers::class, 'addRequirement'], $dossiers);
$router->get('/gestion/finance', [GestionFinance::class, 'index'], ['auth', 'acces:finance']);
$router->get('/gestion/dossiers/{reference}/finance', [GestionFinance::class, 'show'], ['auth', 'acces:finance']);
$router->post('/gestion/dossiers/{reference}/finance', [GestionFinance::class, 'save'], ['auth', 'acces:finance']);
$router->get('/gestion/dossiers/{reference}/finance/recus/{number}', [GestionFinance::class, 'receipt'], ['auth', 'acces:finance']);
$router->post('/gestion/dossiers/{reference}/suivi', [GestionDossiers::class, 'followUp'], $dossiers);
$router->post('/gestion/dossiers/{reference}/pieces-attendues/{id}', [GestionDossiers::class, 'reviewRequirement'], $dossiers);
$router->get('/gestion/dossiers/{reference}/tuteurs', [GestionTuteurs::class, 'show'], $dossiers);
$router->post('/gestion/dossiers/{reference}/tuteurs', [GestionTuteurs::class, 'grant'], $dossiers);
$router->post('/gestion/dossiers/{reference}/tuteurs/{id}/revoquer', [GestionTuteurs::class, 'revoke'], $dossiers);
// Personnes et recherche globale (phase 2, 2A) : périmètre d'Access::personnesSql(), refus journalisés
$personnes = ['auth', 'acces:personnes'];
$router->get('/gestion/personnes', [GestionPersonnes::class, 'index'], $personnes);
$router->get('/gestion/accueil', [GestionAccueil::class, 'index'], ['auth', 'acces:accueil-agence']);
$router->post('/gestion/accueil', [GestionAccueil::class, 'register'], ['auth', 'acces:accueil-agence']);
$router->post('/gestion/accueil/{id}', [GestionAccueil::class, 'update'], ['auth', 'acces:accueil-agence']);
$router->get('/gestion/doublons', [GestionAccueil::class, 'duplicates'], ['auth', 'acces:doublons']);
$router->post('/gestion/doublons', [GestionAccueil::class, 'merge'], ['auth', 'acces:doublons']);
$router->get('/gestion/personnes/nouvelle', [GestionPersonnes::class, 'create'], $personnes);
$router->post('/gestion/personnes/nouvelle', [GestionPersonnes::class, 'store'], $personnes);
$router->get('/gestion/personnes/{id}', [GestionPersonnes::class, 'show'], $personnes);
// 2C : note sur la personne (aucune route de modification ni de suppression, N4) et ouverture d'un dossier (E7)
$router->post('/gestion/personnes/{id}/notes', [GestionPersonnes::class, 'noter'], $personnes);
$router->post('/gestion/personnes/{id}/dossier', [GestionPersonnes::class, 'ouvrirDossier'], $personnes);
$router->get('/gestion/recherche', [GestionRecherche::class, 'index'], ['auth', 'acces:gestion']);
$prospects = ['auth', 'acces:prospects'];
$router->get('/gestion/prospects', [GestionProspects::class, 'index'], $prospects);
$router->post('/gestion/prospects', [GestionProspects::class, 'store'], $prospects);
$router->post('/gestion/prospects/attribuer', [GestionProspects::class, 'assign'], $prospects);
$router->get('/gestion/prospects/campagnes', [GestionProspects::class, 'catalog'], $prospects);
$router->post('/gestion/prospects/campagnes', [GestionProspects::class, 'createCatalog'], $prospects);
$router->get('/gestion/prospects/importer', [GestionProspects::class, 'import'], $prospects);
$router->post('/gestion/prospects/importer', [GestionProspects::class, 'preview'], $prospects);
$router->post('/gestion/prospects/importer/confirmer', [GestionProspects::class, 'commitImport'], $prospects);
$router->get('/gestion/prospects/{id}', [GestionProspects::class, 'show'], $prospects);
$router->post('/gestion/prospects/{id}', [GestionProspects::class, 'update'], $prospects);
$router->get('/gestion/rendez-vous', [GestionRendezVous::class, 'index'], ['auth', 'acces:rendez-vous']);
$router->post('/gestion/rendez-vous/disponibilites', [GestionRendezVous::class, 'saveAvailability'], ['auth', 'acces:rendez-vous']);
$router->post('/gestion/rendez-vous/durees', [GestionRendezVous::class, 'saveDuration'], ['auth', 'acces:rendez-vous']);
$router->post('/gestion/rendez-vous/{reference}', [GestionRendezVous::class, 'update'], ['auth', 'acces:rendez-vous']);
// 2C (E5, N3) : compte rendu d'un entretien
$router->get('/gestion/rendez-vous/{reference}/compte-rendu', [GestionRendezVous::class, 'compteRendu'], ['auth', 'acces:rendez-vous']);
$router->post('/gestion/rendez-vous/{reference}/compte-rendu', [GestionRendezVous::class, 'enregistrerCompteRendu'], ['auth', 'acces:rendez-vous']);
$router->get('/gestion/messages', [GestionMessages::class, 'index'], ['auth', 'acces:messages']);
$router->post('/gestion/messages/{id}', [GestionMessages::class, 'update'], ['auth', 'acces:messages']);
$router->get('/gestion/e-mails', [GestionMessages::class, 'mails'], ['auth', 'acces:e-mails']);
$router->post('/gestion/e-mails/{id}', [GestionMessages::class, 'retry'], ['auth', 'acces:e-mails']);
$router->get('/gestion/equipe', [GestionEquipe::class, 'index'], ['auth', 'acces:equipe']);
$router->post('/gestion/equipe', [GestionEquipe::class, 'store'], ['auth', 'acces:equipe']);
$router->post('/gestion/equipe/{id}', [GestionEquipe::class, 'update'], ['auth', 'acces:equipe']);
$router->get('/gestion/journal', [GestionEquipe::class, 'journal'], ['auth', 'acces:journal']);
$router->get('/gestion/assistant', [GestionAssistant::class, 'index'], ['auth', 'acces:assistant']);
$router->post('/gestion/assistant', [GestionAssistant::class, 'save'], ['auth', 'acces:assistant']);
// 2C (E8, E9, N5) : modèles de procédure, administrateur seulement
// Phase 7, lot 7A : messages de l'équipe (liens WhatsApp ou e-mail, journalisés) et leurs modèles
$router->get('/gestion/personnes/{id}/message', [GestionMessagesEquipe::class, 'form'], $personnes);
$router->post('/gestion/personnes/{id}/message', [GestionMessagesEquipe::class, 'ouvrir'], $personnes);
$router->get('/gestion/messages-modeles', [GestionMessagesEquipe::class, 'modeles'], ['auth', 'acces:messages-modeles']);
$router->post('/gestion/messages-modeles', [GestionMessagesEquipe::class, 'enregistrerModele'], ['auth', 'acces:messages-modeles']);
// Phase 7, lot 7B : pilotage et exports CSV journalisés
$router->get('/gestion/pilotage', [GestionPilotage::class, 'index'], ['auth', 'acces:pilotage']);
$router->get('/gestion/exports/{type}', [GestionPilotage::class, 'export'], ['auth', 'acces:exports']);
// Phase 7, lot 7C : données personnelles (export JSON, anonymisation) et durées de conservation, administrateur
$donnees = ['auth', 'acces:donnees-personnelles'];
$router->get('/gestion/personnes/{id}/donnees', [GestionDonnees::class, 'show'], $donnees);
$router->get('/gestion/personnes/{id}/donnees/export', [GestionDonnees::class, 'export'], $donnees);
$router->post('/gestion/personnes/{id}/anonymiser', [GestionDonnees::class, 'anonymiser'], $donnees);
$router->get('/gestion/conservation', [GestionDonnees::class, 'conservation'], ['auth', 'acces:conservation']);
$router->get('/gestion/modeles', [GestionModeles::class, 'index'], ['auth', 'acces:modeles']);
$router->post('/gestion/modeles', [GestionModeles::class, 'store'], ['auth', 'acces:modeles']);
$router->get('/gestion/modeles/nouveau', [GestionModeles::class, 'create'], ['auth', 'acces:modeles']);
$router->get('/gestion/modeles/{id}', [GestionModeles::class, 'show'], ['auth', 'acces:modeles']);
$router->post('/gestion/modeles/{id}', [GestionModeles::class, 'update'], ['auth', 'acces:modeles']);

// Transition /admin/ → /gestion/ (docs/gestion-plan.md §2.3) : GET en 302 temporaire avec les filtres conservés
// (301 après validation de la phase 1) ; POST de compatibilité : même traitement, mêmes droits, même jeton CSRF.
$versGestion = static function (string $to): never {
    $query = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_QUERY);
    header('Location: ' . url($to) . ($query !== '' ? '?' . $query : ''), true, 302);
    exit;
};
$router->get('/admin', static fn () => $versGestion('/gestion/'));
$router->get('/admin/demandes', static fn () => $versGestion('/gestion/dossiers/'));
$router->get('/admin/demandes/{reference}', static fn (string $reference) => $versGestion('/gestion/dossiers/' . rawurlencode($reference) . '/'));
$router->get('/admin/demandes/{reference}/documents/{id}', static fn (string $reference, string $id) => $versGestion('/gestion/dossiers/' . rawurlencode($reference) . '/pieces/' . rawurlencode($id) . '/'));
$router->get('/admin/rendez-vous', static fn () => $versGestion('/gestion/rendez-vous/'));
$router->get('/admin/messages', static fn () => $versGestion('/gestion/messages/'));
$router->get('/admin/e-mails', static fn () => $versGestion('/gestion/e-mails/'));
$router->get('/admin/equipe', static fn () => $versGestion('/gestion/equipe/'));
$router->post('/admin/demandes/{reference}', [GestionDossiers::class, 'update'], $dossiers);
$router->post('/admin/demandes/{reference}/attribution', [GestionDossiers::class, 'assign'], $dossiers);
$router->post('/admin/rendez-vous/{reference}', [GestionRendezVous::class, 'update'], ['auth', 'acces:rendez-vous']);
$router->post('/admin/messages/{id}', [GestionMessages::class, 'update'], ['auth', 'acces:messages']);
$router->post('/admin/e-mails/{id}', [GestionMessages::class, 'retry'], ['auth', 'acces:e-mails']);
$router->post('/admin/equipe', [GestionEquipe::class, 'store'], ['auth', 'acces:equipe']);
$router->post('/admin/equipe/{id}', [GestionEquipe::class, 'update'], ['auth', 'acces:equipe']);

// Articles (fiches métiers, écoles, offres…) à la racine, comme sur WordPress (/medecin/, /unipro-d/).
// Déclarés en dernier : une page existante n'est jamais masquée par un article de même adresse.
// Si la base est indisponible, le reste du site continue de fonctionner sans les articles.
try {
    foreach (Article::slugs() as $articleSlug) {
        if (!$router->has('/' . $articleSlug)) {
            $router->get('/' . $articleSlug, static fn () => (new ArticleController())->show($articleSlug));
        }
    }
} catch (PDOException $exception) {
    error_log('Articles indisponibles : ' . $exception->getMessage());
}
