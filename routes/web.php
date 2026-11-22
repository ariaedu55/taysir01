<?php

declare(strict_types=1);

use App\Controllers\ActivityController;
use App\Controllers\AgencyController;
use App\Controllers\Admin\MessageController as AdminMessages;
use App\Controllers\Admin\MailController as AdminMails;
use App\Controllers\Admin\TeamController as AdminTeam;
use App\Controllers\Admin\DashboardController as AdminDashboard;
use App\Controllers\Admin\RequestController as AdminRequests;
use App\Controllers\AppointmentController;
use App\Controllers\AuthController;
use App\Controllers\Client\DashboardController as ClientDashboard;
use App\Controllers\Client\RequestController as ClientRequests;
use App\Controllers\DestinationController;
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
$router->get('/mon-espace/demandes', [ClientRequests::class, 'index'], ['auth']);
$router->get('/mon-espace/demandes/{reference}', [ClientRequests::class, 'show'], ['auth']);
$router->post('/mon-espace/demandes/{reference}', [ClientRequests::class, 'reply'], ['auth']);
$router->get('/mon-espace/demandes/{reference}/documents/{id}', [ClientRequests::class, 'document'], ['auth']);

// Offres et activités (voir docs/offres-activites.md).
foreach (ActivityController::pages() as $activity) {
    $activitySlug = $activity['slug'];
    $router->get('/' . $activitySlug, static fn () => (new ActivityController())->show($activitySlug));
}

// Pages de l'agence (voir docs/pages-agence.md). Les pages WordPress vides redirigent vers leur équivalent.
$router->get('/about-us', [AgencyController::class, 'about']);
$router->get('/contact', [AgencyController::class, 'contact']);
$router->post('/contact', [AgencyController::class, 'sendMessage']);
$router->get('/faq', [AgencyController::class, 'faq']);
$router->get('/nos-regles-et-valeurs', [AgencyController::class, 'rules']);
$router->get('/terms-conditions', [AgencyController::class, 'privacy']);
$router->get('/temoignage', [AgencyController::class, 'testimonials']);
$router->get('/avis-etudiants', static fn () => (new AgencyController())->testimonials(true));
$router->redirect('/partir-a-letranger', '/destination/');
$router->redirect('/journal-ecoles', '/formations-profesionnelles/');

// Back-office : conseillers et administrateurs.
$staff = ['auth', 'role:admin,conseiller'];
$router->get('/admin', [AdminDashboard::class, 'index'], $staff);
$router->get('/admin/demandes', [AdminRequests::class, 'index'], $staff);
$router->get('/admin/demandes/{reference}', [AdminRequests::class, 'show'], $staff);
$router->post('/admin/demandes/{reference}', [AdminRequests::class, 'update'], $staff);
$router->post('/admin/demandes/{reference}/attribution', [AdminRequests::class, 'assign'], $staff);
$router->get('/admin/demandes/{reference}/documents/{id}', [AdminRequests::class, 'document'], $staff);
$router->get('/admin/rendez-vous', [AdminRequests::class, 'appointments'], $staff);
$router->post('/admin/rendez-vous/{reference}', [AdminRequests::class, 'updateAppointment'], $staff);
$router->get('/admin/messages', [AdminMessages::class, 'index'], $staff);
$router->post('/admin/messages/{id}', [AdminMessages::class, 'update'], $staff);
$router->get('/admin/e-mails', [AdminMails::class, 'index'], $staff);
$router->post('/admin/e-mails/{id}', [AdminMails::class, 'retry'], $staff);
// Équipe : administrateurs uniquement (voir docs/comptes-equipe.md).
$router->get('/admin/equipe', [AdminTeam::class, 'index'], ['auth', 'role:admin']);
$router->post('/admin/equipe', [AdminTeam::class, 'store'], ['auth', 'role:admin']);
$router->post('/admin/equipe/{id}', [AdminTeam::class, 'update'], ['auth', 'role:admin']);

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
