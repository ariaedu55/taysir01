<?php
/**
 * Prise de rendez-vous, ouverte à tous (avec ou sans compte).
 *
 * @var array|null $user
 * @var string     $motive  Objet présélectionné (?motif=)
 * @var string     $minDate
 * @var string     $maxDate
 */
use App\Models\Appointment;

$errors = errors();
$offices = array_filter(config('site.offices'), static fn (array $office) => $office['phone'] !== null);
$choiceGroup = static function (string $name, string $legend, array $choices, string $selected, string $class) use ($errors): void {
    $error = $errors[$name] ?? null;
    $current = old($name, $selected);
    ?>
    <fieldset class="field<?= $error ? ' has-error' : '' ?>"<?= $error ? ' aria-describedby="champ-' . e($name) . '-erreur"' : '' ?>>
        <legend><?= e($legend) ?></legend>
        <div class="<?= e($class) ?>">
            <?php foreach ($choices as $value => $label): ?>
                <label class="profile-option">
                    <input type="radio" name="<?= e($name) ?>" value="<?= e($value) ?>" required<?= $current === $value ? ' checked' : '' ?>>
                    <span><?= e($label) ?></span>
                </label>
            <?php endforeach; ?>
        </div>
        <?php if ($error): ?><p class="field-error" id="champ-<?= e($name) ?>-erreur"><?= e($error) ?></p><?php endif; ?>
    </fieldset>
    <?php
};
?>
<main id="contenu" class="destinations-page service-page">
    <section class="destinations-hero">
        <div class="wrap">
            <nav class="breadcrumb" aria-label="Fil d’Ariane">
                <ol>
                    <li><a href="<?= e(url('/')) ?>">Accueil</a></li>
                    <li aria-current="page">Rendez-vous</li>
                </ol>
            </nav>
            <h1 data-split>Prendre <span class="accent">rendez-vous</span></h1>
            <p class="destinations-lead" data-reveal="up" style="--delay: 300ms">Un conseiller Taysir vous reçoit en agence, vous appelle ou vous retrouve en visio pour faire le point sur votre projet d’études ou de voyage.</p>
        </div>
    </section>

    <div class="wrap service-layout">
        <section class="service-main" id="formulaire" aria-labelledby="formulaire-titre">
            <div class="service-form-head" data-reveal="up">
                <p class="kicker">Formulaire</p>
                <h2 id="formulaire-titre">Votre rendez-vous</h2>
                <p>Choisissez un jour et un moment : l’agence vous confirme l’heure exacte par téléphone ou sur WhatsApp.</p>
            </div>
            <?php partial('flash'); ?>

            <form class="portal-form" method="post" action="<?= e(url('/rendez_vous_orientation/')) ?>" novalidate data-portal-form>
                <?= csrf_field() ?>
                <div class="form-trap" aria-hidden="true">
                    <label for="champ-site-web">Ne pas remplir</label>
                    <input id="champ-site-web" name="site_web" type="text" tabindex="-1" autocomplete="off">
                </div>

                <fieldset class="form-section glass" data-reveal="up">
                    <legend><span>1</span> L’objet du rendez-vous</legend>
                    <?php $choiceGroup('motive', 'De quoi souhaitez-vous parler ?', Appointment::MOTIVES, $motive, 'choice-grid'); ?>
                </fieldset>

                <fieldset class="form-section glass" data-reveal="up">
                    <legend><span>2</span> Le moment et la façon</legend>
                    <?php $choiceGroup('mode', 'Comment souhaitez-vous échanger ?', Appointment::MODES, '', 'choice-grid'); ?>
                    <div class="field-grid">
                        <?php partial('form-field', ['name' => 'preferred_date', 'label' => 'Jour souhaité', 'type' => 'date', 'required' => true, 'min' => $minDate, 'max' => $maxDate, 'hint' => 'Du lundi au samedi ; le samedi, le matin seulement.']); ?>
                        <?php $choiceGroup('preferred_slot', 'Moment de la journée', Appointment::SLOTS, '', 'choice-grid choice-grid-2'); ?>
                    </div>
                </fieldset>

                <fieldset class="form-section glass" data-reveal="up">
                    <legend><span>3</span> Vos coordonnées</legend>
                    <div class="field-grid">
                        <?php partial('form-field', ['name' => 'first_name', 'label' => 'Prénom', 'required' => true, 'autocomplete' => 'given-name', 'value' => $user['first_name'] ?? '']); ?>
                        <?php partial('form-field', ['name' => 'last_name', 'label' => 'Nom', 'required' => true, 'autocomplete' => 'family-name', 'value' => $user['last_name'] ?? '']); ?>
                        <?php partial('form-field', ['name' => 'email', 'label' => 'Adresse e-mail', 'type' => 'email', 'required' => true, 'autocomplete' => 'email', 'value' => $user['email'] ?? '']); ?>
                        <?php partial('form-field', ['name' => 'phone', 'label' => 'Téléphone (WhatsApp)', 'type' => 'tel', 'required' => true, 'autocomplete' => 'tel', 'placeholder' => '+221 77 000 00 00', 'value' => (string) ($user['phone'] ?? '')]); ?>
                        <?php partial('form-field', ['name' => 'message', 'label' => 'Votre message', 'type' => 'textarea', 'hint' => 'Votre niveau, votre projet, vos questions : tout ce qui aidera votre conseiller à préparer l’échange.']); ?>
                    </div>
                </fieldset>

                <div class="form-submit" data-reveal="up">
                    <p><?= $user === null ? 'Astuce : avec un <a href="' . e(url('/inscription/?retour=' . rawurlencode('/rendez_vous_orientation/'))) . '">compte Taysir</a>, vous retrouvez vos rendez-vous dans votre espace.' : 'Ce rendez-vous apparaîtra dans votre espace Taysir.' ?></p>
                    <button class="button primary" type="submit">Demander le rendez-vous</button>
                </div>
            </form>
        </section>

        <aside class="service-aside">
            <div class="article-aside-card glass" data-reveal="right">
                <p class="kicker">Nos agences</p>
                <h2>Horaires</h2>
                <?php partial('opening-hours'); ?>
                <ul class="office-list">
                    <?php foreach ($offices as $office): ?>
                        <li>
                            <strong><?= e($office['city']) ?></strong>
                            <span><?= e($office['label']) ?></span>
                            <a href="tel:<?= e(str_replace(' ', '', $office['phone'])) ?>"><?= e($office['phone']) ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <a class="button outline" href="<?= e(config('site.contact.whatsapp')) ?>">Écrire sur WhatsApp</a>
            </div>
        </aside>
    </div>
</main>
