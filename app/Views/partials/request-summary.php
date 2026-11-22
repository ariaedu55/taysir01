<?php
/**
 * Récapitulatif des informations d'une demande.
 *
 * @var array      $request
 * @var array|null $service
 */
$labels = ['identity_number' => 'Passeport ou CNI'];
foreach ($service['fields'] ?? [] as [$name, $label, $type]) {
    $labels[$name] = $label;
}
$rows = [
    'Nom' => trim(($request['civility'] ? $request['civility'] . ' ' : '') . $request['first_name'] . ' ' . $request['last_name']),
    'E-mail' => $request['email'],
    'Téléphone' => $request['phone'],
];
if ($request['birth_date']) {
    $rows['Date de naissance'] = date('d/m/Y', strtotime($request['birth_date']));
}
if ($request['destination']) {
    $rows[$labels['destination'] ?? 'Destination'] = $request['destination'];
}
foreach ($request['details'] as $key => $value) {
    $rows[$labels[$key] ?? $key] = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value) ? date('d/m/Y', strtotime((string) $value)) : (string) $value;
}
?>
<section class="account glass" aria-labelledby="recap-titre" data-reveal="up">
    <h2 id="recap-titre">Informations transmises</h2>
    <dl class="summary-list">
        <?php foreach ($rows as $label => $value): ?>
            <div><dt><?= e($label) ?></dt><dd><?= e($value) ?></dd></div>
        <?php endforeach; ?>
    </dl>
    <?php if (!empty($request['message'])): ?>
        <h3 class="summary-subtitle">Précisions</h3>
        <p class="summary-message"><?= nl2br(e($request['message'])) ?></p>
    <?php endif; ?>
</section>
