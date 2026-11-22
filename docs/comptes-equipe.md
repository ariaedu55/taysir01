# Comptes de l'équipe et attribution des demandes

Jusqu'ici, un compte conseiller ou administrateur ne pouvait être créé qu'en modifiant la base à la main. Voir [vision-plateforme.md](vision-plateforme.md) (rôles) et [authentification.md](authentification.md).

## Existant

- `users.role` : `client`, `conseiller`, `admin` ; middleware `role:admin,conseiller` sur tout `/admin/`.
- `service_requests.assigned_to` (clé étrangère vers `users`) : prévu, jamais utilisé.
- Le parcours « mot de passe oublié » ([mot-de-passe-oublie.md](mot-de-passe-oublie.md)) sait déjà faire choisir un mot de passe à partir d'un lien envoyé par e-mail.
- La base ne contient aucun compte : il faut un moyen de créer le **premier administrateur**.

## Choix

- **Écran « Équipe »** (`/admin/equipe/`), réservé aux **administrateurs** (`role:admin`) : liste des conseillers et administrateurs, création, changement de rôle, désactivation et réactivation, renvoi de l'invitation.
- **Invitation plutôt que mot de passe choisi par l'administrateur** : le compte est créé sans mot de passe utilisable, et la personne reçoit un e-mail avec un lien (valable 72 heures) pour choisir le sien. Le lien réutilise le parcours de réinitialisation. L'administrateur ne connaît jamais le mot de passe.
- **Désactivation** (migration `006` : `users.active`) : un compte désactivé ne peut plus se connecter, et une session déjà ouverte est fermée à la requête suivante. On ne supprime pas : l'historique des demandes garde l'auteur des notes.
- **Garde-fous** : un administrateur ne peut ni se désactiver ni retirer son propre rôle ; le dernier administrateur actif ne peut pas être rétrogradé ni désactivé.
- **Premier administrateur** : `bin/create-admin.php` (ligne de commande, donc accès au serveur obligatoire) crée le compte et envoie l'invitation, ou affiche le lien si l'envoi n'est pas configuré.
- **Attribution des demandes** : toute l'équipe voit toutes les demandes (petite agence). Chaque demande peut être attribuée à un conseiller depuis sa fiche (« Me l'attribuer » ou choix dans la liste). La liste des demandes a une colonne « Conseiller » et les filtres « Mes demandes » et « Non attribuées ». Le conseiller reçoit un e-mail quand on lui attribue une demande ; un événement interne est ajouté à l'historique (non visible du client).
- Les rendez-vous ne sont pas encore attribués (la table n'a pas de colonne prévue) : étape suivante si l'agence le souhaite.

## Plan

1. Migration `006_add_users_active.sql` ; `User` : comptes de l'équipe, création, rôle, activation ; `Auth` : refus des comptes désactivés.
2. `PasswordReset::issue()` avec durée ; `Notifier::staffInvitation()` et `requestAssigned()` ; `bin/create-admin.php`.
3. `Admin\TeamController`, vue `admin/team.php`, routes `role:admin`, onglet « Équipe » visible des seuls administrateurs.
4. Attribution : `ServiceRequest::assign()`, formulaire sur la fiche, colonne et filtres de la liste.
5. Tests : droits (client, conseiller, administrateur), invitation jusqu'à la première connexion, désactivation avec session ouverte, garde-fous, attribution et e-mail.
6. Résultat ici, `authentification.md`, mémoire.

## Résultat (20 novembre 2026)

| Fichier | Rôle |
| --- | --- |
| `database/migrations/006_add_users_active.sql` | `users.active`, `users.invited_by` (appliquée) |
| `app/Models/User.php` | `staff()`, `createStaff()` (mot de passe aléatoire inutilisable), `setRole()`, `setActive()`, `activeAdminCount()` |
| `app/Core/Auth.php` | Compte désactivé : connexion refusée (même message qu'un mauvais mot de passe), session fermée à la requête suivante |
| `app/Controllers/PasswordController.php` | Pas de lien de réinitialisation pour un compte désactivé ; premier mot de passe d'un membre invité : page « Bienvenue dans l'équipe », pas d'e-mail « mot de passe modifié » |
| `app/Models/PasswordReset.php` | `issue($userId, $minutes)`, `INVITATION_MINUTES` (72 heures) |
| `app/Services/Notifier.php` | `staffInvitation()`, `requestAssigned()` ; le contenu de l'invitation est effacé de `mail_outbox` après l'envoi, comme le lien de réinitialisation |
| `bin/create-admin.php` | Premier administrateur, en ligne de commande : `--email`, `--prenom`, `--nom`, `--telephone` ; refuse une adresse déjà utilisée ; affiche le lien d'activation |
| `app/Controllers/Admin/TeamController.php`, `app/Views/admin/team.php` | `/admin/equipe/` (`role:admin`) : liste (rôle, invitation en attente, désactivé, dernière connexion, demandes en cours), ajout avec invitation, rôle, désactivation, réactivation, nouvelle invitation |
| `app/Views/partials/admin-nav.php` | Onglet « Équipe » affiché aux administrateurs seulement |
| `app/Models/ServiceRequest.php` | `search()` avec filtre conseiller (`moi`, `aucun`), `assign()` (trace interne dans l'historique), `assignmentCounts()` |
| `app/Controllers/Admin/RequestController.php`, `app/Views/admin/request(s).php` | Encadré « Conseiller » sur la fiche (liste de l'équipe active, « Me l'attribuer »), colonne et filtres « Mes demandes » et « Non attribuées » dans la liste |

Tests (base vide au départ, tout supprimé ensuite) :

- **Premier administrateur** (`bin/create-admin.php`) : arguments invalides refusés ; compte créé, lien affiché, page « Bienvenue dans l'équipe », mot de passe choisi, arrivée sur `/admin/`, pas d'e-mail « mot de passe modifié ».
- **Invitation d'un conseiller** depuis l'écran : e-mail envoyé puis son contenu effacé de la base ; adresse en double refusée ; « Invitation en attente » jusqu'à l'activation, puis connexion.
- **Droits** :
  - un conseiller accède au back-office mais pas à l'onglet ni à `/admin/equipe/` (403, y compris en POST avec un jeton valide) ;
  - un client a 403.
- **Garde-fous** :
  - pas de désactivation ni de changement de rôle sur son propre compte ;
  - un compte client ne peut pas être géré depuis cet écran (404) ;
  - promotion puis rétrogradation d'un conseiller.
- **Désactivation** :
  - une session ouverte est fermée à la requête suivante ;
  - la connexion est refusée et aucun lien de réinitialisation n'est envoyé ;
  - l'invitation n'est pas renvoyée ;
  - après réactivation, la connexion refonctionne.
- **Attribution** :
  - par l'administrateur au conseiller : e-mail au conseiller, la demande apparaît dans ses « Mes demandes » et dans la colonne ;
  - attribution à un compte client refusée ;
  - « Me l'attribuer » n'envoie pas d'e-mail ;
  - libérer la demande la remet dans « Non attribuées » ;
  - les 3 traces sont internes et invisibles du client.
- **Affichage** : écran Équipe (actions sous chaque membre) et encadré « Conseiller » ; listes déroulantes hors `.field` stylées.

### Mise en production

Après le déploiement et les migrations, créer le premier administrateur :

```
php bin/create-admin.php --email=… --prenom=… --nom=…
```

Il invite ensuite les conseillers depuis `/admin/equipe/`.

### Reste à faire

- Attribution des rendez-vous (colonne à ajouter à `appointments`) et vue « mes rendez-vous ».
- Modification de son propre profil (nom, téléphone, mot de passe) depuis l'espace ou le back-office.
