# Demandes de service et rendez-vous

Troisième brique du portail ([vision-plateforme.md](vision-plateforme.md)) : remplacer les formulaires WordPress par des demandes enregistrées, suivies par le client et traitées par l'agence.

## Constat sur les formulaires WordPress (20 novembre 2026)

| Page | Champs WordPress |
| --- | --- |
| `/demande-dadmission/` | Civilité, prénom, nom, naissance, CNI ou passeport, e-mail, téléphone, destination, niveau, établissement, filière ou série, **dossier** (fichier) |
| `/demande-de-logement/` | Socle commun, pays et ville de destination, établissement d'accueil |
| `/demande-de-visa/` | Socle commun, destination, type de visa (étudiant, travailleur, affaires, tourisme) |
| `/demande-de-prise-en-charge/` | Socle commun, pays et ville de destination, établissement d'accueil |
| `/vente-billet-davion/` | Socle commun, destination, aller simple ou aller-retour, dates de départ et de retour |
| `/reservation-hotel/` | Socle commun, destination, hôtel, début et fin du séjour |
| `/rendez_vous_orientation/` | 6 motifs : orientation filières, renseignement, logement, orientation métiers, prise en charge, visa (via une extension de réservation) |

**Anomalie** : chaque formulaire envoyait l'e-mail de notification **à l'adresse du visiteur** (`email_to: [field id="email"]`), et non à l'agence. Les demandes n'étaient probablement consultables que dans l'administration WordPress.

## Choix

- **Demandes de service réservées aux comptes connectés.** Elles contiennent des données sensibles (pièce d'identité, dossiers scolaires) et doivent être suivies dans l'espace client. Un visiteur voit la présentation du service et un appel « Créez votre compte ou connectez-vous », qui le ramène au formulaire une fois connecté.
- **Rendez-vous ouverts à tous**, avec le minimum de données (nom, téléphone, e-mail, motif, mode, date souhaitée). Une personne connectée retrouve ses rendez-vous dans son espace.
- **Données minimales** : le numéro de pièce d'identité n'est demandé que pour le visa et le billet d'avion, et reste facultatif. La date de naissance est facultative.
- **Documents** (relevés, diplômes, passeport) : facultatifs, 3 au maximum, en PDF, JPG ou PNG, 5 Mo chacun. Le vrai type est contrôlé avec `fileinfo`, le fichier est enregistré sous un nom aléatoire dans `storage/uploads/` (hors de la racine web), et le téléchargement n'est possible que pour le propriétaire et l'équipe.
- **Destinations** : saisie libre avec suggestions (les 12 pays du site et les pays fréquents), à la place de la liste de 190 pays en anglais.
- Chaque demande et chaque rendez-vous reçoit une **référence** (`TAY-AAAA-000123`, `RDV-AAAA-000045`) et un **historique** (création, changements de statut, notes visibles par le client).

## Plan

1. Migration `003_create_requests_appointments.sql` : tables `service_requests`, `request_documents`, `request_events` et `appointments`.
2. `config/services.php` : les 6 services (adresse WordPress, titre, présentation, champs propres). Moteur de formulaire générique (rendu et validation à partir de cette description).
3. Modèles `ServiceRequest` et `Appointment`, service `DocumentStorage`.
4. Pages publiques aux adresses WordPress : présentation et formulaire du service, prise de rendez-vous et confirmation.
5. Espace client : liste des demandes, détail avec historique et documents, rendez-vous. Le tableau de bord affiche les vrais chiffres.
6. Back-office : listes des demandes et des rendez-vous, filtrées par statut ; fiche d'une demande avec changement de statut et note au client ; documents.
7. Tests du parcours complet : visiteur, client, équipe, documents, contrôles d'accès.

## Résultat (20 novembre 2026)

Les 7 formulaires WordPress sont remplacés. Leurs adresses répondent désormais sur le nouveau site, et `legacy_url()` y fait pointer le menu automatiquement.

### Fichiers

| Fichier | Rôle |
| --- | --- |
| `database/migrations/003_create_requests_appointments.sql` | Tables `service_requests`, `request_documents`, `request_events`, `appointments` (appliquée) |
| `config/services.php` | Les 6 services : adresse, textes, étapes, champs propres (`fields`), pièce d'identité proposée ou non, documents proposés ou non |
| `app/Models/ServiceRequest.php` | Création en transaction (référence `TAY-AAAA-000001`, documents, événement), recherche, compteurs par statut, documents, historique, mise à jour par l'équipe |
| `app/Models/Appointment.php` | Motifs, modes, créneaux, statuts ; création (référence `RDV-AAAA-000001`), listes, mise à jour |
| `app/Services/DocumentStorage.php` | Contrôle des fichiers (3 au maximum, 5 Mo, PDF, JPG ou PNG selon `finfo`), enregistrement sous nom aléatoire dans `storage/uploads/requests/`, téléchargement (`attachment`, `nosniff`, `no-store`) |
| `app/Services/FormInput.php` | Lecture des saisies (texte, message, téléphone, date), pays suggérés, champ anti-robots |
| `app/Controllers/ServiceController.php` | Page de service et enregistrement de la demande (validation générique d'après `config/services.php`) |
| `app/Controllers/AppointmentController.php` | Prise de rendez-vous ouverte à tous, confirmation |
| `app/Controllers/Client/RequestController.php` | `/mon-espace/demandes/` : liste, détail, réponse du client (message et documents, 10 par demande), téléchargement |
| `app/Controllers/Admin/RequestController.php` | `/admin/demandes/` et `/admin/rendez-vous/` : listes filtrées, fiche, statut et note, documents, confirmation des rendez-vous |
| `app/Views/pages/service.php`, `appointment.php`, `appointment-confirmed.php` | Pages publiques |
| `app/Views/client/requests.php`, `request.php` ; `app/Views/admin/requests.php`, `request.php`, `appointments.php` | Suivi client et back-office |
| `app/Views/partials/form-field.php` | Étendu : `select`, `textarea`, `date`, `number`, `country` (suggestions `#liste-pays`), valeur par défaut, bornes |
| `app/Views/partials/status.php`, `request-timeline.php`, `request-summary.php`, `request-documents.php`, `admin-nav.php` | Éléments partagés client et équipe |
| `public/assets/css/portal.css`, `public/assets/js/portal.js` | Styles du portail ; liste et contrôle des fichiers choisis, bouton « Envoi en cours… » |
| `public/.user.ini` | `upload_max_filesize = 6M`, `post_max_size = 20M` (hébergement en CGI/FPM, comme LWS) |
| `storage/.htaccess` | `Require all denied` : `storage/` n'est jamais servi par Apache, même sous WAMP où le projet est dans `www/` |

Autres changements : connexion et inscription acceptent `?retour=/chemin/` (chemin interne uniquement) et y renvoient après succès. Le gabarit accepte `$robots` (`noindex` sur les pages privées). Les tableaux de bord client et équipe affichent les vrais chiffres. Le fil d'Ariane est passé de `destination.css` à `app.css`.

### Routes

| Adresse | Accès |
| --- | --- |
| `/demande-dadmission/`, `/demande-de-logement/`, `/demande-de-visa/`, `/demande-de-prise-en-charge/`, `/vente-billet-davion/`, `/reservation-hotel/` | GET public (formulaire si connecté, sinon invitation à se connecter) ; POST `auth` |
| `/rendez_vous_orientation/` (`?motif=visa`…), `/rendez_vous_orientation/confirmation/` | Public |
| `/mon-espace/demandes/`, `/mon-espace/demandes/{reference}/` (GET, POST réponse), `…/documents/{id}/` | `auth`, uniquement ses propres demandes (404 sinon) |
| `/admin/demandes/` (`?statut=`, `?service=`), `/admin/demandes/{reference}/` (GET, POST), `…/documents/{id}/`, `/admin/rendez-vous/` (`?statut=`), POST `/admin/rendez-vous/{reference}/` | `auth`, `role:admin,conseiller` |

### Règles de validation

- Coordonnées : prénom, nom, e-mail, téléphone (8 chiffres au moins) obligatoires ; civilité et date de naissance facultatives.
- Champs du service : obligatoires selon `config/services.php`, choix limités aux options, nombres de 1 à 20, dates à venir, retour après le départ, fin de séjour après le début.
- En cas d'erreur, les saisies reviennent mais pas les fichiers : un message demande de les sélectionner à nouveau.
- Rendez-vous : date entre demain et six mois, pas le dimanche ; 5 demandes par heure et par session au plus ; champ caché `site_web` contre les robots (réponse muette).
- Équipe : confirmer un rendez-vous exige la date et l'heure ; une note peut être visible du client ou interne.

### Tests (serveur de développement ; comptes et fichiers de test supprimés ensuite)

- Visiteur : 6 pages de service et rendez-vous en 200 ; invitation à se connecter ; POST sans compte refusé ; `?retour=` ramène au formulaire après l'inscription.
- Client : erreurs affichées (dates, nombre, option, faux PDF) ; demande de visa avec 2 documents, puis hôtel ; fichiers sous nom aléatoire ; téléchargement identique à l'original.
- Contrôle d'accès : demande et documents d'un autre client en 404, document d'une autre demande en 404, back-office en 403 pour un client, `storage/` en 403 sous Apache.
- Équipe : listes, filtres, fiche, documents ; statut « En attente de vos éléments » avec note visible ; note interne absente de l'espace client.
- Rendez-vous : dimanche refusé, robot ignoré, visiteur et client confirmés, 6e envoi dans l'heure bloqué, confirmation sans heure refusée puis acceptée ; le client voit « Confirmé, le … à 10h30 » et ses compteurs.
- Affichage vérifié en largeur mobile et bureau, sans débordement horizontal.

### Serveur de développement

`fileinfo` et la taille d'envoi sont nécessaires :

```
php -n -d extension_dir=C:/wamp64/bin/php/php8.4.15/ext -d extension=pdo_mysql -d extension=mbstring -d extension=openssl -d extension=fileinfo -d upload_max_filesize=6M -d post_max_size=20M -S 127.0.0.1:8080 -t public
```

### Reste à faire

- ~~**E-mails**~~ : faits, voir [notifications-email.md](notifications-email.md).
- Attribution d'une demande à un conseiller (`assigned_to` existe, pas encore d'écran) et création des comptes conseillers depuis le back-office.
- Envoi trop lourd (au-delà de `post_max_size`) : PHP vide le formulaire et le contrôle CSRF répond 419 ; un message plus clair serait mieux.
- Historique des demandes WordPress : à reprendre seulement si le client le souhaite (soumissions Elementor, absentes de l'export XML).
