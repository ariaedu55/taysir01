<?php

declare(strict_types=1);

namespace App\Controllers\Gestion;

use App\Core\Auth;
use App\Models\Appointment;
use App\Models\ServiceRequest;
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

        $this->page('journee', 'gestion', 'Ma journée', [
            'compteurs' => Coquille::compteurs($me),
            'counts' => $counts,
            'dossiers' => $dossiers,
            'affectable' => $affectable,
            'mesDossiers' => $affectable ? ServiceRequest::searchScoped($me, null, null, 'mes', 6) : [],
            'aPrendre' => $affectable ? ServiceRequest::searchScoped($me, null, null, 'a-prendre', 6) : [],
            'recents' => $dossiers && !$affectable ? ServiceRequest::searchScoped($me, 'nouvelle', null, null, 6) : [],
            'rdv' => Access::peut($me, 'rendez-vous') ? array_slice(Appointment::search('demande'), 0, 5) : [],
        ]);
    }
}
