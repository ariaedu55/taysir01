# Notifications par e-mail

Jusqu'ici, le portail n'envoyait aucun e-mail : l'agence devait consulter le back-office et le client son espace. Voir [demandes-rendez-vous.md](demandes-rendez-vous.md) et [pages-agence.md](pages-agence.md).

## Événements notifiés

| Événement | Client | Agence |
| --- | --- | --- |
| Inscription | Bienvenue, lien vers l'espace | — |
| Nouvelle demande de service | Accusé de réception avec la référence | Alerte (service, client, destination, lien vers la fiche) |
| Changement de statut ou note **visible** par un conseiller | Nouveau statut et message du conseiller | — |
| Message ou document ajouté par le client | — | Alerte avec le message |
| Demande de rendez-vous | Accusé de réception | Alerte |
| Rendez-vous confirmé (date et heure) ou annulé | Confirmation ou annulation | — |
| Message de la page Contact | Accusé de réception | Alerte (réponse directe au visiteur) |

Les notes internes ne déclenchent jamais d'e-mail. Les e-mails ne contiennent ni document ni numéro de pièce d'identité : les détails restent dans l'espace, accessible après connexion.

## Choix

- **File d'attente en base** (`mail_outbox`, migration `005`). Chaque e-mail est enregistré puis envoyé **après la réponse au navigateur** (`fastcgi_finish_request()` quand il existe, comme chez LWS) : l'internaute n'attend pas le serveur d'envoi, et un échec n'empêche jamais l'enregistrement d'une demande.
- **Relance** : un e-mail en échec est retenté par `bin/send-mails.php`, à lancer par une tâche planifiée (cron LWS, toutes les 5 minutes), jusqu'à 5 tentatives espacées. Le back-office affiche les envois (`/admin/e-mails/`) et permet de renvoyer un e-mail en échec.
- **Trois modes d'envoi** (`config/mail.php`) :
  - `log` (développement, par défaut) : chaque e-mail est écrit dans `storage/mails/` au format `.eml` ;
  - `mail` : fonction `mail()` de PHP, disponible chez LWS ;
  - `smtp` : client SMTP natif (SSL ou STARTTLS, authentification), sans bibliothèque, pour la boîte de l'agence.
  Les identifiants SMTP vont dans `config/mail.local.php`, jamais dans le dépôt.
- **Adresse absolue des liens** : `config('app.url')` et la fonction `absolute_url()`, utilisables aussi en ligne de commande.
- **Modèles** dans `app/Views/emails/` : un gabarit commun (en-tête bleu nuit, logo texte, pied avec coordonnées) et une version texte pour chaque e-mail. Polices système, styles en ligne, pas de texte rouge.
- **Sécurité** : en-têtes protégés contre l'injection (retours à la ligne retirés), adresses validées, `Reply-To` vers le client dans les alertes et vers l'agence dans les e-mails aux clients.

## Plan

1. `config/mail.php`, `app.url`, `absolute_url()` ; migration `005_create_mail_outbox.sql`.
2. `App\Services\Mail\Mailer` (modes log, mail, smtp), `App\Models\MailOutbox`, `App\Services\Notifier`.
3. Modèles d'e-mails.
4. Branchement dans les contrôleurs (inscription, demandes, réponses, mises à jour, rendez-vous, contact) ; `bin/send-mails.php` ; écran `/admin/e-mails/`.
5. Tests : chaque événement en mode `log`, envoi SMTP réel vers un serveur SMTP local de test, échec puis relance.
6. Résultat ici, plan de migration et mémoire.

## Résultat (20 novembre 2026)

Le portail envoie désormais 11 types d'e-mails (7 aux clients, 4 alertes à l'agence), en file d'attente, avec relance et suivi dans le back-office.

| Fichier | Rôle |
| --- | --- |
| `config/mail.php` | Mode (`log` par défaut), expéditeur, boîte de l'agence, SMTP, `send_immediately`, `max_attempts` |
| `config/app.php` | `url` : adresse publique du site (liens absolus) |
| `app/Helpers/functions.php` | `absolute_url()` |
| `database/migrations/005_create_mail_outbox.sql` | Table `mail_outbox` (appliquée) |
| `app/Services/Mail/Mailer.php` | Message MIME texte + HTML (quoted-printable, en-têtes encodés et protégés), modes `log`, `mail`, `smtp` |
| `app/Services/Mail/SmtpClient.php` | Client SMTP natif : SSL (465) ou STARTTLS (587), vérification du certificat, AUTH LOGIN ; le mot de passe n'apparaît jamais dans les erreurs |
| `app/Models/MailOutbox.php` | `queue()` (envoi en fin de requête après `fastcgi_finish_request()`), `deliver()` (ne lève jamais d'exception), relances à 5, 15, 60 puis 240 minutes, `due()`, `retry()` |
| `app/Services/Notifier.php` | Les événements : `welcome`, `requestCreated`, `requestUpdated` (statut ou note visible seulement), `clientReplied`, `appointmentRequested`, `appointmentUpdated` (confirmation avec date et heure, ou annulation), `contactReceived` |
| `app/Views/emails/layout.php`, `text.php` | Gabarit HTML (tableaux, styles en ligne, bandeau bleu nuit, filet et bouton rouges, aucun texte rouge) et version texte, à partir des mêmes données |
| `bin/send-mails.php` | Relances, pour la tâche planifiée (verrou : une seule exécution à la fois) |
| `app/Controllers/Admin/MailController.php`, `app/Views/admin/mails.php` | `/admin/e-mails/` : envois par statut, erreur et nombre d'essais, bouton « Renvoyer » ; avertissement en mode `log` |
| Contrôleurs | Appels au `Notifier` après l'inscription, la demande, la réponse du client, la mise à jour par l'équipe, le rendez-vous, sa confirmation et le message de contact |
| `app/bootstrap.php` | Pas de session en ligne de commande |

### Tests

- **Parcours complet en mode `log`** : inscription, demande de visa, note interne (aucun e-mail), statut « En attente de vos éléments » avec note visible, réponse du client avec document, rendez-vous, confirmation, message de contact. Résultat : exactement les 10 e-mails attendus, avec les bons destinataires, objets et `Reply-To`.
- **Rendu** vérifié dans le navigateur (largeur de téléphone et d'ordinateur) : demande en attente, confirmation de rendez-vous, alerte de l'agence.
- **Défaut corrigé** : la version texte encodait les sauts de ligne en `=0A` ; les fins de ligne sont maintenant converties avant l'encodage.
- **SMTP réel** vers un serveur SMTP de test local : connexion, AUTH LOGIN, envoi, message reçu complet.
- **Échecs** :
  - mauvais mot de passe : e-mail « en attente », relance 5 minutes plus tard ; le cron ne le reprend pas avant ; mot de passe corrigé, il part ;
  - après 5 échecs, l'e-mail passe « en échec » ; le bouton « Renvoyer » du back-office le renvoie ;
  - serveur d'envoi coupé pendant un message de contact : le message est bien enregistré et la page répond ; les e-mails attendent la relance.
- **Accès** : `/admin/e-mails/` en 403 pour un client.
- **Mode différé** (`send_immediately` à false) : réponse en 0,04 s, e-mails envoyés par `bin/send-mails.php`.
- Données, fichiers et configuration de test supprimés.

### Mise en production (LWS)

1. Créer `config/app.local.php` (copie de `config/app.php`) avec `'url' => 'https://taysir-orientation.com'` et `'debug' => false`.
2. Créer `config/mail.local.php` (copie **complète** de `config/mail.php` : il remplace le fichier, il ne le complète pas) :
   - mode `smtp` avec la boîte contact@taysir-orientation.com : serveur, port 465 et `ssl`, ou 587 et `tls`, identifiants fournis par LWS ;
   - ou mode `mail` si l'hébergement envoie directement.
3. Ajouter la tâche planifiée : `php /chemin/du/site/bin/send-mails.php` toutes les 5 minutes.
4. Vérifier que `fastcgi_finish_request()` existe (PHP-FPM) ; sinon, mettre `send_immediately` à false pour que les visiteurs n'attendent pas l'envoi.
5. Configurer SPF et DKIM du domaine chez LWS, pour que les e-mails n'arrivent pas en indésirables.

### Reste à faire

- ~~Réinitialisation du mot de passe par e-mail~~ : faite, voir [mot-de-passe-oublie.md](mot-de-passe-oublie.md).
- Choix par le client de recevoir ou non certains e-mails, si l'agence le souhaite.
