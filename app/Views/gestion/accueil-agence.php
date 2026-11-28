<div class="g-grid g-grid-2">
    <section class="g-card is-rdv" aria-labelledby="visites">
        <div class="g-card-head"><h3 id="visites">Visites du jour</h3></div>
        <?php if (!$visits): ?><p class="g-empty">Aucune visite enregistrée aujourd’hui.</p><?php endif; ?>
        <ul class="g-tasks">
            <?php foreach ($visits as $visit): ?>
                <li class="g-task g-task-checklist-row">
                    <div>
                        <strong><?= e($visit['first_name'] . ' ' . $visit['last_name']) ?></strong>
                        <p><?= e(substr($visit['created_at'], 11, 5)) ?> · <?= e($visit['purpose']) ?><?= $visit['phone'] ? ' · ' . e($visit['phone']) : '' ?><?= $visit['email'] ? ' · ' . e($visit['email']) : '' ?></p>
                        <?= g_badge(App\Models\Reception::STATES[$visit['state']], $visit['state'] === 'attente' ? 'ech' : 'ok') ?>
                        <?php if ($visit['person_id']): ?><p><a href="<?= e(url('/gestion/personnes/' . $visit['person_id'] . '/')) ?>">Ouvrir la fiche</a></p><?php endif; ?>
                    </div>
                    <?php if ($visit['state'] !== 'termine'): ?>
                        <form method="post" action="<?= e(url('/gestion/accueil/' . $visit['id'] . '/')) ?>">
                            <?= csrf_field() ?>
                            <button class="g-btn g-btn-sm g-btn-success" name="action" value="<?= $visit['state'] === 'attente' ? 'recu' : 'termine' ?>"><?= $visit['state'] === 'attente' ? 'Recevoir' : 'Terminer' ?></button>
                        </form>
                    <?php endif; ?>
                    <?php if (!$visit['person_id']): ?>
                        <?php $proches = App\Models\Reception::suggestions($visit); ?>
                        <details<?= $proches ? ' open' : '' ?>>
                            <summary><?= $proches ? 'Fiches qui lui ressemblent (' . count($proches) . ')' : 'Créer sa fiche' ?></summary>
                            <form class="g-grid" method="post" action="<?= e(url('/gestion/accueil/' . $visit['id'] . '/')) ?>">
                                <?= csrf_field() ?>
                                <fieldset class="g-choice">
                                    <legend>Rattacher la visite à</legend>
                                    <?php foreach ($proches as $fiche): ?>
                                        <label class="g-choice-card is-rdv"><input type="radio" name="person_id" value="<?= (int) $fiche['id'] ?>"><span><strong><?= e($fiche['first_name'] . ' ' . $fiche['last_name']) ?></strong><small><?= e(trim(($fiche['phone'] ?? '') . ' · ' . ($fiche['email'] ?? ''), ' ·') ?: 'Sans coordonnées') ?> · fiche du <?= e(date('d/m/Y', strtotime($fiche['created_at']))) ?></small></span></label>
                                    <?php endforeach; ?>
                                    <label class="g-choice-card is-ok"><input type="radio" name="person_id" value=""<?= $proches ? '' : ' checked' ?>><span><strong>Créer une nouvelle fiche</strong><small>Avec le nom et les coordonnées de la visite.</small></span></label>
                                </fieldset>
                                <div><button class="g-btn g-btn-sm g-btn-primary" name="action" value="convertir">Valider</button></div>
                            </form>
                        </details>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <section class="g-card" aria-labelledby="nouvelle-visite">
        <div class="g-card-head"><h3 id="nouvelle-visite">Enregistrer une visite</h3></div>
        <form class="g-grid" method="post" action="<?= e(url('/gestion/accueil/')) ?>">
            <?= csrf_field() ?>
            <label class="g-form-row">Prénom <input class="g-input" name="first_name" maxlength="80" required></label>
            <label class="g-form-row">Nom <input class="g-input" name="last_name" maxlength="80" required></label>
            <label class="g-form-row">Téléphone <input class="g-input" name="phone" type="tel" maxlength="30"></label>
            <label class="g-form-row">E-mail (facultatif si téléphone) <input class="g-input" name="email" type="email" maxlength="190"></label>
            <label class="g-form-row">Objet de la visite <input class="g-input" name="purpose" maxlength="190" required></label>
            <div><button class="g-btn g-btn-success" type="submit">Enregistrer la visite</button></div>
        </form>
    </section>
</div>
