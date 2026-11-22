<?php
/**
 * Page Contact : agences, horaires, formulaire enregistré en base (back-office /admin/messages/).
 *
 * @var array|null  $user
 * @var list<array> $offices
 */
$whatsapp = config('site.contact.whatsapp');
$mapUrl = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode('Taysir Orientation Voyage, Rond-point JVC, Dakar');
?>
<main id="contenu" class="destinations-page agency-page">
    <section class="destinations-hero">
        <div class="wrap">
            <nav class="breadcrumb" aria-label="Fil d’Ariane">
                <ol>
                    <li><a href="<?= e(url('/')) ?>">Accueil</a></li>
                    <li>L’agence</li>
                    <li aria-current="page">Contact</li>
                </ol>
            </nav>
            <h1 data-split>Parlons de <span class="accent">votre projet</span></h1>
            <p class="destinations-lead" data-reveal="up" style="--delay: 300ms">Études à l’étranger, langue, voyage : nous sommes là pour vous aider à réaliser vos projets, avec un accompagnement adapté à chacun.</p>
        </div>
    </section>

    <section class="wrap contact-quick" aria-label="Nous joindre rapidement" data-stagger>
        <a class="contact-tile glass" data-glow data-reveal="up" href="<?= e($whatsapp) ?>">
            <span class="contact-tile-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3.5 20.5 5 16a8.5 8.5 0 1 1 3.2 3.1Z"/><path d="M9 9.5c.3 2.4 2.3 4.9 5.2 5.5l1.3-1.3-1.8-1-1 .8c-1-.5-1.9-1.4-2.4-2.4l.8-1-1-1.8Z"/></svg></span>
            <strong>WhatsApp</strong><span>La réponse la plus rapide</span>
        </a>
        <a class="contact-tile glass" data-glow data-reveal="up" href="tel:<?= e(str_replace(' ', '', $offices[0]['phone'])) ?>">
            <span class="contact-tile-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/></svg></span>
            <strong><?= e($offices[0]['phone']) ?></strong><span>Ligne d’assistance, Dakar</span>
        </a>
        <a class="contact-tile glass" data-glow data-reveal="up" href="<?= e(url('/rendez_vous_orientation/')) ?>">
            <span class="contact-tile-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></svg></span>
            <strong>Rendez-vous</strong><span>En agence, au téléphone ou en visio</span>
        </a>
    </section>

    <div class="wrap service-layout contact-layout">
        <section class="service-main" id="formulaire" aria-labelledby="formulaire-titre">
            <div class="service-form-head" data-reveal="up">
                <p class="kicker">Formulaire</p>
                <h2 id="formulaire-titre">Écrivez-nous</h2>
                <p>Une question, une demande d’information ? Laissez-nous un message : un conseiller vous répond par e-mail ou par téléphone.</p>
            </div>
            <?php partial('flash'); ?>
            <form class="portal-form" method="post" action="<?= e(url('/contact/')) ?>" novalidate data-portal-form>
                <?= csrf_field() ?>
                <div class="form-trap" aria-hidden="true">
                    <label for="champ-site-web">Ne pas remplir</label>
                    <input id="champ-site-web" name="site_web" type="text" tabindex="-1" autocomplete="off">
                </div>
                <div class="form-section glass" data-reveal="up">
                    <div class="field-grid">
                        <?php partial('form-field', ['name' => 'name', 'label' => 'Nom complet', 'required' => true, 'autocomplete' => 'name', 'value' => $user ? $user['first_name'] . ' ' . $user['last_name'] : '']); ?>
                        <?php partial('form-field', ['name' => 'phone', 'label' => 'Téléphone (WhatsApp)', 'type' => 'tel', 'required' => true, 'autocomplete' => 'tel', 'placeholder' => '+221 77 000 00 00', 'value' => (string) ($user['phone'] ?? '')]); ?>
                        <?php partial('form-field', ['name' => 'email', 'label' => 'Adresse e-mail', 'type' => 'email', 'required' => true, 'autocomplete' => 'email', 'value' => $user['email'] ?? '']); ?>
                        <?php partial('form-field', ['name' => 'message', 'label' => 'Votre message', 'type' => 'textarea', 'required' => true]); ?>
                    </div>
                </div>
                <div class="form-submit" data-reveal="up">
                    <p>Vos informations servent uniquement à vous répondre. <a href="<?= e(url('/terms-conditions/')) ?>">Confidentialité</a></p>
                    <button class="button primary" type="submit">Envoyer le message</button>
                </div>
            </form>
        </section>

        <aside class="service-aside">
            <div class="article-aside-card glass" data-reveal="right">
                <p class="kicker">Horaires</p>
                <h2>Nos agences</h2>
                <?php partial('opening-hours'); ?>
                <ul class="office-list">
                    <?php foreach ($offices as $office): ?>
                        <li>
                            <strong><?= e($office['city']) ?></strong>
                            <span><?= e($office['label']) ?><?= $office['address'] ? ' · ' . e($office['address']) : '' ?></span>
                            <?php if ($office['phone']): ?><a href="tel:<?= e(str_replace(' ', '', $office['phone'])) ?>"><?= e($office['phone']) ?></a><?php endif; ?>
                            <a href="mailto:<?= e($office['email']) ?>"><?= e($office['email']) ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <a class="button light" href="<?= e($mapUrl) ?>" rel="noopener" target="_blank">Itinéraire vers l’agence de Dakar</a>
            </div>
        </aside>
    </div>
</main>
