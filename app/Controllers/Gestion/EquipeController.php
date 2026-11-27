<?php

declare(strict_types=1);

namespace App\Controllers\Gestion;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Session;
use App\Models\Assignment;
use App\Models\PasswordReset;
use App\Models\User;
use App\Services\Access;
use App\Services\Audit;
use App\Services\FormInput;
use App\Services\Notifier;

/**
 * Équipe (/gestion/equipe/, administrateur) : 8 postes, un poste par personne, missions nominatives tracées,
 * invitation, désactivation (jamais de suppression). Journal d'audit (/gestion/journal/).
 * Voir docs/gestion-plan.md §3.1 et §3.6, docs/comptes-equipe.md.
 */
final class EquipeController extends EspaceController
{
    public function index(): void
    {
        $members = User::staff();
        $missions = [];
        $rows = Database::connection()->query(
            'SELECT m.*, g.first_name AS by_first_name FROM user_missions m JOIN users g ON g.id = m.granted_by WHERE m.revoked_at IS NULL'
        )->fetchAll();
        foreach ($rows as $row) {
            $missions[(int) $row['user_id']][] = $row;
        }

        $this->page('equipe', 'equipe', 'Équipe', [
            'members' => $members,
            'missions' => $missions,
            'postes' => Access::POSTES,
            'missionLabels' => Access::MISSIONS,
        ]);
    }

    public function store(): void
    {
        $data = [
            'first_name' => FormInput::text('first_name', 80),
            'last_name' => FormInput::text('last_name', 80),
            'email' => User::normalizeEmail(FormInput::text('email', 190)),
            'phone' => FormInput::phone('phone'),
        ];
        $role = FormInput::text('role', 20);
        $errors = [];
        if ($data['first_name'] === '') {
            $errors['first_name'] = 'Indiquez le prénom.';
        }
        if ($data['last_name'] === '') {
            $errors['last_name'] = 'Indiquez le nom.';
        }
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Indiquez une adresse e-mail valide.';
        } elseif (User::emailExists($data['email'])) {
            $errors['email'] = 'Un compte existe déjà avec cette adresse (peut-être un compte client).';
        }
        if (!isset(Access::POSTES[$role])) {
            $errors['role'] = 'Choisissez un poste.';
        }
        if ($errors !== []) {
            $this->back($errors, '/gestion/equipe/#ajouter');
        }

        $member = User::createStaff($data, $role, (int) Auth::id());
        $this->invite($member);
        Audit::log('membre_invite', 'compte', (string) $member['id'], ['poste' => $role]);
        Session::flash('_success', $member['first_name'] . ' ' . $member['last_name'] . ' est ajouté(e) à l’équipe (' . Access::libelle($role) . '). Une invitation part à ' . $member['email'] . '.');
        redirect_to('/gestion/equipe/');
    }

    /** Poste, désactivation, réactivation, invitation, missions. */
    public function update(string $id): void
    {
        $member = ctype_digit($id) ? User::find((int) $id) : null;
        if ($member === null || !isset(Access::POSTES[$member['role']])) {
            $this->abort(404);
        }
        $me = Auth::user();
        $self = (int) $member['id'] === (int) $me['id'];
        $isLastAdmin = $member['role'] === 'admin' && $member['active'] && User::activeAdminCount() <= 1;
        $name = $member['first_name'] . ' ' . $member['last_name'];
        $ref = (string) $member['id'];
        $error = null;
        $message = null;

        switch ((string) ($_POST['action'] ?? '')) {
            case 'role':
                $role = (string) ($_POST['role'] ?? '');
                if (!isset(Access::POSTES[$role]) || $role === $member['role']) {
                    break;
                }
                if ($self) {
                    $error = 'Vous ne pouvez pas changer votre propre poste.';
                } elseif ($isLastAdmin) {
                    $error = 'Il faut au moins un administrateur actif.';
                } else {
                    $avant = Access::postes($member);
                    User::setRole((int) $member['id'], $role);
                    Access::oublier();
                    // Les dossiers des postes perdus retournent dans leur file (revue 1B, point 2)
                    $perdus = array_values(array_diff($avant, Access::postes(User::find((int) $member['id']))));
                    $remis = Assignment::remettreEnFile((int) $member['id'], (int) $me['id'], $perdus);
                    Audit::log('poste_change', 'compte', $ref, ['de' => $member['role'], 'vers' => $role, 'dossiers_remis' => count($remis)]);
                    $message = $name . ' est maintenant ' . mb_strtolower(Access::libelle($role)) . '.' . self::remis($remis);
                }
                break;
            case 'deactivate':
                if ($self) {
                    $error = 'Vous ne pouvez pas désactiver votre propre compte.';
                } elseif ($isLastAdmin) {
                    $error = 'Il faut au moins un administrateur actif.';
                } else {
                    User::setActive((int) $member['id'], false);
                    // Ses dossiers en cours retournent dans la file de leur poste (revue 1B, point 2)
                    $remis = Assignment::remettreEnFile((int) $member['id'], (int) $me['id']);
                    Audit::log('compte_desactive', 'compte', $ref, ['dossiers_remis' => count($remis)]);
                    $message = 'Le compte de ' . $name . ' est désactivé : il ne peut plus se connecter.' . self::remis($remis);
                }
                break;
            case 'activate':
                User::setActive((int) $member['id'], true);
                Audit::log('compte_reactive', 'compte', $ref);
                $message = 'Le compte de ' . $name . ' est réactivé.';
                break;
            case 'invite':
                if (!$member['active']) {
                    $error = 'Réactivez le compte avant de renvoyer l’invitation.';
                } else {
                    $this->invite($member);
                    $message = 'Nouvelle invitation envoyée à ' . $member['email'] . ' (l’ancien lien ne fonctionne plus).';
                }
                break;
            case 'mission':
                $mission = (string) ($_POST['mission'] ?? '');
                if (!isset(Access::MISSIONS[$mission])) {
                    $error = 'Choisissez une mission.';
                    break;
                }
                $db = Database::connection();
                $active = $db->prepare('SELECT id FROM user_missions WHERE user_id = ? AND mission = ? AND revoked_at IS NULL');
                $active->execute([$member['id'], $mission]);
                $missionId = $active->fetchColumn();
                if ($missionId) {
                    $avant = Access::postes($member);
                    $db->prepare('UPDATE user_missions SET revoked_at = NOW(), revoked_by = ? WHERE id = ?')->execute([$me['id'], $missionId]);
                    Access::oublier();
                    $remis = Assignment::remettreEnFile((int) $member['id'], (int) $me['id'], array_values(array_diff($avant, Access::postes($member))));
                    Audit::log('mission_retiree', 'compte', $ref, ['mission' => $mission, 'dossiers_remis' => count($remis)]);
                    $message = 'Mission « ' . Access::MISSIONS[$mission][0] . ' » retirée à ' . $name . '.' . self::remis($remis);
                } else {
                    $db->prepare('INSERT INTO user_missions (user_id, mission, granted_by) VALUES (?, ?, ?)')->execute([$member['id'], $mission, $me['id']]);
                    Audit::log('mission_attribuee', 'compte', $ref, ['mission' => $mission]);
                    $message = 'Mission « ' . Access::MISSIONS[$mission][0] . ' » attribuée à ' . $name . ', datée et inscrite au journal.';
                }
                Access::oublier();
                break;
        }
        if ($error !== null) {
            $this->back(['member_' . $member['id'] => $error], '/gestion/equipe/');
        }
        if ($message !== null) {
            Session::flash('_success', $message);
        }
        redirect_to('/gestion/equipe/');
    }

    public function journal(): void
    {
        $action = $this->filter('action');

        $this->page('journal', 'journal', 'Journal d’audit', [
            'lignes' => Audit::latest($action),
            'action' => $action,
            'actions' => Audit::ACTIONS,
        ]);
    }

    /** Phrase ajoutée au message de confirmation quand des dossiers retournent dans la file. */
    private static function remis(array $references): string
    {
        $n = count($references);

        return $n === 0 ? '' : ' ' . $n . ' dossier' . ($n > 1 ? 's en cours retournent' : ' en cours retourne') . ' dans la file : ' . implode(', ', array_slice($references, 0, 5)) . ($n > 5 ? '…' : '') . '.';
    }

    private function invite(array $member): void
    {
        $me = Auth::user();
        Notifier::staffInvitation($member, PasswordReset::issue((int) $member['id'], PasswordReset::INVITATION_MINUTES), $me['first_name'] . ' ' . $me['last_name']);
    }
}
