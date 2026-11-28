<?php $groups = [[], []]; foreach ($tasks as $task) { $groups[(int) ($task['done_at'] !== null)][] = $task; } ?>
<section class="g-card is-ok" aria-labelledby="tasks-title">
    <div class="g-card-head"><h3 id="tasks-title"><?= gestion_icone('check', 18) ?> Tâches</h3></div>
    <?php if (!$groups[0]): ?><p class="g-empty">Aucune tâche ouverte.</p><?php endif; ?>
    <?php foreach ($groups as $closed => $group): ?>
        <?php if (!$group) continue; ?>
        <?php if ($closed): ?><details><summary>Tâches clôturées (<?= count($group) ?>)</summary><?php endif; ?>
        <ul class="g-tasks">
            <?php foreach ($group as $task): ?>
                <li class="g-task g-task-checklist-row">
                    <div>
                        <strong><?= e($task['title']) ?></strong>
                        <?php if (!$closed): ?>
                            <p><?= !$task['due_date'] ? 'Sans échéance' : ($task['due_date'] === $today ? 'Aujourd’hui' : e(g_date($task['due_date']))) ?>
                                <?php if ($task['due_date'] && $task['due_date'] < $today): ?><?= g_badge('En retard', 'urgent') ?><?php endif; ?>
                            </p>
                        <?php endif; ?>
                        <?php if ($task['link']): ?><a href="<?= e(url($task['link'])) ?>">Ouvrir la fiche liée</a><?php endif; ?>
                    </div>
                    <?php if ($task['can_toggle']): ?>
                        <form method="post" action="<?= e(url('/gestion/taches/' . $task['id'] . '/etat/')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="done" value="<?= $closed ? '0' : '1' ?>">
                            <button class="g-btn g-btn-sm <?= $closed ? 'g-btn-primary' : 'g-btn-success' ?>" type="submit"><?= $closed ? 'Rouvrir' : 'Terminer' ?></button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($closed): ?></details><?php endif; ?>
    <?php endforeach; ?>
</section>
