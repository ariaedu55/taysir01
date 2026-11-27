<?php
/**
 * Gabarit de l'espace équipe (docs/gestion-plan.md §12, docs/gestion-phase-1.md).
 * Barre latérale par groupes (repliable), barre supérieure (recherche Ctrl+K, « + Créer », notifications),
 * onglets en bas sur téléphone. Charte du site : blanc, gris clair, texte noir, rouge Taysir pour l'action.
 *
 * @var string $slot     Contenu de la page
 * @var string $title    Titre de la page
 * @var string $poste    Poste affiché (clé de MaquetteController::POSTES)
 * @var string $page     Page active (journee, dossiers, prospects…)
 * @var array  $equipe   Membres par poste
 * @var array  $nav      Navigation par poste
 * @var array  $recherche Index de la recherche globale
 */
require BASE_PATH . '/app/Views/gestion/icones.php';

[$prenom, $nom, $intitule] = $equipe[$poste];
$base = '/gestion/maquette';
$lien = static fn (?string $page): string => match ($page) {
    'journee' => url($base . '/'),
    'dossiers' => url($base . '/dossiers/'),
    'prospects' => url($base . '/prospects/'),
    default => '#',
};
$creer = [
    'admin' => ['Personne', 'Rendez-vous', 'Dossier', 'Règlement', 'Membre de l’équipe'],
    'secretaire' => ['Visite', 'Rendez-vous', 'Personne'],
    'prospection' => ['Prospect', 'Établissement', 'Import Excel'],
    'commerciale' => ['Prospect', 'Proposition de rendez-vous'],
    'conseiller' => ['Personne', 'Rendez-vous', 'Dossier'],
    'orientation' => ['Rendez-vous d’orientation', 'Note'],
    'traitement' => ['Tâche', 'Note', 'Relance client'],
    'comptable' => ['Règlement', 'Devis', 'Dépense'],
][$poste];
$intitules = ['admin' => 'Administratrice', 'secretaire' => 'Secrétaire', 'prospection' => 'Agent de prospection', 'commerciale' => 'Commerciale',
    'conseiller' => 'Conseiller', 'orientation' => 'Agente d’orientation', 'traitement' => 'Agent de traitement', 'comptable' => 'Comptable'];
// Recherche limitée au périmètre du poste (matrice §3.5) : prospects pour la prospection et le commercial, pas de dossiers pour la prospection
$recherche = array_values(array_filter($recherche, static fn (array $r): bool => $r['page'] === 'dossier'
    ? $poste !== 'prospection'
    : in_array($poste, ['admin', 'prospection', 'commerciale'], true)));
// Couleur de chaque groupe métier (docs/gestion-phase-1.md, 1A bis)
$familles = ['' => 'jour', 'Accueil' => 'rdv', 'Relation client' => 'rdv', 'Mes clients' => 'ori', 'Orientation' => 'ori',
    'Dossiers' => 'ok', 'Prospection' => 'pro', 'Finance' => 'fin', 'Paramètres' => 'neutre'];
// Raccourcis du bas sur téléphone : la liste de travail principale du poste, jamais une page en chantier
$raccourci = in_array($poste, ['prospection', 'commerciale'], true)
    ? ['prospects', 'target', $poste === 'commerciale' ? 'Mes prospects' : 'Prospects']
    : ['dossiers', 'folder', 'Dossiers'];
$initiales = mb_substr($prenom, 0, 1) . mb_substr($nom, 0, 1);
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#ffffff">
    <title><?= e($title) ?> — Gestion Taysir</title>
    <link rel="icon" href="<?= e(asset('images/brand/logo-navy.webp')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Prata&amp;family=Poppins:wght@400;500;600;700;800&amp;display=swap">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/gestion.css')) ?>">
    <script src="<?= e(asset('js/gestion.js')) ?>" defer></script>
</head>
<body class="g-body">
<a class="skip" href="#g-contenu">Aller au contenu</a>
<div class="g-shell" data-g-shell>

    <aside class="g-sidebar" id="g-sidebar" aria-label="Navigation de l’espace équipe">
        <div class="g-brand">
            <a href="<?= e(url($base . '/')) ?>" class="g-brand-logo" aria-label="Taysir, Ma journée">
                <img src="<?= e(asset('images/brand/logo-navy.webp')) ?>" width="124" height="44" alt="Taysir Orientation Voyage">
            </a>
            <button class="g-icon-btn g-collapse" type="button" data-g-collapse aria-label="Réduire la barre latérale" title="Réduire la barre latérale"><?= gestion_icone('collapse', 18) ?></button>
            <button class="g-icon-btn g-drawer-close" type="button" data-g-drawer-close aria-label="Fermer le menu"><?= gestion_icone('close', 18) ?></button>
        </div>
        <p class="g-agency"><span class="g-agency-dot" aria-hidden="true"></span><span class="g-hide-collapsed">Agence de Dakar</span></p>

        <nav class="g-nav">
            <?php $actifPose = false; ?>
            <?php foreach ($nav[$poste] as [$groupe, $items]): ?>
                <?php $fam = $familles[$groupe] ?? 'neutre'; ?>
                <?php if ($groupe !== ''): ?><p class="g-nav-title is-<?= e($fam) ?>"><span class="g-nav-mark" aria-hidden="true"></span><?= e($groupe) ?></p><?php endif; ?>
                <ul class="is-<?= e($fam) ?>">
                    <?php foreach ($items as [$label, $icone, $cible, $compteur]): ?>
                        <?php $actif = !$actifPose && $cible === $page && ($cible !== 'journee' || $label === 'Ma journée'); $actifPose = $actifPose || $actif; ?>
                        <li>
                            <a class="g-nav-link<?= $actif ? ' is-active' : '' ?>" href="<?= e($lien($cible)) ?>"<?= $actif ? ' aria-current="page"' : '' ?><?= $cible === null ? ' data-g-soon' : '' ?> title="<?= e($label) ?>">
                                <span class="g-nav-icon" aria-hidden="true"><?= gestion_icone($icone, 17) ?></span>
                                <span class="g-nav-label"><?= e($label) ?></span>
                                <?php if ($cible === null): ?><span class="g-soon-tag" title="Écran prévu dans une phase suivante">bientôt</span><?php endif; ?>
                                <?php if ($compteur !== null): ?><span class="g-count" aria-label="<?= (int) $compteur ?> en attente"><?= (int) $compteur ?></span><?php endif; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endforeach; ?>
        </nav>

        <div class="g-sidebar-foot">
            <div class="g-me">
                <span class="g-avatar" aria-hidden="true"><?= e($initiales) ?></span>
                <span class="g-hide-collapsed"><strong><?= e($prenom . ' ' . $nom) ?></strong><small><?= e($intitule) ?></small></span>
            </div>
            <label class="g-switch-poste g-hide-collapsed">
                <span>Voir en tant que <em>(maquette)</em></span>
                <select data-g-poste>
                    <?php foreach ($intitules as $cle => $libelle): ?>
                        <option value="<?= e($cle) ?>"<?= $cle === $poste ? ' selected' : '' ?>><?= e($libelle) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <a class="g-client-link g-hide-collapsed" href="<?= e(url($base . '/client/')) ?>"><?= gestion_icone('eye', 16) ?> Voir le suivi côté client</a>
        </div>
    </aside>
    <div class="g-scrim" data-g-drawer-close></div>

    <div class="g-main">
        <header class="g-topbar">
            <button class="g-icon-btn g-menu-btn" type="button" data-g-drawer-open aria-controls="g-sidebar" aria-label="Ouvrir le menu"><?= gestion_icone('menu') ?></button>
            <div class="g-heading">
                <p>Espace équipe · <?= e($intitule) ?></p>
                <h1><?= e($title) ?></h1>
            </div>
            <button class="g-search" type="button" data-g-search-open aria-haspopup="dialog">
                <?= gestion_icone('search', 18) ?>
                <span>Rechercher une personne, un dossier…</span>
                <kbd>Ctrl K</kbd>
            </button>
            <div class="g-menu-wrap">
                <button class="g-btn g-btn-primary g-create-btn" type="button" data-g-menu aria-expanded="false" aria-haspopup="true">
                    <?= gestion_icone('plus', 18) ?><span>Créer</span>
                </button>
                <ul class="g-menu" hidden>
                    <?php foreach ($creer as $element): ?>
                        <li><button type="button" data-g-soon><?= e($element) ?></button></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="g-menu-wrap">
                <button class="g-icon-btn g-bell" type="button" data-g-menu aria-expanded="false" aria-haspopup="true" aria-label="Notifications (3 nouvelles)">
                    <?= gestion_icone('bell') ?><span class="g-bell-dot" aria-hidden="true"></span>
                </button>
                <div class="g-menu g-notifs" hidden>
                    <p class="g-menu-title">Notifications</p>
                    <ul>
                        <li><span class="g-dot is-alerte"></span><span><strong>Awa Sène</strong> a déposé une pièce.<small>il y a 25 min</small></span></li>
                        <li><span class="g-dot is-info"></span><span><strong>Rokhaya Thiam</strong> a demandé un rendez-vous depuis le site.<small>il y a 1 h</small></span></li>
                        <li><span class="g-dot is-urgent"></span><span>Échéance dépassée : <strong>TAY-2027-0125</strong>.<small>hier</small></span></li>
                    </ul>
                </div>
            </div>
        </header>

        <p class="g-mock-banner"><?= gestion_icone('alert', 16) ?> <span class="g-mock-long">Maquette : données fictives. Chaque action (confirmation, validation, transfert) n’est gardée que dans la page affichée ; un rechargement la remet à zéro.</span><span class="g-mock-short">Maquette : données fictives, actions effacées au rechargement.</span></p>

        <main id="g-contenu" class="g-content" tabindex="-1">
            <?= $slot ?>
        </main>
    </div>

    <nav class="g-tabbar" aria-label="Navigation rapide">
        <a href="<?= e(url($base . '/')) ?>"<?= $page === 'journee' ? ' aria-current="page"' : '' ?>><?= gestion_icone('sun') ?><span>Ma journée</span></a>
        <a href="<?= e($lien($raccourci[0])) ?>"<?= $page === $raccourci[0] ? ' aria-current="page"' : '' ?>><?= gestion_icone($raccourci[1]) ?><span><?= e($raccourci[2]) ?></span></a>
        <button type="button" class="g-tab-search" data-g-search-open><?= gestion_icone('search') ?><span>Rechercher</span></button>
        <button type="button" data-g-drawer-open aria-controls="g-sidebar"><?= gestion_icone('menu') ?><span>Menu</span></button>
    </nav>
</div>

<div class="g-palette" role="dialog" aria-modal="true" aria-label="Recherche globale" hidden data-g-palette>
    <div class="g-palette-box">
        <label class="g-palette-field"><?= gestion_icone('search') ?><span class="sr-only">Rechercher</span>
            <input type="search" placeholder="Nom, téléphone, référence de dossier…" autocomplete="off" data-g-palette-input>
            <kbd>Échap</kbd>
        </label>
        <ul class="g-palette-results" role="listbox" data-g-palette-results></ul>
        <p class="g-palette-hint">Résultats limités à votre périmètre. ↑ ↓ pour choisir, Entrée pour ouvrir.</p>
    </div>
</div>
<div class="g-toasts" aria-live="polite" data-g-toasts></div>
<script type="application/json" id="g-search-data"><?= json_encode(array_map(static fn (array $r): array => $r + ['url' => $r['page'] === 'dossier' ? url($base . '/dossiers/' . $r['ref'] . '/') : url($base . '/prospects/')], $recherche), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
<script type="application/json" id="g-config"><?= json_encode(['base' => url($base . '/')], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
</body>
</html>
