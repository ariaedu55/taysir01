<?php

declare(strict_types=1);

namespace App\Services\Assistant;

use App\Controllers\ActivityController;
use App\Controllers\OrientationController;
use App\Core\Database;
use App\Models\Destination;
use App\Models\Sector;
use App\Models\ServiceRequest;
use App\Models\University;
use Throwable;

/**
 * Base de connaissances de l'assistant (docs/assistant-ia.md) : tout le contenu PUBLIC de la plateforme,
 * découpé en passages d'environ 900 caractères (titre, chemin de la page, texte), recherché par BM25.
 *
 * Jamais de données personnelles : ni demandes, ni documents, ni comptes, ni messages des clients.
 * L'index est mis en cache dans storage/cache/assistant-index.php et reconstruit dès qu'un contenu change.
 */
final class KnowledgeBase
{
    private const CACHE = BASE_PATH . '/storage/cache/assistant-index.php';
    private const CHUNK = 900;
    private const K1 = 1.4;
    private const B = 0.75;

    /** Clés de contenu sans intérêt pour l'IA (images, adresses techniques, sources d'import). */
    private const SKIP_KEYS = ['image', 'photo', 'guidePhoto', 'gallery', 'source', 'wordpress', 'wordpressTitle', 'wordpress_id',
        'flag', 'focus', 'aliases', 'airport', 'logo', 'hero', 'icon', 'slug', 'key', 'category', 'status', 'published_at'];

    private const STOPWORDS = ['a', 'au', 'aux', 'avec', 'ce', 'ces', 'cette', 'dans', 'de', 'des', 'du', 'elle', 'en', 'et', 'est', 'il', 'ils',
        'je', 'la', 'le', 'les', 'leur', 'lui', 'ma', 'mais', 'me', 'mes', 'moi', 'mon', 'ne', 'nos', 'notre', 'nous', 'on', 'ou', 'par', 'pas',
        'pour', 'qu', 'que', 'qui', 'sa', 'se', 'ses', 'son', 'sur', 'ta', 'te', 'tes', 'toi', 'ton', 'tu', 'un', 'une', 'vos', 'votre', 'vous',
        'y', 'c', 'd', 'j', 'l', 'm', 'n', 's', 't', 'etre', 'avoir', 'fait', 'faire', 'plus', 'tres', 'comment', 'quel', 'quelle', 'quels',
        'quelles', 'est-ce', 'sont', 'ai', 'as', 'avez', 'peux', 'peut', 'puis', 'bien', 'aussi', 'tout', 'tous', 'toute', 'toutes', 'the', 'of',
        'and', 'to', 'in', 'is', 'for'];

    private static ?array $index = null;

    /** Passages les plus pertinents pour une question : liste de ['title', 'url', 'text', 'score']. */
    public static function search(string $query, int $limit = 6): array
    {
        $index = self::index();
        $terms = array_unique(self::tokens($query));
        if (!$terms || !$index['chunks']) {
            return [];
        }
        $count = count($index['chunks']);
        $scores = [];
        foreach ($terms as $term) {
            $df = $index['df'][$term] ?? 0;
            if ($df === 0) {
                continue;
            }
            $idf = log(1 + ($count - $df + .5) / ($df + .5));
            foreach ($index['postings'][$term] as $id => $tf) {
                $length = $index['chunks'][$id]['length'];
                $norm = $tf * (self::K1 + 1) / ($tf + self::K1 * (1 - self::B + self::B * $length / $index['avg']));
                $scores[$id] = ($scores[$id] ?? 0) + $idf * $norm;
            }
        }
        arsort($scores);
        $results = [];
        $perPage = [];
        foreach ($scores as $id => $score) {
            $chunk = $index['chunks'][$id];
            // Deux passages au plus par page, pour varier les sources.
            if (($perPage[$chunk['url']] ?? 0) >= 2) {
                continue;
            }
            $perPage[$chunk['url']] = ($perPage[$chunk['url']] ?? 0) + 1;
            $results[] = ['title' => $chunk['title'], 'url' => $chunk['url'], 'text' => $chunk['text'], 'score' => round($score, 2)];
            if (count($results) >= $limit) {
                break;
            }
        }

        return $results;
    }

    /** Sommaire court du catalogue (pays, universités, services, rubriques) pour que l'IA sache ce qui existe. */
    public static function catalogue(): string
    {
        return self::index()['catalogue'];
    }

    /** Statistiques de l'index (bin/assistant-index.php). */
    public static function stats(): array
    {
        $index = self::index();
        $pages = array_unique(array_column($index['chunks'], 'url'));

        return ['pages' => count($pages), 'passages' => count($index['chunks']), 'termes' => count($index['df']), 'construit' => date('Y-m-d H:i:s', $index['built'])];
    }

    /** Reconstruit l'index sans attendre un changement de contenu. */
    public static function rebuild(): void
    {
        self::$index = self::build(self::signature());
        self::store(self::$index);
    }

    private static function index(): array
    {
        if (self::$index !== null) {
            return self::$index;
        }
        $signature = self::signature();
        if (is_file(self::CACHE)) {
            $cached = require self::CACHE;
            if (is_array($cached) && ($cached['signature'] ?? null) === $signature) {
                return self::$index = $cached;
            }
        }
        self::$index = self::build($signature);
        self::store(self::$index);

        return self::$index;
    }

    /** Signature des contenus : dates des fichiers de contenu et état des articles publiés. */
    private static function signature(): string
    {
        $files = array_merge(
            glob(BASE_PATH . '/config/*.php') ?: [],
            glob(BASE_PATH . '/database/content/*.php') ?: [],
            glob(BASE_PATH . '/database/content/*/*.php') ?: [],
            [__FILE__],
        );
        $parts = array_map(static fn (string $file): string => basename(dirname($file)) . '/' . basename($file) . ':' . filemtime($file), $files);
        try {
            $row = Database::connection()->query("SELECT COUNT(*) n, MAX(updated_at) m FROM articles WHERE status <> 'brouillon'")->fetch();
            $parts[] = 'articles:' . $row['n'] . ':' . $row['m'];
        } catch (Throwable) {
            $parts[] = 'articles:indisponible';
        }

        return sha1(implode('|', $parts));
    }

    private static function store(array $index): void
    {
        $dir = dirname(self::CACHE);
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        $tmp = self::CACHE . '.' . bin2hex(random_bytes(4)) . '.tmp';
        file_put_contents($tmp, "<?php\n\n// Index de l'assistant IA, généré automatiquement (App\\Services\\Assistant\\KnowledgeBase).\n\nreturn " . var_export($index, true) . ";\n");
        rename($tmp, self::CACHE);
    }

    private static function build(string $signature): array
    {
        $documents = self::documents();
        $chunks = [];
        $df = [];
        $postings = [];
        $total = 0;
        foreach ($documents as [$title, $url, $text]) {
            foreach (self::split($text) as $piece) {
                $tokens = self::tokens($title . ' ' . $piece);
                if (!$tokens) {
                    continue;
                }
                $id = count($chunks);
                $chunks[] = ['title' => $title, 'url' => $url, 'text' => $piece, 'length' => count($tokens)];
                $total += count($tokens);
                foreach (array_count_values($tokens) as $term => $tf) {
                    $postings[$term][$id] = $tf;
                    $df[$term] = ($df[$term] ?? 0) + 1;
                }
            }
        }

        return [
            'signature' => $signature,
            'built' => time(),
            'avg' => $chunks ? $total / count($chunks) : 1,
            'chunks' => $chunks,
            'df' => $df,
            'postings' => $postings,
            'catalogue' => self::buildCatalogue(),
        ];
    }

    /** Toutes les pages publiques : liste de [titre, adresse, texte brut]. */
    private static function documents(): array
    {
        $docs = [];
        $add = static function (string $title, string $path, mixed $content) use (&$docs): void {
            $text = self::flatten($content);
            if (mb_strlen($text) > 40) {
                $docs[] = [$title, $path, $text];
            }
        };

        // Coordonnées écrites en toutes lettres : « adresse », « téléphone », « horaires » doivent figurer dans le texte.
        $site = config('site');
        $contact = $site['contact'] ?? [];
        $lines = ['Adresse du siège : ' . ($contact['address'] ?? '') . '. Téléphone : ' . ($contact['phone'] ?? '')
            . '. E-mail : ' . ($contact['email'] ?? '') . '. WhatsApp : ' . ($contact['whatsapp'] ?? '') . '.'];
        foreach ($site['offices'] ?? [] as $office) {
            $lines[] = 'Bureau de ' . ($office['city'] ?? '') . ' (' . ($office['label'] ?? '') . ') : adresse ' . ($office['address'] ?? 'non précisée')
                . ', téléphone ' . ($office['phone'] ?? 'non précisé') . ', e-mail ' . ($office['email'] ?? '') . '.';
        }
        $lines[] = 'Horaires d’ouverture : ' . implode(' ; ', array_map(static fn (array $h): string => implode(' : ', $h), $site['hours'] ?? [])) . '.';
        $add('Taysir Orientation Voyage : adresse, téléphone, bureaux et horaires', '/contact/', $lines);
        $add('Les services de Taysir Orientation Voyage', '/', $site['services'] ?? []);
        $faq = config('faq', []);
        foreach ($faq as $group) {
            $add('FAQ : ' . ($group[0] ?? 'Questions fréquentes'), '/faq/', $group[1] ?? []);
        }

        foreach (Destination::all() as $country) {
            $full = Destination::find($country['slug']) ?? $country;
            $facts = 'Pays : ' . $country['name'] . '. Région : ' . $country['region'] . '.';
            if ($km = Destination::distanceLabel($country)) {
                $facts .= ' Distance depuis Dakar : ' . $km . ' (aéroport ' . $country['airport'][0] . ', ' . $country['airport'][1] . ').';
            }
            $guide = config('destination-guides.' . $country['slug']);
            $add('Étudier ' . $country['in'], '/' . $country['slug'] . '/', [$facts, $full['intro'] ?? '', $full['sections'] ?? [], $guide ?? []]);
        }

        foreach (University::all() as $university) {
            $full = University::find($university['slug']) ?? $university;
            $add($university['name'] . ' (' . $university['acronym'] . ')', '/' . $university['slug'] . '/', [
                $university['name'] . ', ' . $university['city'] . '.', $full, config('institutions.' . $university['slug']) ?? [],
            ]);
        }

        foreach (ServiceRequest::services() as $service) {
            $add('Service : ' . ($service['title'] ?? $service['short'] ?? $service['slug']), '/' . $service['slug'] . '/', $service);
        }
        foreach (Sector::all() as $sector) {
            $add('Secteur : ' . $sector['name'], '/' . $sector['slug'] . '/', [$sector['name'], $sector['description']]);
        }
        foreach (OrientationController::PAGES as $slug => [$title, $lead]) {
            $file = BASE_PATH . '/database/content/orientation/' . $slug . '.php';
            $add($title, '/' . $slug . '/', [$lead, is_file($file) ? require $file : []]);
        }
        foreach (ActivityController::pages() as $activity) {
            $file = BASE_PATH . '/database/content/activities/' . $activity['slug'] . '.php';
            $add($activity['title'], '/' . $activity['slug'] . '/', [$activity['lead'] ?? '', is_file($file) ? require $file : []]);
        }
        foreach (['about' => ['Qui sommes-nous ?', '/about-us/'], 'rules' => ['Nos règles et valeurs', '/nos-regles-et-valeurs/'], 'testimonials' => ['Témoignages', '/temoignage/']] as $file => [$title, $path]) {
            $source = BASE_PATH . '/database/content/agency/' . $file . '.php';
            if (is_file($source)) {
                $add($title, $path, require $source);
            }
        }

        try {
            $rows = Database::connection()->query("SELECT slug, title, type, sector, excerpt, intro, sections, published_at FROM articles WHERE status <> 'brouillon' ORDER BY id")->fetchAll();
            $labels = ['metier' => 'Fiche métier', 'ecole' => 'École et formation', 'offre-emploi' => 'Offre d’emploi', 'offre-stage' => 'Offre de stage',
                'bourse' => 'Bourse', 'formation' => 'Formation', 'salon' => 'Salon', 'actualite' => 'Actualité'];
            foreach ($rows as $row) {
                $add(($labels[$row['type']] ?? 'Article') . ' : ' . $row['title'], '/' . $row['slug'] . '/', [
                    $row['published_at'] ? 'Date de publication : ' . $row['published_at'] . '. Vérifier si les conditions sont encore actuelles.' : '',
                    $row['sector'] ? 'Secteur : ' . (Sector::byKey($row['sector'])['name'] ?? $row['sector']) . '.' : '',
                    $row['excerpt'], $row['intro'], json_decode((string) $row['sections'], true) ?? [],
                ]);
            }
        } catch (Throwable) {
            // Base indisponible : l'assistant garde le reste du contenu.
        }

        return $docs;
    }

    private static function buildCatalogue(): string
    {
        $countries = array_map(static fn (array $c): string => $c['name'] . ' ' . absolute_url('/' . $c['slug'] . '/'), Destination::all());
        $universities = array_map(static fn (array $u): string => $u['acronym'] . ' ' . absolute_url('/' . $u['slug'] . '/'), University::all());
        $services = array_map(static fn (array $s): string => ($s['short'] ?? $s['title'] ?? $s['slug']) . ' ' . absolute_url('/' . $s['slug'] . '/'), ServiceRequest::services());
        $sectors = array_map(static fn (array $s): string => $s['name'] . ' ' . absolute_url('/' . $s['slug'] . '/'), Sector::all());

        return "Destinations accompagnées : " . implode(' ; ', $countries) . "\n"
            . "Universités publiques du Sénégal : " . implode(' ; ', $universities) . "\n"
            . "Services (demande en ligne depuis l'espace client) : " . implode(' ; ', $services) . "\n"
            . "Secteurs d'avenir : " . implode(' ; ', $sectors) . "\n"
            . 'Autres pages : fiches métiers ' . absolute_url('/metiers/') . ' ; formations professionnelles ' . absolute_url('/formations-profesionnelles/')
            . ' ; blog ' . absolute_url('/blog/') . ' ; après le bac ' . absolute_url('/apres-le-bac/') . ' ; prendre rendez-vous ' . absolute_url('/rendez_vous_orientation/')
            . ' ; contact ' . absolute_url('/contact/') . ' ; FAQ ' . absolute_url('/faq/') . ' ; créer un compte ' . absolute_url('/inscription/');
    }

    /** Texte brut d'un contenu quelconque (chaînes, tableaux imbriqués, HTML). */
    private static function flatten(mixed $value, ?string $key = null): string
    {
        if ($key !== null && in_array($key, self::SKIP_KEYS, true)) {
            return '';
        }
        if (is_array($value)) {
            $parts = [];
            foreach ($value as $k => $item) {
                $part = self::flatten($item, is_string($k) ? $k : null);
                if ($part !== '') {
                    // Les clés textuelles (titres de rubrique) sont gardées : « La demande de CAQ : … »
                    $parts[] = is_string($k) && !in_array($k, self::SKIP_KEYS, true) && preg_match('/\s/', $k) ? $k . ' : ' . $part : $part;
                }
            }

            return implode("\n", $parts);
        }
        if (!is_string($value)) {
            return '';
        }
        $value = trim($value);
        // Adresses et chemins de fichiers seuls : inutiles à l'IA.
        if ($value === '' || preg_match('#^(https?://|/)\S*$#', $value) || preg_match('#^\S+\.(webp|png|jpe?g|svg|pdf)$#i', $value)) {
            return '';
        }
        $value = preg_replace('#<(br|/p|/li|/h[1-6]|/tr)\s*/?>#i', "\n", $value);
        $value = preg_replace('#<li[^>]*>#i', '• ', $value);
        $text = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace(["/[ \t\x{00A0}]+/u", "/\n\s*\n+/"], [' ', "\n"], $text));
    }

    /** Découpe en passages d'environ CHUNK caractères, aux fins de phrase. */
    private static function split(string $text): array
    {
        $sentences = preg_split('/(?<=[.!?…:])\s+|\n+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $chunks = [];
        $current = '';
        foreach ($sentences as $sentence) {
            if ($current !== '' && mb_strlen($current) + mb_strlen($sentence) > self::CHUNK) {
                $chunks[] = $current;
                $current = '';
            }
            $current = trim($current . ' ' . $sentence);
        }
        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }

    /** Mots normalisés : minuscules, sans accents, sans mots vides, pluriels simples ramenés au singulier. */
    private static function tokens(string $text): array
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = strtr($text, ['à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ç' => 'c', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'í' => 'i', 'ô' => 'o', 'ö' => 'o', 'ó' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u', 'ÿ' => 'y',
            'œ' => 'oe', 'æ' => 'ae', '’' => ' ', "'" => ' ']);
        preg_match_all('/[a-z0-9]+/', $text, $matches);
        $tokens = [];
        foreach ($matches[0] as $word) {
            if (strlen($word) < 2 || in_array($word, self::STOPWORDS, true)) {
                continue;
            }
            if (strlen($word) > 4 && (str_ends_with($word, 's') || str_ends_with($word, 'x'))) {
                $word = substr($word, 0, -1);
            }
            $tokens[] = $word;
        }

        return $tokens;
    }
}
