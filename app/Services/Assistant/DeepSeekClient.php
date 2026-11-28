<?php

declare(strict_types=1);

namespace App\Services\Assistant;

use RuntimeException;

/**
 * Appel en flux à l'API DeepSeek (format OpenAI), docs/assistant-ia.md.
 *
 * La recherche en ligne est faite par DeepSeek lui-même (outils serveur deepseek_web_search et deepseek_read_url) :
 * le modèle décide d'aller sur internet ; en mode réflexion, la recherche devient approfondie.
 * Le flux est analysé au fil de l'eau et transmis par rappels : texte, statut (recherche, réflexion), sources.
 */
final class DeepSeekClient
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        private readonly string $endpoint,
        private readonly int $timeout,
    ) {
    }

    /**
     * @param list<array{role: string, content: string}> $messages
     * @param callable(string): void              $onText   morceau de réponse
     * @param callable(string, string): void      $onStatus type (search, read, thinking) et détail
     * @param callable(array{title: string, url: string}): void $onSource source web citée
     * @return array{usage: ?array, finish: ?string}
     */
    public function stream(array $messages, bool $deep, int $maxTokens, callable $onText, callable $onStatus, callable $onSource): array
    {
        $body = [
            'model' => $this->model,
            'messages' => $messages,
            'stream' => true,
            'stream_options' => ['include_usage' => true],
            'max_tokens' => $maxTokens,
            'tools' => [['type' => 'deepseek_web_search'], ['type' => 'deepseek_read_url']],
            'thinking' => $deep ? ['type' => 'enabled'] : ['type' => 'disabled'],
        ];
        if ($deep) {
            $body['reasoning_effort'] = 'high';
        } else {
            $body['temperature'] = 0.4;
        }

        $buffer = '';
        $usage = null;
        $finish = null;
        $errorBody = '';
        $thinkingSignalled = false;
        $seenTools = [];
        $streamError = null;
        $receivedText = false;

        $handle = function (array $chunk) use (&$usage, &$finish, &$thinkingSignalled, &$seenTools, &$streamError, &$receivedText, $onText, $onStatus, $onSource): void {
            if (isset($chunk['error'])) {
                $streamError = 'DeepSeek a interrompu la génération.';
            }
            if (!empty($chunk['usage'])) {
                $usage = $chunk['usage'];
            }
            $choice = $chunk['choices'][0] ?? null;
            if (!$choice) {
                return;
            }
            $finish = $choice['finish_reason'] ?? $finish;
            $delta = $choice['delta'] ?? [];
            if (($delta['reasoning_content'] ?? '') !== '' && !$thinkingSignalled) {
                $thinkingSignalled = true;
                $onStatus('thinking', '');
            }
            // Recherches et lectures de pages faites par DeepSeek (outils serveur)
            $calls = $delta['tool_calls'] ?? [];
            foreach ($delta['server_tool_calls'] ?? [] as $step) {
                $calls = array_merge($calls, $step['calls'] ?? []);
            }
            $event = $choice['server_tool_event'] ?? [];
            if (isset($event['call'])) {
                $calls[] = $event['call'];
            }
            foreach ($calls as $call) {
                $name = $call['type'] ?? ($call['function']['name'] ?? $call['name'] ?? '');
                $args = $call['action'] ?? $call['function']['arguments'] ?? $call['arguments'] ?? $call['input'] ?? null;
                $args = is_string($args) ? (json_decode($args, true) ?? []) : (array) $args;
                $detail = (string) ($args['query'] ?? $args['url'] ?? $args['q'] ?? implode(' ; ', $args['queries'] ?? []));
                $action = $args['type'] ?? '';
                $isRead = in_array($action, ['open_page', 'find_in_page', 'read_link'], true) || str_contains($name, 'read');
                $signature = $name . '|' . $detail;
                if (isset($seenTools[$signature]) || ($detail === '' && isset($seenTools[$name . '|']))) {
                    continue;
                }
                $seenTools[$signature] = true;
                $onStatus($isRead ? 'read' : 'search', $detail);
            }
            foreach (array_merge($delta['annotations'] ?? [], $choice['message']['annotations'] ?? []) as $annotation) {
                $citation = $annotation['url_citation'] ?? $annotation;
                if (!empty($citation['url'])) {
                    $onSource(['title' => (string) ($citation['title'] ?? $citation['url']), 'url' => (string) $citation['url']]);
                }
            }
            if (($delta['content'] ?? '') !== '') {
                $receivedText = true;
                $onText($delta['content']);
            }
        };

        $ch = curl_init($this->endpoint);
        $options = [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: text/event-stream', 'Authorization: Bearer ' . $this->apiKey],
            CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR),
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_WRITEFUNCTION => function ($ch, string $data) use (&$buffer, &$errorBody, $handle): int {
                // Visiteur parti : on coupe l'appel pour ne pas consommer de jetons pour rien.
                if (connection_aborted()) {
                    return -1;
                }
                if (curl_getinfo($ch, CURLINFO_RESPONSE_CODE) >= 400) {
                    $errorBody .= $data;

                    return strlen($data);
                }
                $buffer .= $data;
                while (($end = strpos($buffer, "\n")) !== false) {
                    $line = trim(substr($buffer, 0, $end));
                    $buffer = substr($buffer, $end + 1);
                    if (!str_starts_with($line, 'data:')) {
                        continue; // lignes vides et « : keep-alive »
                    }
                    $payload = trim(substr($line, 5));
                    if ($payload === '[DONE]') {
                        continue;
                    }
                    $chunk = json_decode($payload, true);
                    if (is_array($chunk)) {
                        $handle($chunk);
                    }
                }

                return strlen($data);
            },
        ];
        // Windows (développement sous WAMP) : magasin de certificats du système.
        if (PHP_OS_FAMILY === 'Windows' && defined('CURLSSLOPT_NATIVE_CA')) {
            $options[CURLOPT_SSL_OPTIONS] = CURLSSLOPT_NATIVE_CA;
        }
        curl_setopt_array($ch, $options);
        $ok = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($ch);
        $aborted = connection_aborted();

        if ($aborted) {
            return ['usage' => $usage, 'finish' => 'aborted'];
        }
        if ($status >= 400) {
            $message = json_decode($errorBody, true)['error']['message'] ?? 'erreur inconnue';
            throw new RuntimeException('DeepSeek HTTP ' . $status . ' : ' . $message, $status);
        }
        if ($ok === false) {
            throw new RuntimeException('DeepSeek injoignable : ' . $curlError, 0);
        }
        if ($streamError !== null || !$receivedText || $finish === null) {
            throw new RuntimeException($streamError ?? 'Réponse DeepSeek vide ou interrompue.');
        }
        if ($finish === 'length') {
            $onText("\n\nLa réponse a atteint sa limite de longueur. Vous pouvez demander de préciser une étape.");
        }

        return ['usage' => $usage, 'finish' => $finish];
    }
}
