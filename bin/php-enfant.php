<?php

declare(strict_types=1);

/*
 * Commande PHP des processus enfants (serveur de recette, migrate.php) lancés par bin/recette.php et
 * bin/repetition-migrations.php : mêmes réglages que le processus parent, sur n'importe quelle machine.
 * - Parent lancé avec un php.ini : l'enfant le relit (rien à ajouter).
 * - Parent lancé avec « -n » : l'enfant recharge les mêmes extensions, dans le même ordre (pdo avant pdo_mysql).
 *
 * @return list<string>
 */
return (static function (): array {
    $commande = [PHP_BINARY];
    if (php_ini_loaded_file() !== false) {
        return array_merge($commande, ['-c', php_ini_loaded_file()]);
    }
    $dossier = (string) ini_get('extension_dir');
    $commande = array_merge($commande, ['-n', '-d', 'extension_dir=' . $dossier]);
    foreach (get_loaded_extensions() as $extension) {
        $nom = strtolower($extension);
        foreach ([$nom . '.so', 'php_' . $nom . '.dll', $nom . '.dll'] as $fichier) {
            if (is_file($dossier . DIRECTORY_SEPARATOR . $fichier) || is_file(rtrim($dossier, '/\\') . '/' . $fichier)) {
                array_push($commande, '-d', 'extension=' . $nom);
                break;
            }
        }
    }

    return $commande;
})();
