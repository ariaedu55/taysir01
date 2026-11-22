<?php
/**
 * Historique d'une demande.
 *
 * @var list<array> $events
 * @var bool        $staffView  true : notes internes et auteurs affichés (back-office)
 */
use App\Models\ServiceRequest;
?>
<ol class="timeline">
    <?php foreach ($events as $event): ?>
        <?php
        $fromClient = ($event['author_role'] ?? null) === 'client';
        $title = match ($event['type']) {
            'creation' => 'Demande envoyée',
            'statut' => ServiceRequest::statusLabel((string) $event['status']),
            default => $fromClient ? ($staffView ? 'Message du client' : 'Votre message') : 'Message de votre conseiller',
        };
        if ($staffView && $event['type'] === 'note' && !$fromClient) {
            $title = $event['visible'] ? 'Note envoyée au client' : 'Note interne';
        }
        $tone = $event['type'] === 'statut' ? ServiceRequest::statusTone((string) $event['status']) : ($fromClient ? 'client' : 'team');
        ?>
        <li class="timeline-item timeline-<?= e($tone) ?><?= !$event['visible'] ? ' is-internal' : '' ?>">
            <div class="timeline-head">
                <strong><?= e($title) ?></strong>
                <time datetime="<?= e(date('c', strtotime($event['created_at']))) ?>"><?= e(date('d/m/Y à H\hi', strtotime($event['created_at']))) ?></time>
            </div>
            <?php if ($event['type'] !== 'creation' && !empty($event['message'])): ?>
                <p><?= nl2br(e($event['message'])) ?></p>
            <?php endif; ?>
            <?php if ($staffView && !empty($event['author_first_name']) && $event['type'] !== 'creation'): ?>
                <span class="timeline-author">Par <?= e($event['author_first_name']) ?></span>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
</ol>
