<?php

declare(strict_types=1);

/*
 * Environnement ISOLÉ du robot de recette et de la répétition des migrations
 * (bin/recette.php, bin/repetition-migrations.php ; docs/gestion-phase-1.md, 1B bis).
 *
 * Actif seulement si la variable d'environnement TAYSIR_DB porte un nom finissant par « _recette » :
 * jamais en production, où elle n'existe pas. Alors :
 * - base de données : TAYSIR_DB (et, en option, le compte SQL dédié TAYSIR_DB_USER / TAYSIR_DB_PASSWORD) ;
 * - pièces, e-mails et sessions : storage/recette/<base>/, propres à l'exécution et supprimés à la fin.
 *
 * Retourne null hors recette.
 */
$base = getenv('TAYSIR_DB');
if (!is_string($base) || !preg_match('/^[a-z0-9_]+_recette$/D', $base)) {
    return null;
}
$user = getenv('TAYSIR_DB_USER');

return [
    'base' => $base,
    'user' => is_string($user) && $user !== '' ? $user : null,
    'password' => is_string($user) && $user !== '' ? (string) getenv('TAYSIR_DB_PASSWORD') : null,
    'storage' => dirname(__DIR__) . '/storage/recette/' . $base,
];
