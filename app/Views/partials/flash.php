<?php
use App\Core\Session;

$messages = array_filter([
    'success' => Session::get('_success'),
    'notice' => Session::get('_notice'),
]);
?>
<?php foreach ($messages as $type => $message): ?>
    <p class="flash flash-<?= e($type) ?>" role="status">
        <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><?= $type === 'success' ? '<path d="M20 6 9 17l-5-5"/>' : '<circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/>' ?></svg>
        <?= e($message) ?>
    </p>
<?php endforeach; ?>
