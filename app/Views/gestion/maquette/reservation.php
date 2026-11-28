<?php
/**
 * Maquette 2.0 : réservation publique d'un créneau (docs/gestion-phase-2.md, décision D3, exigences C2 et C6).
 * - Choix direct parmi des heures libres ; aucun nom de membre affiché (attribution automatique).
 * - « Créneau déjà pris, voici les suivants » ; « aucun créneau ne convient » → demande à confirmer.
 * - Visio : heure de Dakar et heure de la personne (fuseau du navigateur).
 * En production, ce choix direct reste désactivé tant que le SMTP et des disponibilités réelles manquent (C9).
 * Données fictives.
 *
 * @var array $motifs, $creneauxPublics
 */
$base = '/gestion/maquette';
$jours = ['', 'lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.', 'dim.'];
$mois = ['', 'janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
$directs = ['orientation-filieres', 'orientation-metiers', 'renseignement'];
$modes = ['agence-dakar' => ['À l’agence de Dakar', 'building'], 'telephone' => ['Par téléphone', 'phone'], 'whatsapp' => ['Par WhatsApp', 'chat'], 'visio' => ['En visio', 'eye']];
?>
<main id="contenu" class="c-space">
    <div class="c-wrap c-booking">
        <p class="c-mock"><?= gestion_icone('alert', 15) ?> Maquette, données fictives ·
            <a href="<?= e(url($base . '/agenda/')) ?>">Agenda de l’équipe</a> ·
            <a href="<?= e(url($base . '/client/')) ?>">Espace client</a>
        </p>
        <header class="c-hello">
            <h1>Prendre rendez-vous</h1>
            <p>Choisissez l’objet, la façon de se parler et l’heure qui vous convient. Confirmation immédiate par e-mail.</p>
        </header>

        <form onsubmit="return false" data-c-booking>
            <section class="c-card c-step" aria-labelledby="etape-1">
                <h2 id="etape-1" class="c-step-title"><span>1</span>Objet du rendez-vous</h2>
                <div class="c-options">
                    <?php foreach ($directs as $i => $cle): ?>
                        <label class="c-option"><input type="radio" name="motif" value="<?= e($cle) ?>"<?= $i === 0 ? ' checked' : '' ?>>
                            <span><strong><?= e($motifs[$cle][0]) ?></strong><small><?= (int) $motifs[$cle][1] ?> min</small></span></label>
                    <?php endforeach; ?>
                </div>
                <p class="c-hint">Admission, visa ou logement ? <a href="#demande" data-c-demande>Envoyez une demande</a> : un conseiller vous propose un créneau adapté.</p>
            </section>

            <section class="c-card c-step" aria-labelledby="etape-2">
                <h2 id="etape-2" class="c-step-title"><span>2</span>Comment souhaitez-vous échanger ?</h2>
                <div class="c-options">
                    <?php foreach ($modes as $cle => [$libelle, $icone]): ?>
                        <label class="c-option"><input type="radio" name="mode" value="<?= e($cle) ?>"<?= $cle === 'agence-dakar' ? ' checked' : '' ?>>
                            <span><strong><?= gestion_icone($icone, 16) ?> <?= e($libelle) ?></strong></span></label>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="c-card c-step" aria-labelledby="etape-3">
                <h2 id="etape-3" class="c-step-title"><span>3</span>Jour et heure</h2>
                <p class="c-tz" data-c-tz hidden><?= gestion_icone('clock', 15) ?><span>Heures affichées : <strong>heure de Dakar</strong> et <strong data-c-tz-nom>votre heure</strong>.</span></p>
                <div class="c-days" role="tablist" aria-label="Jours disponibles">
                    <?php foreach ($creneauxPublics as $i => [$date, $heures]): ?>
                        <button type="button" role="tab" class="c-day" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>" data-c-day="<?= $i ?>">
                            <small><?= e($jours[(int) $date->format('N')]) ?></small><strong><?= e($date->format('j')) ?></strong><small><?= e($mois[(int) $date->format('n')]) ?></small>
                        </button>
                    <?php endforeach; ?>
                </div>
                <p class="c-alert" data-c-pris hidden role="alert"><?= gestion_icone('alert', 16) ?> Ce créneau vient d’être réservé par quelqu’un d’autre. Voici les heures encore libres.</p>
                <?php foreach ($creneauxPublics as $i => [$date, $heures]): ?>
                    <div class="c-times" data-c-times="<?= $i ?>"<?= $i === 0 ? '' : ' hidden' ?>>
                        <?php foreach ($heures as $k => $h): ?>
                            <button type="button" class="c-time" data-c-time="<?= e($date->format('Y-m-d') . ' ' . $h) ?>" data-pris="<?= $i === 0 && $k === 1 ? '1' : '0' ?>">
                                <strong><?= e($h) ?></strong><small data-c-local></small>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
                <p class="c-hint">Aucun horaire ne convient ? <a href="#demande" data-c-demande>Proposez vos disponibilités</a> : nous vous rappelons pour fixer l’heure.</p>
            </section>

            <section class="c-card c-step" aria-labelledby="etape-4">
                <h2 id="etape-4" class="c-step-title"><span>4</span>Vos coordonnées</h2>
                <p class="c-chosen" data-c-chosen>Choisissez une heure ci-dessus.</p>
                <div class="c-fields">
                    <label>Prénom et nom<input type="text" autocomplete="name" placeholder="Prénom Nom"></label>
                    <label>Téléphone<input type="tel" autocomplete="tel" placeholder="77 000 00 00"></label>
                    <label class="c-wide">E-mail<input type="email" autocomplete="email" placeholder="vous@exemple.com"></label>
                </div>
                <button class="c-btn c-btn-success c-submit" type="submit" data-c-submit disabled><?= gestion_icone('check', 17) ?> Confirmer le rendez-vous</button>
            </section>

            <section class="c-card c-step" id="demande" aria-labelledby="etape-demande" hidden data-c-demande-bloc>
                <h2 id="etape-demande" class="c-step-title"><span>?</span>Demande de rendez-vous</h2>
                <p class="c-hint" style="margin-top:0">Indiquez vos disponibilités : l’accueil vous confirme un créneau, en général sous 24 h ouvrées.</p>
                <div class="c-fields">
                    <label>Jour souhaité<input type="date"></label>
                    <label>Moment<select><option>Matin (9 h – 13 h)</option><option>Après-midi (14 h – 18 h)</option></select></label>
                </div>
                <button class="c-btn c-btn-light" type="button" onclick="window.gestionToast?.('Demande envoyée : l’accueil vous propose un créneau par e-mail.')">Envoyer la demande</button>
            </section>
        </form>
    </div>
    <div class="g-toasts" aria-live="polite" data-g-toasts></div>
</main>
<script>
// Maquette : jours, heures, « créneau déjà pris », double fuseau en visio, demande.
(() => {
    const form = document.querySelector('[data-c-booking]');
    const days = [...form.querySelectorAll('[data-c-day]')];
    const pris = form.querySelector('[data-c-pris]');
    const chosen = form.querySelector('[data-c-chosen]');
    const submit = form.querySelector('[data-c-submit]');
    const fuseau = Intl.DateTimeFormat().resolvedOptions().timeZone || 'Africa/Dakar';
    // Démonstration : un visiteur à Paris si le navigateur est déjà à l'heure de Dakar
    const fuseauPersonne = ['Africa/Dakar', 'UTC', 'Etc/UTC', 'Africa/Abidjan'].includes(fuseau) ? 'Europe/Paris' : fuseau;
    const ville = fuseauPersonne.split('/').pop().replace('_', ' ');
    const heureLocale = (texte) => {
        // Heure de Dakar = UTC+0 : on convertit l'instant UTC dans le fuseau de la personne
        const instant = new Date(texte.replace(' ', 'T') + ':00Z');
        return new Intl.DateTimeFormat('fr-FR', { hour: '2-digit', minute: '2-digit', timeZone: fuseauPersonne }).format(instant);
    };
    const majFuseau = () => {
        const visio = form.querySelector('input[name="mode"]:checked')?.value === 'visio';
        form.querySelector('[data-c-tz]').hidden = !visio;
        form.querySelector('[data-c-tz-nom]').textContent = `votre heure (${ville})`;
        form.querySelectorAll('[data-c-time]').forEach((b) => {
            b.querySelector('[data-c-local]').textContent = visio ? `${heureLocale(b.dataset.cTime)} ${ville}` : '';
        });
    };
    form.querySelectorAll('input[name="mode"]').forEach((r) => r.addEventListener('change', majFuseau));
    days.forEach((d) => d.addEventListener('click', () => {
        days.forEach((o) => o.setAttribute('aria-selected', String(o === d)));
        form.querySelectorAll('[data-c-times]').forEach((t) => { t.hidden = t.dataset.cTimes !== d.dataset.cDay; });
        pris.hidden = true;
    }));
    form.querySelectorAll('[data-c-time]').forEach((b) => b.addEventListener('click', () => {
        if (b.dataset.pris === '1') {
            // C2 : réservé entre-temps par quelqu'un d'autre ; il disparaît, les autres restent
            pris.hidden = false;
            b.remove();
            return;
        }
        form.querySelectorAll('[data-c-time]').forEach((o) => o.classList.toggle('is-selected', o === b));
        const jour = form.querySelector('[data-c-day][aria-selected="true"]');
        const visio = form.querySelector('input[name="mode"]:checked')?.value === 'visio';
        chosen.textContent = `${jour.innerText.replace(/\n/g, ' ')} à ${b.querySelector('strong').textContent} (heure de Dakar)` + (visio ? `, soit ${heureLocale(b.dataset.cTime)} à ${ville}` : '');
        submit.disabled = false;
    }));
    form.addEventListener('submit', () => window.gestionToast?.('Rendez-vous confirmé : un e-mail de confirmation vous est envoyé.'));
    form.querySelectorAll('[data-c-demande]').forEach((a) => a.addEventListener('click', (event) => {
        event.preventDefault();
        const bloc = form.querySelector('[data-c-demande-bloc]');
        bloc.hidden = false;
        bloc.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }));
    majFuseau();
})();
</script>
