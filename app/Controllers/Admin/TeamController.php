<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Session;
use App\Models\PasswordReset;
use App\Models\User;
use App\Services\FormInput;
use App\Services\Notifier;

/**
 * Back-office, administrateurs uniquement : comptes des conseillers et administrateurs.
 * Voir docs/comptes-equipe.md.
 */
final class TeamController extends Controller
{
    public const ROLES = ['conseiller' => 'Conseiller', 'admin' => 'Administrateur'];

    public function index(): void
    {
        $this->render('admin/team', [
            'members' => User::staff(),
            'me' => Auth::user(),
            'roles' => self::ROLES,
            'title' => 'Équipe · Back-office',
            'robots' => 'noindex',
            'styles' => ['css/auth.css', 'css/portal.css'],
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
        if (!isset(self::ROLES[$role])) {
            $errors['role'] = 'Choisissez un rôle.';
        }
        if ($errors !== []) {
            $this->back($errors, '/admin/equipe/#ajouter');
        }

        $me = Auth::user();
        $member = User::createStaff($data, $role, (int) $me['id']);
        $this->invite($member);
        Session::flash('_success', $member['first_name'] . ' ' . $member['last_name'] . ' est ajouté(e) à l’équipe. Une invitation lui est envoyée à ' . $member['email'] . '.');
        redirect_to('/admin/equipe/');
    }

    /** Actions sur un membre : rôle, désactivation, réactivation, renvoi de l'invitation. */
    public function update(string $id): void
    {
        $member = ctype_digit($id) ? User::find((int) $id) : null;
        if ($member === null || !isset(self::ROLES[$member['role']])) {
            $this->abort(404);
        }
        $me = Auth::user();
        $self = (int) $member['id'] === (int) $me['id'];
        $isLastAdmin = $member['role'] === 'admin' && $member['active'] && User::activeAdminCount() <= 1;
        $name = $member['first_name'] . ' ' . $member['last_name'];
        $action = (string) ($_POST['action'] ?? '');

        $error = null;
        $message = null;
        switch ($action) {
            case 'role':
                $role = (string) ($_POST['role'] ?? '');
                if (!isset(self::ROLES[$role]) || $role === $member['role']) {
                    break;
                }
                if ($self) {
                    $error = 'Vous ne pouvez pas changer votre propre rôle.';
                } elseif ($isLastAdmin) {
                    $error = 'Il faut au moins un administrateur actif.';
                } else {
                    User::setRole((int) $member['id'], $role);
                    $message = $name . ' est maintenant ' . strtolower(self::ROLES[$role]) . '.';
                }
                break;
            case 'deactivate':
                if ($self) {
                    $error = 'Vous ne pouvez pas désactiver votre propre compte.';
                } elseif ($isLastAdmin) {
                    $error = 'Il faut au moins un administrateur actif.';
                } else {
                    User::setActive((int) $member['id'], false);
                    $message = 'Le compte de ' . $name . ' est désactivé : il ne peut plus se connecter.';
                }
                break;
            case 'activate':
                User::setActive((int) $member['id'], true);
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
        }
        if ($error !== null) {
            $this->back(['member_' . $member['id'] => $error], '/admin/equipe/');
        }
        if ($message !== null) {
            Session::flash('_success', $message);
        }
        redirect_to('/admin/equipe/');
    }

    private function invite(array $member): void
    {
        $me = Auth::user();
        Notifier::staffInvitation($member, PasswordReset::issue((int) $member['id'], PasswordReset::INVITATION_MINUTES), $me['first_name'] . ' ' . $me['last_name']);
    }
}
