<?php
use App\Models\Task;
use App\Models\User;

$errors = errors();
?>
<details class="g-card"<?= isset($errors['task']) ? ' open' : '' ?>>
    <summary>Créer une tâche</summary>
    <form method="post" action="<?= e(url('/gestion/taches/')) ?>" class="g-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="form_token" value="<?= e(Task::formToken($me)) ?>">
        <?php if (isset($personId)): ?><input type="hidden" name="person_id" value="<?= (int) $personId ?>"><?php endif; ?>
        <?php if (isset($requestId)): ?><input type="hidden" name="request_id" value="<?= (int) $requestId ?>"><?php endif; ?>
        <label class="g-form-row">Intitulé
            <input class="g-input" name="title" maxlength="190" required aria-describedby="task-title-hint">
        </label>
        <small id="task-title-hint">Indiquez l’action à faire. Évitez les noms, références et données personnelles.</small>
        <label class="g-form-row">Échéance <input class="g-input" name="due_date" type="date"></label>
        <?php if (in_array($me['role'], ['admin', 'secretaire'], true)): ?>
            <label class="g-form-row">Responsable
                <select class="g-select" name="assigned_to">
                    <option value="<?= (int) $me['id'] ?>">Moi</option>
                    <?php foreach (User::staff(true) as $staff): if ((int) $staff['id'] === (int) $me['id']) continue; ?>
                        <option value="<?= (int) $staff['id'] ?>"><?= e($staff['first_name'] . ' ' . $staff['last_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php endif; ?>
        <?php if (isset($errors['task'])): ?><p class="g-field-error" role="alert"><?= e($errors['task']) ?></p><?php endif; ?>
        <button class="g-btn g-btn-success" type="submit">Enregistrer la tâche</button>
    </form>
</details>
