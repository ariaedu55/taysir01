<?php
/**
 * Message de l'équipe à une personne (phase 7, lot 7A) : modèle, texte rempli et modifiable, ouverture de WhatsApp
 * ou de l'e-mail (journalisée, sans le texte).
 *
 * @var array       $personne
 * @var array|null  $dossier
 * @var list<array> $modeles, $historique
 * @var array|null  $choisi
 * @var string      $texte
 * @var list<string> $manquants Champs du modèle restés vides
 * @var string|null $whatsapp Numéro au format international, ou null
 * @var string|null $email
 */
use App\Models\MessageTemplate;

$errors = errors();
$base = '/gestion/personnes/' . $personne['id'] . '/message/';
$suffixe = $dossier ? '&dossier=' . rawurlencode($dossier['reference']) : '';
?>
<p class="g-back"><a class="g-btn g-btn-sm" href="<?= e(url($dossier ? '/gestion/dossiers/' . $dossier['reference'] . '/' : '/gestion/personnes/' . $personne['id'] . '/')) ?>"><?= gestion_icone('collapse', 15) ?> <?= $dossier ? 'Dossier ' . e($dossier['reference']) : 'Fiche' ?></a></p>

<div class="g-grid g-grid-2">
    <section class="g-card is-ok" aria-labelledby="msg-titre">
        <div class="g-card-head"><h3 id="msg-titre"><span class="g-head-icon" aria-hidden="true"><?= gestion_icone('chat', 18) ?></span>Message à <?= e($personne['first_name'] . ' ' . $personne['last_name']) ?></h3></div>
        <fieldset class="g-choice">
            <legend>Modèle</legend>
            <?php foreach ($modeles as $m): ?>
                <a class="g-choice-card <?= $choisi && (int) $choisi['id'] === (int) $m['id'] ? 'is-ok' : 'is-neutre' ?>" href="<?= e(url($base) . '?modele=' . (int) $m['id'] . $suffixe) ?>"><span><strong><?= e($m['title']) ?></strong><small><?= e(MessageTemplate::CANAUX[$m['channel']]) ?></small></span></a>
            <?php endforeach; ?>
        </fieldset>
        <form method="post" action="<?= e(url($base)) ?>" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="modele" value="<?= $choisi ? (int) $choisi['id'] : '' ?>">
            <?php if ($dossier): ?><input type="hidden" name="dossier" value="<?= e($dossier['reference']) ?>"><?php endif; ?>
            <label class="g-form-row">Texte (modifiable avant l’envoi)
                <textarea class="g-input" name="texte" rows="6" maxlength="2000"><?= e(old('texte', $texte)) ?></textarea>
            </label>
            <?php if ($manquants !== []): ?>
                <p class="g-notice"><?= gestion_icone('alert', 16) ?> Information absente pour <?= e(implode(', ', $manquants)) ?> : complétez le texte avant l’envoi.</p>
            <?php endif; ?>
            <?php if (isset($errors['message'])): ?><p class="g-field-error"><?= e($errors['message']) ?></p><?php endif; ?>
            <div class="g-case-tools">
                <?php if ($whatsapp): ?>
                    <button class="g-btn g-btn-success" name="canal" value="whatsapp" type="submit"><?= gestion_icone('chat', 16) ?> Ouvrir WhatsApp</button>
                <?php endif; ?>
                <?php if ($email): ?>
                    <button class="g-btn g-btn-primary" name="canal" value="email" type="submit"><?= gestion_icone('send', 16) ?> Ouvrir l’e-mail</button>
                <?php endif; ?>
            </div>
            <?php if (!$whatsapp && !$email): ?><p class="g-muted g-form-note">Aucun numéro ni e-mail valide sur la fiche : complétez-la d’abord.</p><?php endif; ?>
            <p class="g-muted g-form-note">Le message part depuis votre WhatsApp ou votre messagerie ; Taysir garde seulement la trace de l’ouverture.</p>
        </form>
    </section>

    <section class="g-card is-neutre" aria-labelledby="hist-titre">
        <div class="g-card-head"><h3 id="hist-titre"><span class="g-head-icon" aria-hidden="true"><?= gestion_icone('clock', 18) ?></span>Derniers messages</h3></div>
        <?php if ($historique === []): ?><p class="g-empty">Aucun message ouvert pour cette personne.</p><?php else: ?>
            <ul class="g-etapes">
                <?php foreach ($historique as $h): ?>
                    <li class="g-etape"><span><?= g_badge($h['channel'] === 'whatsapp' ? 'WhatsApp' : 'E-mail', $h['channel'] === 'whatsapp' ? 'green' : 'blue') ?></span>
                        <div><strong><?= e($h['title'] ?? 'Message libre') ?></strong><small><?= e($h['first_name'] . ' ' . $h['last_name']) ?> · <?= e(g_date($h['created_at'], true)) ?><?= $h['reference'] ? ' · ' . e($h['reference']) : '' ?></small></div></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
