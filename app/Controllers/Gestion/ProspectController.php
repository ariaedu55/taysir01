<?php

declare(strict_types=1);

namespace App\Controllers\Gestion;

use App\Core\Auth;
use App\Core\Session;
use App\Models\Prospect;
use App\Models\User;
use App\Services\Access;
use App\Services\FormInput;
use App\Services\ProspectImport;
use DomainException;
use OutOfBoundsException;

final class ProspectController extends EspaceController
{
    private const LIST = '/gestion/prospects/';
    private const IMPORT = '/gestion/prospects/importer/';
    private const CATALOG = '/gestion/prospects/campagnes/';

    public function index(): void
    {
        $actor = Auth::user();
        $query = mb_substr(trim($this->filter('q') ?? ''), 0, 80);
        $filter = $this->filter('vue') ?? '';
        $campaign = (int) ($this->filter('campagne') ?? 0);
        $school = (int) ($this->filter('etablissement') ?? 0);
        $level = mb_substr(trim($this->filter('niveau') ?? ''), 0, 80);
        [$rows, $total, $page] = Prospect::search($actor, $query, $filter, $campaign, (int) ($this->filter('page') ?? 1), school: $school, level: $level);
        $manage = Access::gereCampagnes($actor);
        $this->page('prospects', 'prospects', $actor['role'] === 'commerciale' ? 'Mes prospects' : 'Prospects', Prospect::catalog($actor) + [
            'query' => $query, 'filter' => $filter, 'campaign' => $campaign, 'rows' => $rows, 'total' => $total, 'page' => $page,
            'stats' => Prospect::stats($actor), 'manage' => $manage,
            'school' => $school, 'level' => $level,
            'assignees' => $manage ? array_filter(User::staff(true), static fn (array $member): bool => $member['role'] === 'commerciale'
                && ($actor['role'] === 'admin' || (int) $member['agency_id'] === (int) $actor['agency_id'])) : [],
        ]);
    }

    public function show(string $id): void
    {
        $actor = Auth::user();
        $row = ctype_digit($id) ? Prospect::scoped($actor, (int) $id) : null;
        if ($row === null) {
            $this->refuser('prospect', $id, 'hors périmètre');
        }
        $this->page('prospect', 'prospects', $row['first_name'] . ' ' . $row['last_name'], [
            'row' => $row, 'events' => Prospect::events((int) $id), 'appointments' => Prospect::appointments((int) $id),
            'canTrack' => ($actor['role'] === 'admin' || ($actor['role'] === 'commerciale' && (int) $row['assigned_to'] === (int) $actor['id']))
                && $row['state'] !== 'transmis', 'today' => Prospect::today($actor),
        ]);
    }

    public function store(): void
    {
        $actor = Auth::user();
        try {
            $data = [];
            foreach (['first_name', 'last_name', 'phone', 'email', 'level'] as $field) {
                $data[$field] = FormInput::text($field, 191);
            }
            [$ids] = Prospect::collect($actor, self::number('school'), self::number('campaign'), [1 => ProspectImport::validate($data)], false);
            if ($ids === []) {
                throw new DomainException('Un contact correspondant existe déjà. Demandez un rapprochement avant de le recréer.');
            }
        } catch (DomainException $exception) {
            $this->back(['prospection' => $exception->getMessage()], self::LIST);
        }
        Session::flash('_success', 'Prospect enregistré.');
        redirect_to(self::LIST . $ids[0] . '/');
    }

    public function assign(): void
    {
        $actor = $this->manager();
        $selection = $_POST['selection'] ?? [];
        if (!is_array($selection) || count($selection) < 1 || count($selection) > Prospect::PAGE
            || array_filter($selection, static fn ($id): bool => !is_string($id) || !ctype_digit($id))) {
            $this->back(['prospection' => 'Sélectionnez les prospects à attribuer.'], self::LIST);
        }
        try {
            $count = Prospect::assign($actor, array_unique(array_map('intval', $selection)), self::number('assignee'));
        } catch (OutOfBoundsException) {
            $this->refuser('prospect', 'lot', 'sélection hors périmètre');
        } catch (DomainException $exception) {
            $this->back(['prospection' => $exception->getMessage()], self::LIST);
        }
        Session::flash('_success', $count === 0 ? 'L’attribution est déjà enregistrée.' : $count . ' prospect(s) attribué(s).');
        redirect_to(self::LIST);
    }

    public function update(string $id): void
    {
        if (!ctype_digit($id)) {
            $this->abort(404);
        }
        try {
            $action = FormInput::text('action', 20);
            Prospect::update(Auth::user(), (int) $id, $action, [
                'state' => FormInput::text('state', 20), 'follow_up' => FormInput::text('follow_up', 11),
                'note' => FormInput::multiline('note', 1500), 'date' => FormInput::text('date', 11),
                'slot' => FormInput::text('slot', 20), 'mode' => FormInput::text('mode', 30),
            ]);
        } catch (OutOfBoundsException) {
            $this->refuser('prospect', $id, 'suivi hors périmètre');
        } catch (DomainException $exception) {
            $this->back(['prospection' => $exception->getMessage()], self::LIST . $id . '/');
        }
        Session::flash('_success', 'Suivi enregistré.');
        redirect_to(self::LIST . $id . '/');
    }

    public function catalog(): void
    {
        $actor = Auth::user();
        $this->page('prospection-campagnes', 'prospects', 'Établissements et campagnes', Prospect::catalog($actor) + ['manage' => Access::gereCampagnes($actor)]);
    }

    public function createCatalog(): void
    {
        $actor = $this->manager();
        try {
            Prospect::createCatalog($actor, FormInput::text('kind', 12), self::number('agency'), FormInput::text('name', 150),
                FormInput::text('city', 100), FormInput::text('start', 11), FormInput::text('end', 11));
        } catch (DomainException $exception) {
            $this->back(['prospection' => $exception->getMessage()], self::CATALOG);
        }
        Session::flash('_success', 'Entrée enregistrée.');
        redirect_to(self::CATALOG);
    }

    public function import(): void
    {
        $actor = $this->manager();
        header('Cache-Control: private, no-store');
        $preview = Session::get('prospect_import');
        if (!$preview || $preview['actor'] !== (int) $actor['id'] || $preview['expires'] < time()) {
            Session::forget('prospect_import');
            $preview = null;
        }
        $this->page('prospection-import', 'prospects', 'Importer des prospects', Prospect::catalog($actor) + [
            'preview' => $preview, 'report' => Session::get('prospect_import_report'), 'recent' => Prospect::recentImports($actor),
        ]);
    }

    public function preview(): void
    {
        $actor = $this->manager();
        Session::forget('prospect_import');
        $school = self::number('school');
        $campaign = self::number('campaign');
        try {
            Prospect::context($actor, $school, $campaign);
            [$rows, $invalid] = ProspectImport::read($_FILES['list'] ?? []);
            if ($rows === [] && $invalid === []) {
                throw new DomainException('Le fichier ne contient aucune ligne de prospect.');
            }
        } catch (DomainException $exception) {
            $this->back(['prospection' => $exception->getMessage()], self::IMPORT);
        }
        $seen = $duplicates = [];
        foreach ($rows as $line => $row) {
            $phone = ltrim($row['phone'], '+');
            $identity = mb_strtolower($row['first_name'] . "\0" . $row['last_name']) . "\0" . $phone;
            if (isset($seen[$identity]) || ($row['email'] !== '' && isset($seen[$row['email']])) || Prospect::duplicate($row)) {
                $duplicates[] = $line;
                continue;
            }
            if ($phone !== '') {
                $seen[$identity] = true;
            }
            if ($row['email'] !== '') {
                $seen[$row['email']] = true;
            }
        }
        Session::set('prospect_import', [
            'actor' => (int) $actor['id'], 'expires' => time() + 1200, 'token' => bin2hex(random_bytes(16)), 'school' => $school,
            'campaign' => $campaign, 'rows' => $rows, 'invalid' => $invalid, 'duplicates' => $duplicates,
        ]);
        redirect_to(self::IMPORT);
    }

    public function commitImport(): void
    {
        $actor = $this->manager();
        $preview = Session::get('prospect_import');
        $token = FormInput::text('token', 33);
        if (!$preview || $preview['actor'] !== (int) $actor['id'] || $preview['expires'] < time() || !hash_equals($preview['token'], $token)) {
            $this->back(['prospection' => 'Ouvrez un nouvel aperçu avant de confirmer l’import.'], self::IMPORT);
        }
        try {
            [$ids, $duplicates] = Prospect::collect($actor, $preview['school'], $preview['campaign'], $preview['rows'], true);
        } catch (DomainException $exception) {
            $this->back(['prospection' => $exception->getMessage()], self::IMPORT);
        }
        Session::forget('prospect_import');
        Session::flash('prospect_import_report', ['added' => count($ids), 'duplicates' => $duplicates, 'invalid' => $preview['invalid']]);
        redirect_to(self::IMPORT);
    }

    private function manager(): array
    {
        $actor = Auth::user();
        if (!Access::gereCampagnes($actor)) {
            $this->refuser('prospection', 'campagnes', 'action réservée à la prospection');
        }
        return $actor;
    }

    private static function number(string $field): int
    {
        $value = FormInput::text($field, 20);
        return ctype_digit($value) ? (int) $value : 0;
    }
}
