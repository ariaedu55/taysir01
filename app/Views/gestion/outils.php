<?php

declare(strict_types=1);

/*
 * Petites aides d'affichage de l'espace équipe réel (docs/gestion-phase-1.md, 1B).
 * Chargées par App\Controllers\Gestion\EspaceController::page().
 */

if (!function_exists('g_famille')) {
    /** Teinte d'un statut des modèles (blue, amber…) => famille de couleur de l'espace équipe (rdv, ech…). */
    function g_famille(string $tone): string
    {
        return ['blue' => 'rdv', 'amber' => 'ech', 'violet' => 'ori', 'green' => 'ok', 'grey' => 'neutre', 'red' => 'urgent'][$tone] ?? 'neutre';
    }

    /** Pastille de statut : point coloré, fond teinté, texte noir. */
    function g_badge(string $label, string $tone): string
    {
        $famille = g_famille($tone);

        return '<span class="g-badge is-' . e($famille) . '"><span class="g-dot is-' . e($famille) . '"></span>' . e($label) . '</span>';
    }

    /** Date courte : « aujourd'hui 14:05 », « hier », « 12/11 », « 12/11/2025 ». */
    function g_date(?string $date, bool $time = false): string
    {
        if ($date === null || $date === '') {
            return '—';
        }
        $timestamp = strtotime($date);
        $day = date('Y-m-d', $timestamp);
        if ($day === date('Y-m-d')) {
            return 'aujourd’hui' . ($time ? ' ' . date('H:i', $timestamp) : '');
        }
        if ($day === date('Y-m-d', strtotime('-1 day'))) {
            return 'hier' . ($time ? ' ' . date('H:i', $timestamp) : '');
        }

        return date(date('Y', $timestamp) === date('Y') ? 'd/m' : 'd/m/Y', $timestamp) . ($time ? ' ' . date('H:i', $timestamp) : '');
    }

    function g_taille(int $bytes): string
    {
        return $bytes < 1048576 ? max(1, (int) round($bytes / 1024)) . ' Ko' : number_format($bytes / 1048576, 1, ',', ' ') . ' Mo';
    }

    /** « conseiller:Awa Ndiaye|traitement:Cheikh Gueye » (searchScoped) => ['conseiller' => 'Awa Ndiaye', …] */
    function g_responsables(?string $value): array
    {
        $result = [];
        foreach (array_filter(explode('|', (string) $value)) as $pair) {
            [$poste, $name] = array_pad(explode(':', $pair, 2), 2, '');
            $result[$poste] = $name;
        }

        return $result;
    }

    /** Lien de filtre qui garde les autres paramètres de la page. */
    function g_filtre(string $path, array $params): string
    {
        $query = http_build_query(array_filter($params, static fn ($value) => $value !== null && $value !== ''));

        return url($path) . ($query !== '' ? '?' . $query : '');
    }
}
