<?php
/**
 * « Documents à préparer » d'une démarche (phase 12) : formulaire public et réponse du point d'accès /demarches/pieces/.
 *
 * @var list<array{titre: string, aide: ?string, obligatoire: bool, fournisseur: string}> $pieces
 * @var string|null $libelle « Visa · France · Études », ou null pour le modèle général
 */
use App\Services\Demarches;
?>
<?php if ($pieces === []): ?>
    <p class="demarche-vide">Votre conseiller vous indiquera les documents à fournir pour votre situation, dès réception de la demande.</p>
<?php else: ?>
    <p class="demarche-titre"><strong>Documents à préparer</strong><?= $libelle ? ' · ' . e($libelle) : '' ?></p>
    <ul class="demarche-liste">
        <?php foreach ($pieces as $p): ?>
            <li>
                <span class="demarche-coche" aria-hidden="true"></span>
                <span><strong><?= e($p['titre']) ?></strong><?= $p['obligatoire'] ? '' : ' <em>(si possible)</em>' ?>
                    <?php if ($p['aide'] || $p['fournisseur'] !== 'etudiant'): ?><small><?= e(implode(' · ', array_filter([$p['aide'], $p['fournisseur'] !== 'etudiant' ? 'Fourni par ' . mb_strtolower(Demarches::FOURNISSEURS[$p['fournisseur']]) : null]))) ?></small><?php endif; ?></span>
            </li>
        <?php endforeach; ?>
    </ul>
    <p class="demarche-note">Pas besoin de tout envoyer maintenant : après votre demande, chaque document sera à déposer dans votre espace, un par un.</p>
<?php endif; ?>
