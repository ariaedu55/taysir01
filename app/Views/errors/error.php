<?php
$messages = [
    403 => ['Accès refusé', 'Vous n’avez pas les droits nécessaires pour consulter cette page.'],
    404 => ['Page introuvable', 'La page demandée n’existe pas ou a été déplacée.'],
    405 => ['Méthode non autorisée', 'Cette adresse n’accepte pas ce type de requête.'],
    419 => ['Session expirée', 'Le formulaire a expiré. Rechargez la page et réessayez.'],
    500 => ['Erreur du serveur', 'Une erreur est survenue. Réessayez dans quelques instants.'],
];
$code = (int) ($code ?? 500);
[$title, $message] = $messages[$code] ?? $messages[500];
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= e($title) ?> — Taysir Orientation Voyage</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 16px; box-sizing: border-box; background: #FFFFFF; color: #0A0A0A; font-family: "Segoe UI", system-ui, sans-serif; text-align: center; }
        .code { margin: 0; font: 700 clamp(4rem, 18vw, 8rem)/1 Bahnschrift, "Segoe UI", sans-serif; color: #0A0A0A; }
        h1 { margin: .5rem 0; }
        p { max-width: 32rem; color: #5F5F5F; }
        a { display: inline-block; margin-top: 1rem; padding: .75rem 1.5rem; border-radius: 999px; background: #CE0F0F; color: #FFFFFF; font-weight: 600; text-decoration: none; }
    </style>
</head>
<body>
    <main>
        <p class="code"><?= $code ?></p>
        <h1><?= e($title) ?></h1>
        <p><?= e($message) ?></p>
        <a href="<?= e(url('/')) ?>">Retour à l’accueil</a>
    </main>
</body>
</html>
