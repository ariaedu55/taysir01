<?php
[$label, $tone] = match ($state) {
    'deposee' => ['À vérifier', 'blue'],
    'validee' => ['Validée', 'green'],
    'refusee' => ['À corriger', 'amber'],
    'expiree' => ['À renouveler', 'amber'],
    'non-demandee' => ['Non demandée', 'grey'],
    default => ['À fournir', 'grey'],
};
partial('status', ['label' => $label, 'tone' => $tone]);
