<?php

/*
 * Extrait le contenu des pages de l'agence vers database/content/agency/ :
 *   about.php         « Qui sommes-nous ? » : accueil, mission, engagement, vision, services, raisons, atouts, chiffres, équipe
 *   testimonials.php  les 9 témoignages clients (page « Qui sommes-nous ? »)
 *   rules.php         « Nos règles et valeurs » (titres remis en ordre, coquilles corrigées)
 *   gallery.php       photos de clients publiées par l'agence (page « Témoignage »)
 * La FAQ (contenu de démonstration du thème) et la politique de confidentialité (erronée) ne sont pas reprises
 * telles quelles : voir docs/pages-agence.md.
 *
 * Usage : php -d extension=mbstring database/import/wordpress-agency.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("À lancer en ligne de commande.\n");
}

const BASE_PATH = __DIR__ . '/../..';
require __DIR__ . '/WordpressExport.php';

$export = $argv[1] ?? BASE_PATH . '/storage/exports/taysirorientationvoyage.WordPress.2026-11-20.xml';
$outputDir = BASE_PATH . '/database/content/agency';
$wp = new WordpressExport($export);
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0775, true);
}

/** Coquilles relevées dans les textes WordPress. */
$typos = [
    'souvent analysé de défis' => 'souvent semé de défis',
    'Taysir Orientation Voyage</strong> ,' => 'Taysir Orientation Voyage</strong>,',
    'Taysir Orientation Voyage ,' => 'Taysir Orientation Voyage,',
    'des partenaire et' => 'des partenaires et',
    'surs et fiables' => 'sûrs et fiables',
    'jusqu\'à la demande de visa.' => 'jusqu\'à la demande de visa).',
    'Taysir Orientation voyage propose des délai de traitement courts pour permettre à ses client' => 'Taysir Orientation Voyage propose des délais de traitement courts pour permettre à ses clients',
    'a adhérer à' => 'adhère à',
    'L’entreprise promouvoit des pratiques durables et responsables, et contribuer positivement' => 'L’entreprise promeut des pratiques durables et responsables, et contribue positivement',
    'Grace à' => 'Grâce à',
    'Université  de Haute facture' => 'université de haute facture',
    'l\' université' => 'l\'université',
];
$fix = static fn (string $text): string => trim((string) preg_replace('/ {2,}/', ' ', strtr($text, $typos)));
$plain = static fn (string $html): string => $fix(html_entity_decode(trim((string) preg_replace('/\s+/u', ' ', strip_tags($html))), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
$path = static fn (?string $url): ?string => $url ? WordpressExport::uploadPath($url) : null;
/** « <li><strong>Titre :</strong> texte</li> » => [[Titre, texte], …] */
$items = static function (string $html) use ($fix, $plain): array {
    preg_match_all('#<li>\s*<strong>(.+?)\s*:?\s*</strong>\s*:?\s*(.+?)</li>#su', WordpressExport::cleanHtml($html), $matches, PREG_SET_ORDER);

    return array_map(static fn (array $m): array => [WordpressExport::sentenceCase(rtrim($plain($m[1]), ' :')), $plain($m[2])], $matches);
};

/* ---------- Qui sommes-nous ? ---------- */
$widgets = WordpressExport::widgets($wp->page('about-us')['elementor'] ?? []);
$about = ['welcome' => [], 'pillars' => [], 'services' => [], 'reasons' => [], 'advantages' => [], 'stats' => [], 'team' => []];
$box = null;
foreach ($widgets as $i => $widget) {
    $type = $widget['widgetType'] ?? '';
    $settings = $widget['settings'] ?? [];
    if ($type === 'heading' && $i < 3) {
        $about['welcome'][] = $plain($settings['title']);
    } elseif ($type === 'text-editor' && $box === null) {
        $about['welcome'][] = $plain($settings['editor']);
    } elseif ($type === 'icon-box') {
        $title = $plain($settings['title_text'] ?? '');
        $description = $plain($settings['description_text'] ?? '');
        if ($description !== '') {
            $labels = ['Etude et voyage' => 'Études et voyages', 'revues de voyages' => 'Avis de voyageurs'];
            $about['stats'][] = [str_replace(',', ' ', $title), $labels[$description] ?? $description];
            $box = null;
        } else {
            $box = $title === 'Taysir Orientation Voyage' ? null : $title;
        }
    } elseif ($type === 'text-editor' && $box !== null) {
        $editor = $settings['editor'];
        if ($box === 'Nos Services') {
            $about['services'] = $items($editor);
        } elseif ($box === 'Pourquoi nous choisir?') {
            $about['reasons'] = $items($editor);
        } elseif (in_array($box, ['Notre Mission', 'Notre Engagement', 'Notre Vision'], true)) {
            $about['pillars'][] = [WordpressExport::sentenceCase($box), $plain($editor)];
        } else {
            $about['advantages'][] = [WordpressExport::sentenceCase($box), $plain($editor)];
        }
        $box = null;
    } elseif ($type === 'zourney-team-box') {
        $about['team'][] = [
            'name' => $settings['name'],
            'role' => WordpressExport::sentenceCase(mb_strtolower($settings['job'])),
            'photo' => $path($settings['image']['url'] ?? null),
        ];
    } elseif ($type === 'zourney-testimonials') {
        $testimonials = [];
        foreach ($settings['testimonials'] as $t) {
            $name = mb_convert_case(mb_strtolower(trim($t['testimonial_name'])), MB_CASE_TITLE);
            $role = strtr(trim($t['testimonial_job'] ?? ''), [
                'Etudiant en géographie à université MontReal de Canada' => 'Étudiant en géographie à l’Université de Montréal (Canada)',
                'Etudiant' => 'Étudiant', 'Etudiante' => 'Étudiante', 'L\'Université' => 'l’Université', 'à l\'Université' => 'à l’Université',
                'Ingénieure en Génie Civile' => 'Ingénieure en génie civil', 'Professionnelle dans les TIC' => 'Professionnelle des TIC',
                'Management' => 'management', 'Economie et Gestion' => 'économie et gestion', 'Visa Commerçant' => 'Commerçant',
            ]);
            $testimonials[] = [
                'name' => $name,
                'role' => $role,
                'text' => trim($fix($plain($t['testimonial_content'])), " \"“”"),
                'photo' => $path($t['testimonial_image']['url'] ?? null),
                'student' => (bool) preg_match('/^Étudiant/u', $role),
            ];
        }
    }
}
$about['team'] = array_map(static function (array $member): array {
    $member['role'] = strtr($member['role'], ['Ceo founder' => 'Fondateur et directeur général', 'etudiant' => 'étudiant', 'etude' => 'étude', 'pole' => 'pôle']);

    return $member;
}, $about['team']);

file_put_contents("{$outputDir}/about.php", WordpressExport::toPhpFile(
    ['source' => 'https://taysir-orientation.com/about-us/'] + $about,
    'Page « Qui sommes-nous ? », extraite de l\'export WordPress. Regénéré par database/import/wordpress-agency.php.'
));
file_put_contents("{$outputDir}/testimonials.php", WordpressExport::toPhpFile(
    $testimonials ?? [],
    'Témoignages clients de la page « Qui sommes-nous ? ». Regénéré par database/import/wordpress-agency.php.'
));

/* ---------- Nos règles et valeurs ---------- */
$html = '';
foreach (WordpressExport::widgets($wp->page('nos-regles-et-valeurs')['elementor'] ?? []) as $widget) {
    if (($widget['widgetType'] ?? '') === 'text-editor') {
        $html .= WordpressExport::cleanHtml($widget['settings']['editor']);
    }
}
$html = $fix(str_replace('###', '', $html));
$split = strpos($html, '<h3>Règles et Valeurs de Taysir');
[$first, $second] = $split === false ? [$html, ''] : [substr($html, 0, $split), substr($html, $split)];
// Première partie : « Règles… », « Valeurs… », « Engagement… » en h2, chaque règle en h3.
$first = (string) preg_replace('#<ol><li><strong>(.+?)</strong>\s*:\s*</li></ol>#u', '<h3>$1</h3>', $first);
$first = (string) preg_replace('#<li><strong>(.+?)</strong>\s*:\s*</li>#u', '</ul><h3>$1</h3><ul>', $first);
$first = (string) preg_replace('#<h3>((?:Règles|Valeurs) de Taysir Orientation Voyage|Engagement envers les Clients)</h3>#u', '<h2>$1</h2>', $first);
// Seconde partie : titre en h2, parties numérotées en h3, sous-titres en h4.
$second = (string) preg_replace('#<h3>(.+?)</h3>#u', '<h4>$1</h4>', $second);
$second = (string) preg_replace('#^<h4>(.+?)</h4>#u', '<h2>$1</h2>', $second);
$second = (string) preg_replace('#<p>\d\.\s*<strong>(.+?)</strong></p>#u', '<h3>$1</h3>', $second);
// Titres en casse de phrase (le nom de l'agence et les sigles gardent leurs majuscules).
$second = (string) preg_replace_callback('#<(h[234])>(.+?)</\1>#u', static fn (array $m): string => '<' . $m[1] . '>'
    . strtr(WordpressExport::sentenceCase($m[2]), ['taysir orientation voyage' => 'Taysir Orientation Voyage', 'l\'étranger' => 'l\'étranger'])
    . '</' . $m[1] . '>', $second);
$html = $first . $second;
$html = (string) preg_replace('#<p>Conclusion</p>#u', '<h3>Conclusion</h3>', $html);
$html = (string) preg_replace('#<p>-\s*#u', '<p>', $html);
$html = (string) preg_replace('#<ul>\s*</ul>#u', '', $html);
$html = str_replace(['Engagement envers les Clients', 'Qualité de Service', 'Responsabilité Sociale', 'Assistance Personnalisée', 'Accès à l\'Information', 'Suivi et Support'],
    ['Engagement envers les clients', 'Qualité de service', 'Responsabilité sociale', 'Assistance personnalisée', 'Accès à l\'information', 'Suivi et support'], $html);
file_put_contents("{$outputDir}/rules.php", WordpressExport::toPhpFile(
    ['source' => 'https://taysir-orientation.com/nos-regles-et-valeurs/', 'html' => $html],
    'Texte de la page « Nos règles et valeurs ». Regénéré par database/import/wordpress-agency.php.'
));

/* ---------- Photos de clients (page « Témoignage ») ---------- */
$photos = [];
foreach (WordpressExport::widgets($wp->page('temoignage')['elementor'] ?? []) as $widget) {
    foreach ($widget['settings']['slides'] ?? [] as $slide) {
        $photos[] = $path($slide['image']['url'] ?? null);
    }
    foreach ($widget['settings']['wp_gallery'] ?? [] as $image) {
        $photos[] = $path($image['url'] ?? null);
    }
}
$photos = array_values(array_unique(array_filter($photos)));
file_put_contents("{$outputDir}/gallery.php", WordpressExport::toPhpFile(
    $photos,
    'Photos de clients publiées par l\'agence sur la page « Témoignage ». Regénéré par database/import/wordpress-agency.php.'
));

printf("about : %d piliers, %d services, %d raisons, %d atouts, %d chiffres, %d membres\n", count($about['pillars']), count($about['services']), count($about['reasons']), count($about['advantages']), count($about['stats']), count($about['team']));
printf("testimonials : %d ; rules : %d caractères ; gallery : %d photos\n", count($testimonials ?? []), strlen(strip_tags($html)), count($photos));
