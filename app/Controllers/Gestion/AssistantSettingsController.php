<?php

declare(strict_types=1);

namespace App\Controllers\Gestion;

use App\Core\Session;
use App\Services\Assistant\Settings;
use App\Services\Audit;
use RuntimeException;

final class AssistantSettingsController extends EspaceController
{
    private const PATH = '/gestion/assistant/';

    public function index(): void
    {
        header('Cache-Control: private, no-store');
        $this->page('assistant-settings', 'assistant', 'Assistant IA', [
            'enabled' => config('assistant.enabled', false),
            'mock' => config('assistant.mock', false),
            'keySource' => config('assistant.key_source'),
        ]);
    }

    public function save(): void
    {
        header('Cache-Control: private, no-store');
        $action = $_POST['action'] ?? null;
        if ($action !== 'save' && $action !== 'disable') {
            $this->abort(400);
        }

        $key = null;
        if ($action === 'save') {
            $submitted = $_POST['api_key'] ?? null;
            $key = is_string($submitted) ? trim($submitted) : '';
            if (!Settings::validKey($key)) {
                Session::flash('_assistant_error', 'Saisissez une clé DeepSeek valide, sans espace ni saut de ligne.');
                redirect_to(self::PATH);
            }
        }

        try {
            Settings::save($key);
        } catch (RuntimeException) {
            Session::flash('_assistant_error', 'Le stockage privé est indisponible. La modification n’a pas été enregistrée.');
            redirect_to(self::PATH);
        }

        Audit::log('assistant_parametres', 'assistant', 'deepseek', ['action' => $key === null ? 'desactivation' : 'enregistrement']);
        Session::flash('_success', $key === null ? 'Assistant désactivé.' : 'Clé DeepSeek enregistrée.');
        redirect_to(self::PATH);
    }
}
