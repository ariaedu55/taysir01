<?php

declare(strict_types=1);

/*
 * Icônes au trait de l'espace équipe (24 × 24, trait 1.8). Utilisation : gestion_icone('folder').
 * Un seul jeu, cohérent, pour la navigation et les actions (docs/gestion-plan.md, §3.5 du plan d'interface).
 */
if (!function_exists('gestion_icone')) {
    function gestion_icone(string $name, int $size = 20): string
    {
        static $paths = [
            'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
            'chart' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
            'users' => '<path d="M16 20v-1.5A3.5 3.5 0 0 0 12.5 15h-5A3.5 3.5 0 0 0 4 18.5V20"/><circle cx="10" cy="8" r="3.5"/><path d="M20 20v-1.5a3.5 3.5 0 0 0-2.5-3.4M15.5 4.6a3.5 3.5 0 0 1 0 6.8"/>',
            'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2.5"/><path d="M3 10h18M8 3v4M16 3v4"/>',
            'folder' => '<path d="M3 7.5A2.5 2.5 0 0 1 5.5 5H10l2 2.5h6.5A2.5 2.5 0 0 1 21 10v7.5a2.5 2.5 0 0 1-2.5 2.5h-13A2.5 2.5 0 0 1 3 17.5Z"/>',
            'target' => '<circle cx="12" cy="12" r="8.5"/><circle cx="12" cy="12" r="4.5"/><circle cx="12" cy="12" r="1"/>',
            'flag' => '<path d="M5 21V4M5 4h11l-2 4 2 4H5"/>',
            'wallet' => '<path d="M4 7.5A2.5 2.5 0 0 1 6.5 5H19v4M4 7.5v10A2.5 2.5 0 0 0 6.5 20H20V9H6.5A2.5 2.5 0 0 1 4 7.5Z"/><circle cx="16" cy="14.5" r="1"/>',
            'receipt' => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2ZM9 8h6M9 12h6M9 16h3"/>',
            'team' => '<circle cx="12" cy="7.5" r="3"/><path d="M6.5 20v-1.5A3.5 3.5 0 0 1 10 15h4a3.5 3.5 0 0 1 3.5 3.5V20M3 20v-1a3 3 0 0 1 2.5-3M21 20v-1a3 3 0 0 0-2.5-3"/>',
            'building' => '<path d="M4 21V5.5L12 3v18M12 9h8v12M2 21h20M7.5 8v.01M7.5 12v.01M7.5 16v.01M16 13v.01M16 17v.01"/>',
            'tag' => '<path d="M3 12V4h8l10 10-8 8Z"/><circle cx="7.5" cy="8" r="1.3"/>',
            'route' => '<circle cx="6" cy="18" r="2.5"/><circle cx="18" cy="6" r="2.5"/><path d="M8.5 18H15a3 3 0 0 0 0-6H9a3 3 0 0 1 0-6h6.5"/>',
            'queue' => '<path d="M4 6h16M4 12h10M4 18h7"/><circle cx="18.5" cy="16.5" r="2.5"/>',
            'inbox' => '<path d="M3 13l2.5-8h13L21 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2ZM3 13h5l1.5 3h5L16 13h5"/>',
            'upload' => '<path d="M12 16V4M7 9l5-5 5 5M4 16v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/>',
            'phone' => '<path d="M5 3h4l2 5-2.5 1.5a11 11 0 0 0 6 6L16 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 5a2 2 0 0 1 2-2"/>',
            'chat' => '<path d="M4 5h16v11H9l-5 4Z"/>',
            'compass' => '<circle cx="12" cy="12" r="9"/><path d="m15.5 8.5-2 5-5 2 2-5Z"/>',
            'check' => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
            'file' => '<path d="M6 3h8l4 4v14H6ZM14 3v4h4M9 13h6M9 17h4"/>',
            'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
            'doc' => '<path d="M6 3h12v18H6ZM9 8h6M9 12h6M9 16h4"/>',
            'cart' => '<path d="M3 4h2l2.5 11h10L20 7H6.2M9 20h.01M17 20h.01"/>',
            'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
            'plus' => '<path d="M12 5v14M5 12h14"/>',
            'bell' => '<path d="M6 16V11a6 6 0 1 1 12 0v5l1.5 2h-15ZM10 21h4"/>',
            'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
            'close' => '<path d="M6 6l12 12M18 6 6 18"/>',
            'chevron' => '<path d="m9 6 6 6-6 6"/>',
            'chevron-down' => '<path d="m6 9 6 6 6-6"/>',
            'collapse' => '<path d="M15 6l-6 6 6 6"/>',
            'filter' => '<path d="M4 5h16l-6 7.5V19l-4 2v-8.5Z"/>',
            'sort' => '<path d="M8 4v16M4 8l4-4 4 4M16 20V4M12 16l4 4 4-4"/>',
            'more' => '<circle cx="5" cy="12" r="1.3"/><circle cx="12" cy="12" r="1.3"/><circle cx="19" cy="12" r="1.3"/>',
            'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
            'eye' => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
            'download' => '<path d="M12 4v12M7 11l5 5 5-5M4 20h16"/>',
            'lock' => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
            'send' => '<path d="M21 3 10 14M21 3l-7 18-4-7-7-4Z"/>',
            'swap' => '<path d="M7 4 3 8l4 4M3 8h14M17 20l4-4-4-4M21 16H7"/>',
            'alert' => '<path d="M12 3 2 20h20ZM12 10v4M12 17v.01"/>',
            'logout' => '<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 17l5-5-5-5M15 12H4"/>',
            'home' => '<path d="m3 11 9-7 9 7v9a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1Z"/>',
            'plane' => '<path d="M17.8 19.2 16 11l3.5-3.5C21 6 21.5 4 21 3c-1-.5-3 0-4.5 1.5L13 8 4.8 6.2c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.5 5.3c.3.4.8.5 1.3.3l.5-.2c.4-.3.6-.7.5-1.2z"/>',
        ];

        return '<svg class="g-icon" viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . ($paths[$name] ?? $paths['doc']) . '</svg>';
    }
}
