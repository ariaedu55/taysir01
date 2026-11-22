<?php

/*
 * Publie les médias WordPress utilisés par le site, à partir de l'archive storage/exports/uploads/
 * (originaux téléchargés depuis le site public, voir docs/contenu-a-recuperer.md).
 *
 *  - « Utilisé » : cité dans le contenu ou les mises en page Elementor d'une page ou d'un article publié,
 *    image à la une d'un article, ou référencé dans config/*.php. Les variantes redimensionnées par
 *    WordPress (« photo-300x200.jpg ») sont ramenées à leur original.
 *  - Images jpg, jpeg, png, webp : converties en WebP, 1 600 px de large au maximum, qualité 76 (comme bin/optimize-images.php),
 *    dans public/assets/images/wordpress/<chemin>.webp. Les svg et gif sont copiés.
 *  - Documents (pdf, docx, xlsx, pptx, zip, mp4…) : copiés dans public/assets/files/wordpress/<chemin>.
 *  - Écrit database/content/media-manifest.php (chemin d'origine => fichier publié), lu par media_url().
 *
 * Relançable : un fichier déjà publié et plus récent que l'original n'est pas retraité.
 * Usage : php -d extension=gd -d extension=mbstring database/import/publish-media.php [--all]
 *         (--all publie toute l'archive, y compris les médias non référencés)
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("À lancer en ligne de commande.\n");
}
if (!extension_loaded('gd') || !(gd_info()['WebP Support'] ?? false)) {
    exit("Extension gd avec WebP requise (-d extension=gd).\n");
}

$base = dirname(__DIR__, 2);
$archive = "{$base}/storage/exports/uploads";
$export = "{$base}/storage/exports/taysirorientationvoyage.WordPress.2026-11-20.xml";
$imagesOut = "{$base}/public/assets/images/wordpress";
$filesOut = "{$base}/public/assets/files/wordpress";
$manifestFile = "{$base}/database/content/media-manifest.php";
$publishAll = in_array('--all', $argv, true);

/* ---------- 1. Médias utilisés ---------- */
$xml = simplexml_load_file($export, options: LIBXML_NOCDATA | LIBXML_COMPACT);
$ns = $xml->getNamespaces(true);
$attachments = [];
$thumbnails = [];
$texts = [];
foreach ($xml->channel->item as $item) {
    $wp = $item->children($ns['wp']);
    $type = (string) $wp->post_type;
    if ($type === 'attachment') {
        if (preg_match('#/wp-content/uploads/(.+)$#', (string) $wp->attachment_url, $m)) {
            $attachments[(string) $wp->post_id] = rawurldecode($m[1]);
        }
        continue;
    }
    if ((string) $wp->status !== 'publish' || !in_array($type, ['page', 'post'], true)) {
        continue;
    }
    $texts[] = (string) $item->children($ns['content'])->encoded;
    foreach ($wp->postmeta as $meta) {
        $key = (string) $meta->meta_key;
        if ($key === '_elementor_data') {
            $texts[] = str_replace('\\/', '/', (string) $meta->meta_value);
        } elseif ($key === '_thumbnail_id') {
            $thumbnails[] = (string) $meta->meta_value;
        }
    }
}
foreach (glob("{$base}/config/*.php") as $config) {
    $texts[] = file_get_contents($config);
}

$used = [];
foreach ($thumbnails as $id) {
    if (isset($attachments[$id])) {
        $used[$attachments[$id]] = true;
    }
}
$archiveSet = array_flip($attachments);
foreach ($texts as $text) {
    // Adresses complètes et chemins « 2023/04/fichier.ext » cités dans la configuration.
    preg_match_all('#(?:wp-content/uploads/|[\'"])(20\d\d/\d\d/[^\s"\'<>)\\\\]+\.[a-z0-9]{2,4})#i', $text, $matches);
    foreach ($matches[1] as $path) {
        $path = rawurldecode($path);
        if (!isset($archiveSet[$path])) {
            // Variante redimensionnée par WordPress : on remonte à l'original.
            $original = preg_replace('/-\d+x\d+(\.[a-z0-9]+)$/i', '$1', $path);
            $path = isset($archiveSet[$original]) ? $original : $path;
        }
        if (isset($archiveSet[$path])) {
            $used[$path] = true;
        }
    }
}
/*
 * Documents publiables (pdf, docx…) : liste validée à la main. La médiathèque WordPress contient aussi des
 * documents personnels de clients (passeports, lettres d'admission, visas, contrats…) qui ne doivent
 * jamais être publiés. Un document cité dans une page mais absent de cette liste est signalé, pas publié.
 */
$approvedDocuments = [];

$targets = $publishAll ? array_values($attachments) : array_keys($used);
sort($targets);
printf("%d médias dans l'export, %d utilisés par le site, %d à publier.\n", count($attachments), count($used), count($targets));

/* ---------- 2. Publication ---------- */
$manifest = is_file($manifestFile) ? require $manifestFile : [];
$stats = ['webp' => 0, 'copie' => 0, 'document' => 0, 'à jour' => 0, 'absent' => 0, 'erreur' => 0];
$before = 0;
$after = 0;
$pending = [];

foreach ($targets as $path) {
    $source = "{$archive}/{$path}";
    if (!is_file($source)) {
        $stats['absent']++;
        continue;
    }
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $before += filesize($source);

    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        $relative = 'images/wordpress/' . substr($path, 0, -strlen($ext) - 1) . '.webp';
        $target = "{$imagesOut}/" . substr($path, 0, -strlen($ext) - 1) . '.webp';
        if (is_file($target) && filemtime($target) >= filemtime($source)) {
            $stats['à jour']++;
        } elseif (!toWebp($source, $target)) {
            $stats['erreur']++;
            fwrite(STDERR, "Conversion impossible : {$path}\n");
            continue;
        } else {
            $stats['webp']++;
        }
    } elseif (in_array($ext, ['svg', 'gif'], true)) {
        $relative = 'images/wordpress/' . $path;
        $target = "{$imagesOut}/{$path}";
        copyIfNewer($source, $target) ? $stats['copie']++ : $stats['à jour']++;
    } else {
        if (!in_array($path, $approvedDocuments, true)) {
            $stats['à valider'] = ($stats['à valider'] ?? 0) + 1;
            $pending[] = $path;
            continue;
        }
        $relative = 'files/wordpress/' . $path;
        $target = "{$filesOut}/{$path}";
        copyIfNewer($source, $target) ? $stats['document']++ : $stats['à jour']++;
    }
    $after += filesize($target);
    $manifest[$path] = $relative;
}

ksort($manifest);
$export = "<?php\n\n// Médias WordPress publiés : chemin d'origine => fichier dans public/assets/.\n// Généré par database/import/publish-media.php, ne pas modifier à la main.\n\ndeclare(strict_types=1);\n\nreturn " . var_export($manifest, true) . ";\n";
file_put_contents($manifestFile, $export);

foreach ($stats as $label => $count) {
    printf("  %-9s %d\n", $label, $count);
}
printf("Poids : %.1f Mo d'originaux → %.1f Mo publiés. Manifeste : %d entrées.\n", $before / 1048576, $after / 1048576, count($manifest));
if ($pending !== []) {
    echo "Documents cités dans les pages, à valider avant publication (\$approvedDocuments) :\n";
    foreach ($pending as $path) {
        echo "  - {$path}\n";
    }
}

function toWebp(string $source, string $target): bool
{
    $data = @file_get_contents($source);
    $image = $data !== false ? @imagecreatefromstring($data) : false;
    if ($image === false) {
        return false;
    }
    imagepalettetotruecolor($image);
    imagealphablending($image, true);
    imagesavealpha($image, true);
    $width = imagesx($image);
    if ($width > 1600) {
        $scaled = imagescale($image, 1600, (int) round(imagesy($image) * 1600 / $width), IMG_BICUBIC);
        imagedestroy($image);
        $image = $scaled;
        imagesavealpha($image, true);
    }
    if (!is_dir(dirname($target))) {
        mkdir(dirname($target), 0775, true);
    }
    $ok = imagewebp($image, $target, 76);
    imagedestroy($image);

    return $ok;
}

function copyIfNewer(string $source, string $target): bool
{
    if (is_file($target) && filemtime($target) >= filemtime($source)) {
        return false;
    }
    if (!is_dir(dirname($target))) {
        mkdir(dirname($target), 0775, true);
    }

    return copy($source, $target);
}
