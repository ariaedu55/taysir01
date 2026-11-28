<?php

declare(strict_types=1);

namespace App\Controllers\Gestion;

use App\Core\Auth;
use App\Models\Appointment;
use App\Models\Prospect;
use App\Models\ServiceRequest;
use App\Models\Task;
use App\Services\Access;
use App\Services\Coquille;

/** « Ma journée » réelle (/gestion/) : ce qui attend le compte, calculé sur son seul périmètre. */
final class JourneeController extends EspaceController
{
    public function index(): void
    {
        $me = Auth::user();
        $dossiers = Access::peut($me, 'dossiers');
        $affectable = array_intersect(Access::postes($me), array_keys(Access::AFFECTATIONS)) !== [];
        $counts = $dossiers ? ServiceRequest::countsScoped($me) : [];
        $today = new \DateTimeImmutable('today', Appointment::zone(isset($me['agency_id']) ? (int) $me['agency_id'] : null));
        $utc = new \DateTimeZone('UTC');
        $agenda = Access::peut($me, 'rendez-vous');
        $priorite = match ($me['role']) {
            'admin' => ['Pilotage de l’agence', 'Ouvrir le pilotage', 'chart', '/gestion/pilotage/', 'pilotage'],
            'secretaire' => ['Accueil et rendez-vous', 'Ouvrir l’agenda', 'calendar', '/gestion/rendez-vous/', 'rendez-vous'],
            'prospection' => ['Collecte scolaire', 'Attribuer les prospects', 'target', '/gestion/prospects/?vue=libres', 'prospects'],
            'commerciale' => ['Suivi commercial', 'Ouvrir mes relances', 'phone', '/gestion/prospects/?vue=relances', 'prospects'],
            'conseiller' => ['Entretiens et accompagnement', 'Voir mes dossiers', 'compass', '/gestion/dossiers/?vue=mes', 'dossiers'],
            'orientation' => ['Projets d’études', 'Voir les dossiers à orienter', 'route', '/gestion/dossiers/?vue=a-prendre', 'dossiers'],
            'traitement' => ['Dossiers et pièces à vérifier', 'Voir les dossiers à traiter', 'file', '/gestion/dossiers/?vue=a-prendre', 'dossiers'],
            'comptable' => ['Soldes et échéances', 'Ouvrir la finance', 'chart', '/gestion/finance/', 'finance'],
            default => null,
        };
        if ($priorite !== null && !Access::peut($me, $priorite[4])) {
            $priorite = null;
        }

        $this->page('journee', 'gestion', 'Ma journée', [
            'compteurs' => Coquille::compteurs($me),
            'counts' => $counts,
            'dossiers' => $dossiers,
            'affectable' => $affectable,
            'mesDossiers' => $affectable ? ServiceRequest::searchScoped($me, null, null, 'mes', 6) : [],
            'aPrendre' => $affectable ? ServiceRequest::searchScoped($me, null, null, 'a-prendre', 6) : [],
            'recents' => $dossiers && !$affectable ? ServiceRequest::searchScoped($me, 'nouvelle', null, null, 6) : [],
            'rdv' => $agenda ? Appointment::calendar($me, $today->setTimezone($utc)->format('Y-m-d H:i:s'), $today->modify('+1 day')->setTimezone($utc)->format('Y-m-d H:i:s')) : [],
            'agenda' => $agenda,
            'prospects' => in_array($me['role'], ['prospection', 'commerciale'], true) ? Prospect::stats($me) : null,
            'today' => $today,
            'priorite' => $priorite,
            'tasks' => Task::forStaff($me),
            'deadlines' => \App\Models\RequestFollowUp::upcoming($me, $today->modify('+7 days')->format('Y-m-d')),
        ]);
    }
}
