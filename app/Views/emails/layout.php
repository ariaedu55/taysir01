<?php
/**
 * Gabarit HTML commun des e-mails (styles en ligne, tableaux : compatibles avec les messageries).
 * Voir docs/notifications-email.md et docs/charte-claire.md : en-tête blanc, rouge seulement pour le bouton et le filet.
 *
 * @var string                    $preheader Aperçu affiché dans la liste des messages
 * @var string                    $heading
 * @var list<string>              $paragraphs Texte simple (échappé ici)
 * @var array<string, string>     $details    Tableau libellé => valeur
 * @var string|null               $quote      Message cité (conseiller, client)
 * @var string|null               $quoteBy
 * @var array{0: string, 1: string}|null $button [libellé, URL absolue]
 * @var list<string>              $after      Paragraphes après le bouton
 * @var string                    $audience   client ou agence (pied de message)
 */
$ink = '#0a0a0a';
$muted = '#5f5f5f';
$font = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";
$serif = "Georgia, 'Times New Roman', serif";
$contact = config('site.contact');
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title><?= e($heading) ?></title>
</head>
<body style="margin:0;padding:0;background:#f2f4f8;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;"><?= e($preheader) ?></div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f2f4f8;">
        <tr>
            <td align="center" style="padding:24px 12px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:16px;overflow:hidden;">
                    <tr>
                        <td style="background:#ffffff;padding:22px 28px;border-bottom:1px solid #e3e7ef;">
                            <span style="font-family:<?= $font ?>;font-size:20px;font-weight:800;letter-spacing:2px;color:<?= $ink ?>;">TAYSIR</span>
                            <span style="font-family:<?= $font ?>;font-size:11px;font-weight:600;letter-spacing:1.5px;color:<?= $muted ?>;">&nbsp;ORIENTATION VOYAGE</span>
                        </td>
                    </tr>
                    <tr><td style="height:3px;line-height:3px;font-size:0;background:#ce0f0f;">&nbsp;</td></tr>
                    <tr>
                        <td style="padding:32px 28px 8px;font-family:<?= $font ?>;color:<?= $ink ?>;">
                            <h1 style="margin:0 0 18px;font-family:<?= $serif ?>;font-size:26px;line-height:1.25;font-weight:600;color:<?= $ink ?>;"><?= e($heading) ?></h1>
                            <?php foreach ($paragraphs as $paragraph): ?>
                                <p style="margin:0 0 14px;font-size:15px;line-height:1.6;color:<?= $ink ?>;"><?= nl2br(e($paragraph)) ?></p>
                            <?php endforeach; ?>

                            <?php if ($quote !== null && $quote !== ''): ?>
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:6px 0 18px;">
                                    <tr>
                                        <td style="border-left:3px solid <?= $ink ?>;background:#f2f4f8;border-radius:0 10px 10px 0;padding:14px 16px;font-size:15px;line-height:1.6;color:<?= $ink ?>;">
                                            <?php if ($quoteBy): ?><div style="margin-bottom:6px;font-size:12px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:<?= $muted ?>;"><?= e($quoteBy) ?></div><?php endif; ?>
                                            <?= nl2br(e($quote)) ?>
                                        </td>
                                    </tr>
                                </table>
                            <?php endif; ?>

                            <?php if ($details !== []): ?>
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:6px 0 20px;border:1px solid #e3e7ef;border-radius:12px;">
                                    <?php $i = 0; foreach ($details as $label => $value): ?>
                                        <tr>
                                            <td style="padding:10px 14px;<?= $i++ > 0 ? 'border-top:1px solid #e3e7ef;' : '' ?>font-size:12px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;color:<?= $muted ?>;width:40%;vertical-align:top;"><?= e($label) ?></td>
                                            <td style="padding:10px 14px;<?= $i > 1 ? 'border-top:1px solid #e3e7ef;' : '' ?>font-size:14px;font-weight:600;color:<?= $ink ?>;vertical-align:top;"><?= nl2br(e($value)) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </table>
                            <?php endif; ?>

                            <?php if ($button !== null): ?>
                                <table role="presentation" cellpadding="0" cellspacing="0" style="margin:8px 0 22px;">
                                    <tr>
                                        <td style="border-radius:999px;background:#ce0f0f;">
                                            <a href="<?= e($button[1]) ?>" style="display:inline-block;padding:13px 26px;font-family:<?= $font ?>;font-size:15px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:999px;"><?= e($button[0]) ?></a>
                                        </td>
                                    </tr>
                                </table>
                            <?php endif; ?>

                            <?php foreach ($after as $paragraph): ?>
                                <p style="margin:0 0 14px;font-size:14px;line-height:1.6;color:<?= $muted ?>;"><?= nl2br(e($paragraph)) ?></p>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 28px 26px;border-top:1px solid #e3e7ef;font-family:<?= $font ?>;font-size:12px;line-height:1.6;color:<?= $muted ?>;">
                            <?php if ($audience === 'client'): ?>
                                Taysir Orientation Voyage · <?= e($contact['address']) ?> · <?= e($contact['phone']) ?><br>
                                Vous recevez cet e-mail à la suite de votre démarche sur <a href="<?= e(absolute_url('/')) ?>" style="color:<?= $ink ?>;"><?= e(preg_replace('#^https?://#', '', rtrim((string) config('app.url'), '/'))) ?></a>.
                                <a href="<?= e(absolute_url('/terms-conditions/')) ?>" style="color:<?= $ink ?>;">Confidentialité</a>
                            <?php else: ?>
                                Alerte automatique du portail Taysir. Répondre à cet e-mail écrit directement à la personne concernée.
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
