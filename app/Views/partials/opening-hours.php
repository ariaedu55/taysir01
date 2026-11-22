<?php
/** Horaires d'ouverture de l'agence (config/site.php). */
?>
<dl class="opening-hours">
    <?php foreach (config('site.hours') as [$days, $hours]): ?>
        <div><dt><?= e($days) ?></dt><dd><?= e($hours) ?></dd></div>
    <?php endforeach; ?>
</dl>
