<?php
/**
 * Liste « À faire maintenant » de « Ma journée » (incluse par journee.php).
 * Chaque tâche porte un libellé de niveau explicite (En retard, À traiter, Pour information) en plus de sa couleur.
 *
 * @var array    $j       Données du poste
 * @var array    $niveaux Libellés des niveaux
 * @var callable $cible   Adresse d'une tâche
 */
?>
<?php if (empty($j['taches'])): ?>
    <p class="g-empty">Rien d’urgent pour l’instant. Les nouvelles tâches apparaîtront ici.</p>
<?php else: ?>
    <ul class="g-tasks">
        <?php foreach ($j['taches'] as [$ton, $titre, $texte, $action, $ref]): ?>
            <li class="g-task is-<?= e($ton) ?>">
                <div><span class="g-level is-<?= e($ton) ?>"><?= e($niveaux[$ton]) ?></span><strong><?= e($titre) ?></strong><p><?= e($texte) ?></p></div>
                <div class="g-task-actions">
                    <a class="g-btn g-btn-sm<?= $ton === 'info' ? '' : ' g-btn-primary' ?>" href="<?= e($cible($ref)) ?>"<?= $ref ? '' : ' data-g-soon' ?>><?= e($action) ?> <?= gestion_icone('arrow', 15) ?></a>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
