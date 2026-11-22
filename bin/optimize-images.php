<?php

declare(strict_types=1);

/*
 * Optimise les images servies par le site (public/assets/images/), voir docs/optimisation-images.md.
 *
 *  - PNG et JPEG : convertis en WebP (transparence conservée), l'original est supprimé.
 *  - Images de moins de 15 Ko (logos, drapeaux) : WebP sans perte.
 *  - WebP existants : redimensionnés à MAX_WIDTH au plus (MAX_WIDTH_FULL pour les visuels plein écran),
 *    réencodés en qualité QUALITY ; le fichier n'est remplacé que si le gain dépasse MIN_GAIN.
 *
 * Relançable : une image déjà optimisée ne gagne plus assez et reste telle quelle.
 *
 * Usage (PHP CLI avec l'extension GD) :
 *   php -n -d extension_dir=C:/wamp64/bin/php/php8.4.15/ext -d extension=gd bin/optimize-images.php [--dry-run]
 */

const QUALITY = 76;
const MAX_WIDTH = 1600;
const MAX_WIDTH_FULL = 1920;
const MIN_GAIN = 0.10;
// Petites images aux bords nets (logos, drapeaux) : WebP sans perte, pour ne pas les flouter.
const LOSSLESS_BELOW = 15 * 1024;
// Visuels affichés en plein écran : on garde plus de pixels.
const FULL_WIDTH_DIRS = ['destinations/hero/', 'hero-flight/'];

if (!extension_loaded('gd') || !(gd_info()['WebP Support'] ?? false)) {
    fwrite(STDERR, "L'extension GD avec WebP est nécessaire (-d extension=gd).\n");
    exit(1);
}

$dryRun = in_array('--dry-run', $argv, true);
$root = dirname(__DIR__) . '/public/assets/images/';
$stats = ['converted' => 0, 'recompressed' => 0, 'kept' => 0, 'failed' => 0, 'before' => 0, 'after' => 0];

/** Charge une image en vraie couleur avec sa transparence. */
function load_image(string $file, string $ext): ?GdImage
{
    $image = match ($ext) {
        'png' => @imagecreatefrompng($file),
        'jpg', 'jpeg' => @imagecreatefromjpeg($file),
        'webp' => @imagecreatefromwebp($file),
        default => false,
    };
    if (!$image) {
        return null;
    }
    if (!imageistruecolor($image)) {
        imagepalettetotruecolor($image);
    }
    imagealphablending($image, false);
    imagesavealpha($image, true);

    return $image;
}

/** Redimensionne à $max px de large au plus, transparence comprise. */
function fit(GdImage $image, int $max): GdImage
{
    $width = imagesx($image);
    if ($width <= $max) {
        return $image;
    }
    $height = (int) round(imagesy($image) * $max / $width);
    $scaled = imagecreatetruecolor($max, $height);
    imagealphablending($scaled, false);
    imagesavealpha($scaled, true);
    imagefill($scaled, 0, 0, imagecolorallocatealpha($scaled, 0, 0, 0, 127));
    imagecopyresampled($scaled, $image, 0, 0, 0, 0, $max, $height, $width, imagesy($image));

    return $scaled;
}

/** Encode en WebP dans une chaîne (IMG_WEBP_LOSSLESS pour une image sans perte). */
function encode(GdImage $image, int $quality = QUALITY): ?string
{
    ob_start();
    $ok = imagewebp($image, null, $quality);
    $data = ob_get_clean();

    return $ok && $data !== '' ? $data : null;
}

$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($files as $info) {
    $file = str_replace('\\', '/', $info->getPathname());
    $ext = strtolower($info->getExtension());
    if (!in_array($ext, ['png', 'jpg', 'jpeg', 'webp'], true)) {
        continue;
    }
    $relative = substr($file, strlen($root));
    $max = array_filter(FULL_WIDTH_DIRS, static fn (string $dir): bool => str_starts_with($relative, $dir)) ? MAX_WIDTH_FULL : MAX_WIDTH;
    $size = filesize($file);
    $stats['before'] += $size;

    $image = load_image($file, $ext);
    if ($image === null) {
        echo "ÉCHEC lecture  {$relative}\n";
        $stats['failed']++;
        $stats['after'] += $size;
        continue;
    }
    $data = encode(fit($image, $max), $size < LOSSLESS_BELOW ? IMG_WEBP_LOSSLESS : QUALITY);
    if ($data === null) {
        echo "ÉCHEC encodage {$relative}\n";
        $stats['failed']++;
        $stats['after'] += $size;
        continue;
    }

    if ($ext !== 'webp') {
        // Conversion : toujours, la référence passe au .webp.
        $target = preg_replace('/\.(png|jpe?g)$/i', '.webp', $file);
        if (!$dryRun) {
            file_put_contents($target, $data);
            if (!@unlink($file)) {
                // Fichier ouvert ou protégé par ses droits Windows : à supprimer à la main.
                echo "ORIGINAL NON SUPPRIMÉ {$relative}
";
            }
        }
        printf("CONVERTI       %-70s %8s → %8s\n", $relative, number_format($size / 1024, 1) . ' Ko', number_format(strlen($data) / 1024, 1) . ' Ko');
        $stats['converted']++;
        $stats['after'] += strlen($data);
    } elseif (strlen($data) <= $size * (1 - MIN_GAIN)) {
        if (!$dryRun) {
            file_put_contents($file, $data);
        }
        $stats['recompressed']++;
        $stats['after'] += strlen($data);
    } else {
        $stats['kept']++;
        $stats['after'] += $size;
    }
}

printf(
    "\n%s%d converties, %d recompressées, %d inchangées, %d échecs\nAvant : %.1f Mo  Après : %.1f Mo  Gain : %.1f Mo (%.0f %%)\n",
    $dryRun ? '[simulation] ' : '',
    $stats['converted'], $stats['recompressed'], $stats['kept'], $stats['failed'],
    $stats['before'] / 1048576, $stats['after'] / 1048576, ($stats['before'] - $stats['after']) / 1048576,
    $stats['before'] ? 100 * ($stats['before'] - $stats['after']) / $stats['before'] : 0
);
