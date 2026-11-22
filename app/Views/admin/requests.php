<?php
/**
 * Back-office : liste des demandes, filtrable par statut et par service.
 *
 * @var list<array>        $requests
 * @var array<string, int> $counts
 * @var list<array>        $services
 * @var string|null        $status
 * @var string|null        $service
 * @var string|null        $assigned          moi, aucun ou null
 * @var array{0: int, 1: int} $assignmentCounts Demandes ouvertes : les miennes, sans conseiller
 */
use App\Models\ServiceRequest;

$serviceNames = array_column($services, 'short', 'key');
$filterUrl = static function (?string $status, ?string $service, ?string $assignee = null) use (&$assigned): string {
    $query = http_build_query(array_filter(['statut' => $status, 'service' => $service, 'conseiller' => func_num_args() > 2 ? $assignee : $assigned]));

    return url('/admin/demandes/') . ($query !== '' ? '?' . $query : '');
};
?>
<main id="contenu" class="dashboard portal admin">
    <section class="wrap dashboard-head">
        <div>
            <?php partial('admin-nav', ['active' => 'demandes']); ?>
            <h1>Demandes <em>en ligne</em></h1>
            <p class="dashboard-profile"><?= array_sum($counts) ?> demande(s) au total · <?= (int) ($counts['nouvelle'] ?? 0) ?> nouvelle(s)</p>
        </div>
    </section>

    <div class="wrap portal-stack">
        <?php partial('flash'); ?>

        <div class="filters" role="group" aria-label="Filtrer par conseiller">
            <a class="filter<?= $assigned === null ? ' is-active' : '' ?>" href="<?= e($filterUrl($status, $service, null)) ?>">Toute l’équipe</a>
            <a class="filter<?= $assigned === 'moi' ? ' is-active' : '' ?>" href="<?= e($filterUrl($status, $service, 'moi')) ?>">Mes demandes <span><?= $assignmentCounts[0] ?></span></a>
            <a class="filter<?= $assigned === 'aucun' ? ' is-active' : '' ?>" href="<?= e($filterUrl($status, $service, 'aucun')) ?>">Non attribuées <span><?= $assignmentCounts[1] ?></span></a>
        </div>
        <div class="filters" role="group" aria-label="Filtrer par statut">
            <a class="filter<?= $status === null ? ' is-active' : '' ?>" href="<?= e($filterUrl(null, $service)) ?>">Toutes</a>
            <?php foreach (ServiceRequest::STATUSES as $key => [, $short]): ?>
                <a class="filter<?= $status === $key ? ' is-active' : '' ?>" href="<?= e($filterUrl($key, $service)) ?>"><?= e($short) ?> <span><?= (int) ($counts[$key] ?? 0) ?></span></a>
            <?php endforeach; ?>
        </div>
        <div class="filters" role="group" aria-label="Filtrer par service">
            <a class="filter<?= $service === null ? ' is-active' : '' ?>" href="<?= e($filterUrl($status, null)) ?>">Tous les services</a>
            <?php foreach ($serviceNames as $key => $name): ?>
                <a class="filter<?= $service === $key ? ' is-active' : '' ?>" href="<?= e($filterUrl($status, $key)) ?>"><?= e($name) ?></a>
            <?php endforeach; ?>
        </div>

        <section class="account glass" aria-label="Demandes">
            <?php if ($requests === []): ?>
                <p class="portal-empty">Aucune demande pour ce filtre.</p>
            <?php else: ?>
                <div class="table-scroll">
                    <table>
                        <thead><tr><th scope="col">Référence</th><th scope="col">Service</th><th scope="col">Client</th><th scope="col">Destination</th><th scope="col">Docs</th><th scope="col">Conseiller</th><th scope="col">Reçue</th><th scope="col">Mise à jour</th><th scope="col">Statut</th></tr></thead>
                        <tbody>
                            <?php foreach ($requests as $request): ?>
                                <tr>
                                    <td><a class="table-link" href="<?= e(url('/admin/demandes/' . $request['reference'] . '/')) ?>"><?= e($request['reference']) ?></a></td>
                                    <td><?= e($serviceNames[$request['service']] ?? $request['service']) ?></td>
                                    <td><?= e($request['first_name'] . ' ' . $request['last_name']) ?><br><span class="table-muted"><?= e($request['phone']) ?></span></td>
                                    <td><?= e($request['destination'] ?? '—') ?></td>
                                    <td><?= (int) $request['document_count'] ?></td>
                                    <td><?= $request['assignee_first_name'] ? e($request['assignee_first_name'] . ' ' . substr((string) $request['assignee_last_name'], 0, 1) . '.') : '<span class="table-muted">—</span>' ?></td>
                                    <td><?= e(date('d/m/Y', strtotime($request['created_at']))) ?></td>
                                    <td><?= e(date('d/m H\hi', strtotime($request['updated_at']))) ?></td>
                                    <td><?php partial('status', ['label' => ServiceRequest::statusLabel($request['status'], true), 'tone' => ServiceRequest::statusTone($request['status'])]); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>
</main>
