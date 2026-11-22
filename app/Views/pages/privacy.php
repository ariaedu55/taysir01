<?php
/**
 * Conditions et confidentialité. Reprend la structure de la politique WordPress, corrigée pour décrire
 * le site tel qu'il fonctionne (voir docs/pages-agence.md). À faire valider par le client.
 */
$email = config('site.contact.email');
?>
<main id="contenu" class="destinations-page agency-page">
    <section class="destinations-hero">
        <div class="wrap">
            <nav class="breadcrumb" aria-label="Fil d’Ariane">
                <ol>
                    <li><a href="<?= e(url('/')) ?>">Accueil</a></li>
                    <li>L’agence</li>
                    <li aria-current="page">Conditions et confidentialité</li>
                </ol>
            </nav>
            <h1 data-split>Confidentialité <span class="accent">de vos données</span></h1>
            <p class="destinations-lead" data-reveal="up" style="--delay: 300ms">La protection de vos données personnelles est une priorité. Voici ce que nous recueillons, pourquoi, et comment nous le protégeons.</p>
        </div>
    </section>

    <div class="wrap service-layout">
        <article class="prose legal-text" data-reveal="up">
            <p class="legal-date">Dernière mise à jour : 20 novembre 2026</p>

            <h2>1. Les informations que nous recueillons</h2>
            <h3>Celles que vous nous donnez</h3>
            <ul>
                <li><strong>Votre compte :</strong> prénom, nom, adresse e-mail, téléphone, profil (élève, étudiant, parent, particulier) et mot de passe, conservé sous forme chiffrée.</li>
                <li><strong>Vos demandes :</strong> coordonnées, date de naissance (facultative), numéro de passeport ou de carte d’identité (facultatif, pour le visa et le billet d’avion), informations sur votre projet, messages échangés avec votre conseiller.</li>
                <li><strong>Vos documents :</strong> les fichiers que vous joignez à une demande (relevés, diplômes, passeport…).</li>
                <li><strong>Vos rendez-vous et vos messages :</strong> nom, e-mail, téléphone, objet, date souhaitée et contenu de votre message.</li>
            </ul>
            <h3>Celles qui sont enregistrées automatiquement</h3>
            <ul>
                <li><strong>Un seul cookie,</strong> nécessaire au fonctionnement du site : il maintient votre connexion et protège les formulaires contre les envois frauduleux. Il est supprimé à la fermeture du navigateur.</li>
                <li>Le site n’utilise <strong>ni cookie publicitaire, ni outil de mesure d’audience</strong>.</li>
                <li>Le site ne propose <strong>aucun paiement en ligne</strong> : aucune donnée bancaire n’y est saisie.</li>
            </ul>

            <h2>2. Pourquoi nous les utilisons</h2>
            <ul>
                <li><strong>Traiter vos demandes :</strong> orientation, admission, visa, logement, prise en charge, billet d’avion, hôtel.</li>
                <li><strong>Organiser vos rendez-vous</strong> et répondre à vos messages.</li>
                <li><strong>Vous tenir informé</strong> de l’avancement de votre dossier, dans votre espace, par téléphone, WhatsApp ou e-mail.</li>
            </ul>
            <p>Nous ne vous envoyons pas de prospection commerciale sans votre accord.</p>

            <h2>3. Avec qui nous les partageons</h2>
            <p>Nous ne vendons pas vos données. Elles sont partagées uniquement :</p>
            <ul>
                <li><strong>Avec les organismes concernés par votre projet,</strong> à votre demande : établissements d’enseignement, consulats, logeurs, compagnies aériennes, hôtels.</li>
                <li><strong>Avec notre hébergeur,</strong> qui stocke le site et sa base de données.</li>
                <li><strong>Si la loi l’exige,</strong> ou pour répondre à une procédure judiciaire.</li>
            </ul>

            <h2>4. Comment nous les protégeons</h2>
            <ul>
                <li>Vos documents sont conservés hors de la partie publique du site, sous un nom aléatoire : seuls vous et l’équipe Taysir pouvez les ouvrir.</li>
                <li>Chaque client ne voit que ses propres demandes ; l’accès au back-office est réservé aux conseillers.</li>
                <li>Les mots de passe sont chiffrés, et les connexions répétées en échec sont temporairement bloquées.</li>
            </ul>
            <p>Aucune méthode de transmission ou de stockage n’étant parfaitement sûre, nous ne pouvons toutefois garantir une sécurité absolue.</p>

            <h2>5. Combien de temps nous les gardons</h2>
            <p>Le temps nécessaire au suivi de votre projet et au respect de nos obligations légales. Vous pouvez demander la suppression de votre compte et de vos données à tout moment.</p>

            <h2>6. Vos droits</h2>
            <p>Conformément à la loi sénégalaise sur la protection des données personnelles, sous le contrôle de la Commission de protection des données personnelles (CDP), vous pouvez :</p>
            <ul>
                <li><strong>accéder</strong> à vos données et en obtenir une copie ;</li>
                <li><strong>les rectifier</strong> ou les mettre à jour ;</li>
                <li><strong>demander leur suppression</strong> ;</li>
                <li><strong>vous opposer</strong> à leur traitement ou en demander la limitation, dans certains cas.</li>
            </ul>
            <p>Pour exercer ces droits, écrivez-nous à <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a>.</p>

            <h2>7. Modifications</h2>
            <p>Cette politique peut évoluer. La date de mise à jour en haut de page indique la dernière version.</p>

            <h2>8. Contact</h2>
            <ul>
                <li><strong>E-mail :</strong> <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></li>
                <li><strong>Adresse :</strong> Taysir Orientation Voyage, <?= e(config('site.contact.address')) ?>, Sénégal</li>
            </ul>
        </article>

        <aside class="service-aside">
            <div class="article-aside-card glass" data-reveal="right">
                <p class="kicker">En bref</p>
                <h2>Vos données</h2>
                <ul class="privacy-points">
                    <li>Utilisées seulement pour votre projet</li>
                    <li>Jamais vendues</li>
                    <li>Un seul cookie, technique</li>
                    <li>Documents visibles par vous et l’équipe uniquement</li>
                </ul>
                <a class="button light" href="<?= e(url('/nos-regles-et-valeurs/')) ?>">Nos règles et valeurs</a>
            </div>
        </aside>
    </div>
</main>
