<?php
/**
 * Recherche globale (/gestion/recherche/?q=) : personnes et dossiers du périmètre (docs/gestion-phase-2.md, 2A, P17).
 * Page de secours sans JavaScript ; la palette Ctrl+K lit la même recherche en JSON.
 *
 * @var string      $q
 * @var list<array> $resultats
 */
?>
<form class="g-toolbar" method="get" action="<?= e(url('/gestion/recherche/')) ?>" role="search">
    <label class="g-search-field"><?= gestion_icone('search', 17) ?><span class="sr-only">Rechercher</span>
        <input class="g-input" type="search" name="q" value="<?= e($q) ?>" placeholder="Nom, téléphone, e-mail ou référence" autocomplete="off" autofocus>
    </label>
    <button class="g-btn g-btn-primary" type="submit">Rechercher</button>
</form>

<?php if (mb_strlen($q) < 2): ?>
    <p class="g-empty">Tapez au moins deux caractères.</p>
<?php elseif ($resultats === []): ?>
    <p class="g-empty">Aucun résultat dans votre périmètre.</p>
<?php else: ?>
    <ul class="g-results">
        <?php foreach ($resultats as $r): ?>
            <li><a href="<?= e($r['url']) ?>"><span class="g-badge is-<?= $r['type'] === 'Personne' ? 'rdv' : 'ok' ?>"><?= e($r['type']) ?></span><strong><?= e($r['titre']) ?></strong><small><?= e($r['detail']) ?></small></a></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
