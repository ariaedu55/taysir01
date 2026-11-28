<label class="g-form-row">Établissement
    <select class="g-select" name="school" required>
        <option value="">Choisir un établissement</option>
        <?php foreach ($schools as $entry): ?><option value="<?= (int) $entry['id'] ?>"><?= e($entry['name'] . ' · ' . $entry['city'] . ' · ' . $entry['agency']) ?></option><?php endforeach; ?>
    </select>
</label>
<label class="g-form-row">Campagne
    <select class="g-select" name="campaign" required>
        <option value="">Choisir une campagne</option>
        <?php foreach ($campaigns as $entry): ?><option value="<?= (int) $entry['id'] ?>"><?= e($entry['name'] . ' · ' . $entry['agency'] . ' · ' . g_date($entry['starts_on'])) ?></option><?php endforeach; ?>
    </select>
</label>
