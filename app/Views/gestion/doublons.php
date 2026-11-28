<?php if ($pair): ?>
    <section class="g-card g-grid">
        <h3>Comparer les fiches</h3>
        <div class="g-grid g-grid-2">
            <?php foreach ($pair as $person): ?>
                <div class="g-requirement">
                    <strong><?= e($person['first_name'] . ' ' . $person['last_name']) ?></strong>
                    <p>Fiche <?= (int) $person['id'] ?></p>
                    <p><?= e($person['email'] ?: 'E-mail absent') ?> · <?= e($person['phone'] ?: 'Téléphone absent') ?></p>
                    <?php if ($person['birth_date']): ?><p>Né(e) le <?= e(g_date($person['birth_date'])) ?></p><?php endif; ?>
                    <a href="<?= e(url('/gestion/personnes/' . $person['id'] . '/')) ?>">Ouvrir la fiche</a>
                </div>
            <?php endforeach; ?>
        </div>
        <form class="g-grid" method="post" action="<?= e(url('/gestion/doublons/')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="source" value="<?= (int) $pair[1]['id'] ?>">
            <input type="hidden" name="target" value="<?= (int) $pair[0]['id'] ?>">
            <label><input type="checkbox" name="confirmed" value="1" required> J’ai confirmé qu’il s’agit de la même personne. Je conserve la fiche <?= (int) $pair[0]['id'] ?>.</label>
            <div><button type="submit" class="g-btn g-btn-danger">Fusionner les fiches</button></div>
        </form>
        <a href="<?= e(url('/gestion/doublons/?a=' . $pair[1]['id'] . '&b=' . $pair[0]['id'])) ?>">Conserver l’autre fiche</a>
    </section>
<?php endif; ?>
<section class="g-card">
    <div class="g-card-head"><h3>Correspondances possibles</h3></div>
    <?php if (!$pairs): ?><p class="g-empty">Aucune correspondance détectée.</p><?php endif; ?>
    <ul class="g-tasks">
        <?php foreach ($pairs as $item): ?>
            <li class="g-task g-task-checklist-row">
                <div><strong><?= e($item['first_given'] . ' ' . $item['first_family']) ?></strong><p><?= e($item['second_given'] . ' ' . $item['second_family']) ?></p></div>
                <a class="g-btn g-btn-primary g-btn-sm" href="<?= e(url('/gestion/doublons/?a=' . $item['first_id'] . '&b=' . $item['second_id'])) ?>">Comparer</a>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
