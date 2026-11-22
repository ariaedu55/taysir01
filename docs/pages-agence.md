# Pages de l'agence

Dernier groupe de pages encore servies par l'ancien site, hors blog : présentation, contact, FAQ, règles, confidentialité, témoignages. Voir [plan-migration.md](plan-migration.md).

## Constat sur WordPress (20 novembre 2026)

| Page | Contenu WordPress |
| --- | --- |
| `/about-us/` « Qui sommes-nous ? » | Accueil, mission, engagement, vision, 4 services, 4 raisons, 6 atouts, 4 chiffres (300+ études et voyages, 100+ avis, 10+ destinations, 1 700+ clients), équipe de 4 avec photos, 9 témoignages clients |
| `/contact/` | Texte, formulaire (nom, e-mail, téléphone, message ; « réponse sous 24 heures »), téléphones Dakar et Ziguinchor (Paris : numéro incomplet « +(33) »), horaires (lundi au vendredi 9 h–18 h, samedi 9 h–14 h), e-mails Dakar, Paris, Ziguinchor, cartes, galerie |
| `/faq/` | **Contenu de démonstration du thème** (lunes de miel au Kerala, en anglais) : rien à reprendre |
| `/nos-regles-et-valeurs/` | Long texte : règles, valeurs, engagements, puis un second texte sur les mêmes thèmes ; galerie de photos |
| `/terms-conditions/` | Phrase de démonstration du thème, puis une politique de confidentialité avec des **erreurs** : adresse fictive « 123 Rue de l'Éducation, 75000 Paris », paiement par carte, cookies d'analyse et newsletters que le site n'a pas |
| `/temoignage/` | Titre, 4 photos et une galerie de photos de clients (visas obtenus, affiches « Success story ») |
| `/avis-etudiants/`, `/partir-a-letranger/`, `/journal-ecoles/` | **Pages vides** |

## Choix

- **Textes WordPress repris** par un script (`database/import/wordpress-agency.php`) vers `database/content/agency/` : présentation, équipe, témoignages, chiffres, règles (titres remis en ordre, coquilles corrigées), photos des galeries.
- **Contact** : le formulaire devient une fonctionnalité du portail. Les messages sont enregistrés en base (migration `004_create_contact_messages.sql`) et traités dans le back-office (`/admin/messages/`). Le numéro de Paris, incomplet, n'est pas affiché ; l'e-mail de Paris l'est. Pas de carte intégrée : un lien « Itinéraire » vers Google Maps.
- **Horaires** dans `config/site.php`. Le rendez-vous du samedi après-midi est refusé (agence fermée).
- **FAQ** : nouvelle, écrite uniquement à partir de faits vérifiables (services, compte, documents, suivi, agences, horaires, destinations). Les tarifs ne sont pas inventés : « communiqués par un conseiller avant tout engagement ». **À faire valider par le client.**
- **Confidentialité** : la politique WordPress est reprise et corrigée pour décrire le site réel (données du compte, demandes, documents, rendez-vous, messages ; un seul cookie, de session ; pas de paiement en ligne ni de mesure d'audience ; référence à la CDP du Sénégal citée dans les règles). L'adresse fictive est remplacée par l'agence de Dakar. **À faire valider par le client (aspect juridique).**
- **Témoignages** : les 9 témoignages et les photos publiées par l'agence. `/avis-etudiants/` (« Conseils des anciens étudiants ») montre les témoignages d'étudiants. Le lien de menu « Témoignages » pointe vers `/temoignage/`.
- **Pages vides** : `/partir-a-letranger/` redirige (301) vers `/destination/`, `/journal-ecoles/` vers `/formations-profesionnelles/`.
- Le blog reste à faire.

## Plan

1. Script d'import et contenus ; `config/faq.php` ; horaires dans `config/site.php`.
2. Migration et modèle `ContactMessage` ; `AgencyController` (pages), `ContactController` (formulaire), back-office des messages.
3. Vues : présentation, contact, FAQ, règles, confidentialité, témoignages.
4. Routes, redirections, liens du menu et de l'accueil.
5. Vérifications : pages, formulaire de contact de bout en bout, back-office, samedi après-midi, mobile.
6. Résultat ici, plan de migration et mémoire.

## Résultat (20 novembre 2026)

Les 7 pages de l'agence répondent sur le nouveau site ; les 2 pages vides redirigent. Seul le lien « Blog » pointe encore vers l'ancien site.

| Fichier | Rôle |
| --- | --- |
| `database/import/wordpress-agency.php` | Extrait `about.php` (accueil, 3 piliers, 4 services, 4 raisons, 6 atouts, 4 chiffres, 4 membres de l'équipe), `testimonials.php` (9, avec un indicateur « étudiant »), `rules.php` (titres en h2/h3/h4, casse et coquilles corrigées), `gallery.php` (41 photos) vers `database/content/agency/` |
| `config/faq.php` | 10 questions en 3 thèmes, écrites à partir du fonctionnement réel du site |
| `config/site.php` | `offices` (Dakar, Ziguinchor, Paris sans téléphone) et `hours` ; liens « Questions fréquentes » et « Témoignages » dans le pied de page ; « Témoignages » du menu vers `/temoignage/` |
| `database/migrations/004_create_contact_messages.sql`, `app/Models/ContactMessage.php` | Messages de contact (statut nouveau / traité, conseiller et date de traitement) |
| `app/Controllers/AgencyController.php` | `about`, `contact`, `sendMessage` (validation, champ anti-robots, 5 messages par heure et par session), `faq`, `rules`, `privacy`, `testimonials` (tous ou étudiants seulement) |
| `app/Controllers/Admin/MessageController.php`, `app/Views/admin/messages.php` | `/admin/messages/` : liste filtrée, « Marquer comme traité » / « Remettre à traiter » ; compteur sur le tableau de bord, onglet « Messages » |
| `app/Views/pages/about.php`, `contact.php`, `faq.php`, `rules.php`, `privacy.php`, `testimonials.php` | Pages ; `partials/testimonial-cards.php`, `partials/opening-hours.php` |
| `public/assets/css/agency.css` | Chiffres sur fond bleu nuit, piliers, listes, atouts numérotés, équipe, témoignages, mur de photos, tuiles de contact, FAQ en accordéons natifs (`<details>`), textes juridiques |

Rendez-vous : le samedi après-midi est refusé (« l'agence ferme à 14 h ») et l'aide du champ date le précise ; l'encadré latéral affiche les horaires et les agences depuis `config/site.php`.

Vérifications : 7 pages en 200, 2 redirections 301 ; contact : erreurs affichées, robot ignoré, message enregistré, visible dans le back-office, traité, 403 pour un client ; samedi après-midi refusé ; photos de l'équipe chargées ; affichage bureau et mobile sans débordement. Données de test supprimées.

Correction : la page Contact charge `auth.css`, qui porte les styles des champs (`.field input`). À terme, ces styles pourraient passer dans une feuille commune aux formulaires.

### À faire valider par le client

- **La FAQ** (rédigée, sans tarif ni garantie).
- **La politique de confidentialité** (corrigée : plus d'adresse fictive, de paiement ni de cookies d'analyse) : relecture juridique conseillée.
- Le **numéro de Paris** (incomplet sur WordPress) et l'adresse de l'agence de Ziguinchor.
- L'accord des clients dont les photos et témoignages sont publiés (déjà publiés sur l'ancien site).

### Reste à faire

- Le blog (`/blog/`).
- ~~Envoi d’e-mails~~ : fait, voir [notifications-email.md](notifications-email.md).
