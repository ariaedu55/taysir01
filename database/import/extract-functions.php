<?php

/*
 * Fonctions d'extraction communes aux scripts database/import/wordpress-*.php.
 * Elles s'appuient sur WordpressExport (lecture de l'export et nettoyage du HTML).
 */

declare(strict_types=1);

/**
 * Parcourt la première colonne d'une mise en page Elementor :
 *  - image → image principale (la première) ;
 *  - texte avant la première rubrique → introduction ;
 *  - titre « divider » → nouvelle rubrique ;
 *  - onglets « toggle » ou « accordion » → rubriques (ou sous-rubriques s'ils suivent un titre) ;
 *  - icônes sociales → liens de contact [type, adresse].
 *
 * $fix accepte « drop » (motifs de titres de rubrique à retirer) et « rename » (motif => nouveau titre).
 */
function extractPage(array $elementor, array $fix, array $titleFixes): array
{
    $widgets = WordpressExport::widgets($elementor[0]['elements'][0]['elements'] ?? []);
    $image = null;
    $intro = '';
    $sections = [];
    $links = [];
    $current = null;

    $flush = static function () use (&$current, &$sections): void {
        if ($current !== null && trim(strip_tags($current['html'])) !== '') {
            $sections[] = $current;
        }
        $current = null;
    };

    foreach ($widgets as $widget) {
        $settings = $widget['settings'] ?? [];
        switch ($widget['widgetType'] ?? '') {
            case 'image':
                $image ??= WordpressExport::uploadPath($settings['image']['url'] ?? '');
                break;
            case 'text-editor':
                $html = WordpressExport::cleanHtml($settings['editor'] ?? '');
                if ($current === null && $sections === []) {
                    $intro .= $html;
                } elseif ($current !== null) {
                    $current['html'] .= $html;
                }
                break;
            case 'divider':
                $flush();
                $current = ['title' => cleanTitle($settings['text'] ?? '', $titleFixes), 'html' => ''];
                break;
            case 'toggle':
            case 'accordion':
                foreach ($settings['tabs'] ?? [] as $tab) {
                    $title = cleanTitle($tab['tab_title'] ?? '', $titleFixes);
                    $html = WordpressExport::cleanHtml($tab['tab_content'] ?? '');
                    if ($current !== null) {
                        $current['html'] .= '<h3>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h3>' . $html;
                    } else {
                        $sections[] = ['title' => $title, 'html' => $html];
                    }
                }
                break;
            case 'social-icons':
                foreach ($settings['social_icon_list'] ?? [] as $icon) {
                    $link = classifyLink(trim((string) ($icon['link']['url'] ?? '')), $icon['social_icon']['value'] ?? '');
                    if ($link !== null) {
                        $links[] = $link;
                    }
                }
                break;
        }
    }
    $flush();

    foreach ($fix['drop'] ?? [] as $pattern) {
        $sections = array_values(array_filter($sections, static fn (array $s): bool => !preg_match($pattern, $s['title'])));
    }
    foreach ($fix['rename'] ?? [] as $pattern => $title) {
        foreach ($sections as &$section) {
            if (preg_match($pattern, $section['title'])) {
                $section['title'] = $title;
            }
        }
        unset($section);
    }

    // Sous-titres en casse française, comme les titres de rubrique.
    $headings = static fn (string $html): string => preg_replace_callback(
        '#<h3>(.*?)</h3>#s',
        static fn (array $m): string => '<h3>' . htmlspecialchars(cleanTitle($m[1], $titleFixes), ENT_QUOTES, 'UTF-8') . '</h3>',
        $html
    );
    foreach ($sections as &$section) {
        $section['html'] = $headings($section['html']);
    }
    unset($section);

    return ['image' => $image, 'intro' => $headings($intro), 'sections' => $sections, 'links' => $links];
}

/**
 * Type d'un lien de contact d'après son adresse : email, facebook, x, youtube, instagram, linkedin ou site.
 * Retourne null si l'adresse ne correspond pas à l'icône (par exemple une adresse e-mail derrière l'icône Twitter).
 */
function classifyLink(string $url, mixed $icon): ?array
{
    if ($url === '') {
        return null;
    }
    $icon = is_string($icon) ? strtolower($icon) : '';
    if (str_starts_with($url, 'mailto:')) {
        return ['email', $url];
    }
    if (!preg_match('#^https?://#i', $url)) {
        return null;
    }
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    $type = match (true) {
        str_contains($host, 'facebook.') => 'facebook',
        str_contains($host, 'twitter.') || $host === 'x.com' || str_ends_with($host, '.x.com') => 'x',
        str_contains($host, 'youtube.') => 'youtube',
        str_contains($host, 'instagram.') => 'instagram',
        str_contains($host, 'linkedin.') => 'linkedin',
        default => 'site',
    };
    // Une icône de réseau social qui pointe ailleurs est une erreur de saisie : on l'écarte.
    foreach (['facebook', 'twitter' => 'x', 'youtube', 'instagram', 'linkedin'] as $key => $expected) {
        $name = is_string($key) ? $key : $expected;
        if (str_contains($icon, $name) && $type !== $expected) {
            return null;
        }
    }

    return [$type, $url];
}

/** Titre en casse française, avec les corrections communes ($titleFixes : motif => remplacement ou fonction). */
function cleanTitle(string $title, array $titleFixes): string
{
    $title = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($title), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    $title = rtrim($title, " :\u{a0}");
    $title = str_replace("'", '’', $title);
    $title = WordpressExport::sentenceCase($title);
    foreach ($titleFixes as $pattern => $replacement) {
        $title = is_callable($replacement)
            ? preg_replace_callback($pattern . 'u', $replacement, $title)
            : preg_replace($pattern . 'u', $replacement, $title);
    }

    return $title;
}
