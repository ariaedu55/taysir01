# Vision : une plateforme, pas une vitrine

> **À lire en priorité par toute personne ou tout agent qui travaille sur ce projet.**
> Décision de l'utilisateur, le 20 novembre 2026.

## L'objectif principal

L'application Taysir Orientation Voyage **ne doit pas se limiter à un site vitrine**. C'est une **plateforme** à double rôle :

1. **Un portail pour le public** : élèves, étudiants, parents et particuliers. Ils peuvent :
   - **s'inscrire** et **se connecter** à un espace personnel ;
   - **faire des demandes** : orientation, admission, visa, logement, prise en charge, billet d'avion, réservation d'hôtel ;
   - **prendre rendez-vous** avec un conseiller ;
   - **déposer leurs documents** (passeport, relevés, diplômes…) et **suivre l'avancement de leur dossier** ;
   - échanger avec l'agence et recevoir des notifications (e-mail, puis SMS ou WhatsApp).
2. **Un outil de gestion pour l'agence** (back-office) :
   - clients et dossiers ;
   - demandes et leurs statuts, attribuées aux conseillers ;
   - rendez-vous et agenda ;
   - documents reçus et à vérifier ;
   - paiements, échéances et reçus ;
   - catalogue des destinations, établissements et programmes ;
   - contenus publics (pages, actualités, offres) ;
   - statistiques (demandes, admissions, visas obtenus, chiffre d'affaires).

Les pages publiques (accueil, destinations…) restent la porte d'entrée : chacune mène vers une action du portail (créer un compte, faire une demande, prendre rendez-vous).

## Les trois espaces

| Espace | Public | Adresses | État |
| --- | --- | --- | --- |
| Site public | Tout le monde | `/`, `/destination/`, fiches pays… | En place (accueil, 12 pays) |
| Espace client | Comptes `client` | `/inscription/`, `/connexion/`, `/mon-espace/…` | Inscription, connexion et tableau de bord d'accueil prêts |
| Back-office | Comptes `conseiller` et `admin` | `/admin/…` | À construire |

## Rôles

| Rôle | Peut |
| --- | --- |
| `client` | Gérer son profil, créer et suivre ses demandes, prendre rendez-vous, déposer des documents, consulter ses paiements. |
| `conseiller` | Traiter les demandes qui lui sont attribuées, gérer son agenda, vérifier les documents, échanger avec les clients. |
| `admin` | Tout, plus la gestion des comptes, des contenus, des paiements et des statistiques. |

## Modèle de données (cible)

| Table | Rôle | État |
| --- | --- | --- |
| `users` | Comptes : identité, profil (élève, étudiant, parent, particulier), rôle, mot de passe chiffré, vérification d'e-mail | **Créée** (`database/migrations/001_create_users.sql`) |
| `password_resets` | Jetons de réinitialisation du mot de passe | **Créée** (même migration) |
| `requests` (demandes) | Type de service, pays visé, statut, conseiller attribué, historique | À créer |
| `request_events` | Journal des changements de statut et des commentaires | À créer |
| `appointments` | Rendez-vous : date, motif, mode (agence, téléphone, visio), conseiller | À créer |
| `documents` | Fichiers déposés, stockés dans `storage/uploads/` (hors racine web), avec leur statut de vérification | À créer |
| `payments` | Montants, échéances, moyens de paiement (Wave, Orange Money, espèces, virement), reçus | À créer |
| `messages` / `notifications` | Échanges client-agence et notifications | À créer |
| `destinations`, `institutions`, `programs` | Catalogue, aujourd'hui en fichiers (`database/content/`) | À migrer en base |

## Parcours prioritaires

1. **Inscription et connexion** : fait (voir [authentification.md](authentification.md)).
2. **Prise de rendez-vous** en ligne, depuis le site public et l'espace client. Elle remplace le formulaire WordPress `/rendez_vous_orientation/`.
3. **Demandes de service** : un formulaire par service (admission, visa, logement, prise en charge, billet, hôtel), qui crée une demande suivie dans l'espace client et traitée dans le back-office. Elles remplacent les formulaires WordPress.
4. **Suivi de dossier** : statuts, documents, messages. Il remplace « Suivre son dossier » et le lien externe `gaov.edutaysir.com`.
5. **Back-office** : tableau de bord, demandes, rendez-vous, clients, documents.
6. **Paiements** et reçus.
7. **Notifications** : e-mails transactionnels, puis SMS ou WhatsApp.

## Principes pour la suite

- **Toute nouvelle page publique doit mener vers une action du portail** : bouton « Créer mon compte », « Faire une demande » ou « Prendre rendez-vous ». Le lien vers l'ancien site WordPress n'est que provisoire.
- **Sécurité dès le départ** : mots de passe chiffrés (`password_hash`), sessions sécurisées et régénérées à la connexion, jeton CSRF sur tout formulaire, requêtes préparées, contrôle d'accès par rôle sur chaque route (middlewares `auth`, `guest`, `role:…`), fichiers privés hors de la racine web, limitation des tentatives de connexion.
- **Données personnelles** : consentement explicite à l'inscription, collecte limitée au nécessaire, droit d'accès et de suppression (loi sénégalaise n° 2008-12 sur les données personnelles, et RGPD pour les dossiers vers l'Europe).
- **Même identité visuelle** que le site public (voir [DESIGN.md](DESIGN.md) et [refonte-immersive.md](refonte-immersive.md)) : fonds blanc pur, Playfair Display et Plus Jakarta Sans, pas de texte rouge, animations réversibles.
- Le plan de migration ([plan-migration.md](plan-migration.md)) reste valable pour les contenus. Ses étapes 3 et suivantes s'inscrivent dans cette vision : les formulaires WordPress sont remplacés par des fonctionnalités du portail, pas par de simples pages.
