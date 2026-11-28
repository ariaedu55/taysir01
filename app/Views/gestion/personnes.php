<?php
/**
 * Liste des personnes (/gestion/personnes/), limitée au périmètre du compte (docs/gestion-phase-2.md, 2A).
 *
 * @var list<array> $personnes
 * @var int         $total, $pageCourante, $pages
 * @var string      $q
 * @var bool        $peutCreer
 */
use App\Models\Person;

$initiales = static fn (string $prenom, string $nom): string => mb_strtoupper(mb_substr($prenom, 0, 1) . mb_substr($nom, 0, 1));
?>
<form class="g-toolbar" method="get" action="<?= e(url('/gestion/personnes/')) ?>" role="search">
    <label class="g-search-field"><?= gestion_icone('search', 17) ?><span class="sr-only">Rechercher une personne</span>
        <input class="g-input" type="search" name="q" value="<?= e($q) ?>" placeholder="Nom, téléphone ou e-mail" autocomplete="off">
    </label>
    <button class="g-btn" type="submit">Rechercher</button>
    <span class="g-muted g-toolbar-count"><?= (int) $total ?> personne<?= $total > 1 ? 's' : '' ?></span>
    <?php if ($peutCreer): ?>
        <a class="g-btn g-btn-primary g-toolbar-end" href="<?= e(url('/gestion/personnes/nouvelle/')) ?>"><?= gestion_icone('plus', 16) ?> Nouvelle personne</a>
    <?php endif; ?>
</form>

<div class="g-table-wrap">
    <?php if ($personnes === []): ?>
        <p class="g-empty"><?= $q !== '' ? 'Aucune personne ne correspond dans votre périmètre.' : 'Aucune personne dans votre périmètre pour le moment.' ?></p>
    <?php else: ?>
        <table class="g-table">
            <thead>
                <tr><th>Personne</th><th>Coordonnées</th><th>Dossiers</th><th>Rendez-vous</th><th>Origine</th><th>Mise à jour</th></tr>
            </thead>
            <tbody>
                <?php foreach ($personnes as $p): ?>
                    <tr>
                        <td class="g-cell-main">
                            <a class="g-row-link g-person-link" href="<?= e(url('/gestion/personnes/' . $p['id'] . '/')) ?>">
                                <span class="g-avatar is-sm is-<?= $p['has_account'] ? 'ok' : 'neutre' ?>" aria-hidden="true"><?= e($initiales($p['first_name'], $p['last_name'])) ?></span>
                                <strong><?= e($p['first_name'] . ' ' . $p['last_name']) ?></strong>
                            </a>
                            <span><?= $p['has_account'] ? 'Compte client' : 'Sans compte' ?><?= $p['to_reconcile'] ? ' · à rapprocher' : '' ?></span>
                        </td>
                        <td data-label="Coordonnées"><?= e(trim(($p['phone'] ?? '') . ($p['email'] ? ' · ' . $p['email'] : ''), ' ·') ?: '—') ?></td>
                        <td data-label="Dossiers"><?= (int) $p['dossiers'] ?></td>
                        <td class="g-mobile-hide"><?= $p['rendez_vous'] === null ? '—' : (int) $p['rendez_vous'] ?></td>
                        <td class="g-mobile-hide"><?= e(Person::ORIGINES[$p['origin']] ?? $p['origin']) ?></td>
                        <td data-label="Mise à jour"><?= e(g_date($p['updated_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php if ($pages > 1): ?>
    <nav class="g-pager" aria-label="Pages">
        <?php if ($pageCourante > 1): ?><a class="g-btn g-btn-sm" href="<?= e(g_filtre('/gestion/personnes/', ['q' => $q ?: null, 'page' => $pageCourante - 1])) ?>">Précédente</a><?php endif; ?>
        <span class="g-muted">Page <?= (int) $pageCourante ?> sur <?= (int) $pages ?></span>
        <?php if ($pageCourante < $pages): ?><a class="g-btn g-btn-sm" href="<?= e(g_filtre('/gestion/personnes/', ['q' => $q ?: null, 'page' => $pageCourante + 1])) ?>">Suivante</a><?php endif; ?>
    </nav>
<?php endif; ?>
