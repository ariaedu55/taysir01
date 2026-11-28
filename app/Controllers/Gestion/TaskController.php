<?php

declare(strict_types=1);

namespace App\Controllers\Gestion;

use App\Core\Auth;
use App\Core\Router;
use App\Core\Session;
use App\Models\Task;
use App\Services\FormInput;
use DomainException;

/** Création et changements d'état des tâches. Le routeur contrôle le CSRF des deux POST. */
final class TaskController extends EspaceController
{
    public function store(): void
    {
        $me = Auth::user();
        $title = FormInput::text('title', Task::TITLE_MAX + 1);
        if ($title === '' || mb_strlen($title) > Task::TITLE_MAX) {
            $this->invalid('Saisissez un intitulé de 190 caractères maximum.');
        }
        $dueDate = $_POST['due_date'] ?? '';
        if ($dueDate !== '' && (!is_string($dueDate) || !preg_match('/\A[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}\z/', $dueDate)
            || FormInput::date($dueDate) === null)) {
            $this->invalid('Saisissez une date d’échéance valide.');
        }

        try {
            $created = Task::create($me, [
                'title' => $title,
                'due_date' => $dueDate === '' ? null : $dueDate,
                'person_id' => $this->formId('person_id', 'Choisissez une personne valide.'),
                'request_id' => $this->formId('request_id', 'Choisissez un dossier valide.'),
                'assigned_to' => $this->formId('assigned_to', 'Choisissez un responsable valide.') ?? (int) $me['id'],
                'form_token' => $_POST['form_token'] ?? null,
            ]);
        } catch (DomainException) {
            $this->abort(403);
        }
        Session::flash('_success', $created ? 'Tâche créée.' : 'Tâche déjà enregistrée.');
        redirect_to($this->formReturn());
    }

    public function etat(string $id): void
    {
        $taskId = self::positiveId($id);
        if ($taskId === null) {
            $this->abort(404);
        }
        $done = $_POST['done'] ?? null;
        if (!in_array($done, ['0', '1'], true)) {
            $this->invalid('Choisissez un état valide pour la tâche.');
        }

        try {
            $changed = Task::setDone(Auth::user(), $taskId, $done === '1');
        } catch (DomainException) {
            $this->abort(403);
        }
        if ($changed) {
            Session::flash('_success', $done === '1' ? 'Tâche clôturée.' : 'Tâche rouverte.');
        }
        redirect_to('/gestion/');
    }

    private function formId(string $field, string $error): ?int
    {
        $raw = $_POST[$field] ?? '';
        if ($raw === '') {
            return null;
        }
        $id = self::positiveId($raw);
        if ($id === null) {
            $this->invalid($error);
        }

        return $id;
    }

    private static function positiveId(mixed $raw): ?int
    {
        if (!is_string($raw) || !ctype_digit($raw)) {
            return null;
        }
        $id = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $id === false ? null : $id;
    }

    private function invalid(string $message): never
    {
        $return = $this->formReturn();
        if ($return === '/gestion/') {
            Session::flash('_notice', $message);
        } else {
            Session::flash('_errors', ['task' => $message]);
        }
        redirect_to($return);
    }

    private function formReturn(): string
    {
        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        $host = parse_url($referer, PHP_URL_HOST);
        $path = (string) parse_url($referer, PHP_URL_PATH);
        $base = Router::basePath();
        if ($host === null || $host !== parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST)
            || !str_starts_with($path, $base . '/gestion/')) {
            return '/gestion/';
        }

        $route = substr($path, strlen($base));

        return preg_match('~\A/gestion/(?:personnes/[0-9]+|dossiers/[a-zA-Z0-9-]+|rendez-vous/[a-zA-Z0-9-]+/compte-rendu)/\z~', $route)
            ? $route
            : '/gestion/';
    }
}
