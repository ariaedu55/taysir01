# Mot de passe oublié

Jusqu'ici, la page de connexion renvoyait vers WhatsApp. Maintenant que le portail envoie des e-mails ([notifications-email.md](notifications-email.md)), la réinitialisation se fait en ligne. Voir aussi [authentification.md](authentification.md).

## Existant

- Table `password_resets` (migration 001) : `user_id`, `token_hash` (SHA-256, unique), `expires_at`, `used_at`.
- `User::updatePassword()`, `Auth::login()` (identifiant de session régénéré).

## Parcours

1. `/mot-de-passe-oublie/` : l'internaute saisit son e-mail. **Réponse identique** que le compte existe ou non (« Si un compte existe, un e-mail vient d'être envoyé »), pour ne pas révéler les adresses inscrites.
2. Si le compte existe : jeton aléatoire de 32 octets ; seule son empreinte SHA-256 est enregistrée ; les jetons précédents du compte sont annulés ; validité **1 heure** ; e-mail avec le lien `/reinitialiser-mot-de-passe/<jeton>/`.
3. À l'ouverture du lien, le jeton est vérifié, **mis en session et retiré de l'adresse** (redirection vers `/reinitialiser-mot-de-passe/`) : il ne fuit ni dans l'historique, ni dans l'en-tête `Referer`.
4. Nouveau mot de passe (8 à 72 caractères, confirmation) : jeton marqué utilisé, autres jetons supprimés, connexion automatique, e-mail « Votre mot de passe a été modifié » (avec la consigne de prévenir l'agence si ce n'était pas vous).
5. Lien expiré, déjà utilisé ou inconnu : message clair et nouveau lien proposé.

## Protections

- 3 demandes par heure et par session, et 3 par heure et par compte (comptées en base) : pas d'envoi d'e-mails en rafale.
- Comparaison par empreinte, jeton à usage unique, expiration côté serveur.
- Les comptes conseillers et administrateurs utilisent le même parcours.
- La FAQ et la page de connexion pointent vers la nouvelle page.

## Plan

1. `App\Models\PasswordReset` ; `Notifier::passwordReset()` et `passwordChanged()`.
2. `PasswordController` (demande, lien, nouveau mot de passe), vues `auth/forgot.php` et `auth/reset.php`, routes (visiteurs uniquement).
3. Lien sur la page de connexion, réponse de la FAQ.
4. Tests : parcours complet, compte inexistant (même réponse), lien expiré, réutilisé, faux, limites, mot de passe trop court ou différent.
5. Résultat ici et dans `authentification.md`, mémoire.

## Résultat (20 novembre 2026)

| Fichier | Rôle |
| --- | --- |
| `app/Models/PasswordReset.php` | `issue()` (jeton de 32 octets, empreinte SHA-256, anciens jetons annulés, validité 60 minutes), `recentCount()`, `findValid()`, `consume()` |
| `app/Controllers/PasswordController.php` | `showForgot`, `sendLink` (réponse identique, limites), `open` (jeton en session, retiré de l'adresse), `showReset`, `reset` (connexion automatique, e-mail de confirmation) |
| `app/Views/auth/forgot.php`, `reset.php` | Pages au style de la connexion ; lien WhatsApp si l'adresse e-mail n'est plus accessible |
| `app/Models/User.php` | `passwordErrors()` et `PASSWORD_MIN` : mêmes règles à l'inscription et à la réinitialisation (`AuthController` simplifié) |
| `app/Services/Notifier.php` | `passwordReset()`, `passwordChanged()` |
| `app/Models/MailOutbox.php` | Le contenu de l'e-mail de lien est **effacé de la base après l'envoi** : le jeton n'y reste pas en clair |
| `routes/web.php` | `/mot-de-passe-oublie/` (GET, POST), `/reinitialiser-mot-de-passe/` (GET, POST), `/reinitialiser-mot-de-passe/{jeton}/` ; visiteurs uniquement |
| `app/Views/auth/login.php`, `config/faq.php` | Lien « Mot de passe oublié ? » ; réponse de la FAQ mise à jour |

Tests (comptes et e-mails de test supprimés) :

- **Parcours complet** : demande avec l'adresse en majuscules, e-mail envoyé, lien ouvert puis jeton retiré de l'adresse. Le compte s'affiche, puis :
  - erreurs « 8 caractères au moins » et « ne correspondent pas » ;
  - nouveau mot de passe enregistré, connexion automatique, e-mail « mot de passe modifié » ;
  - l'ancien mot de passe est refusé, le nouveau accepté.
- **Compte inexistant** : même message, aucun e-mail.
- **Liens refusés** : lien déjà utilisé, ancien lien après une nouvelle demande, lien expiré, jeton inventé ou mal formé, page ouverte sans lien.
- **Limites** : la 4e demande de l'heure pour un compte n'envoie rien ; la 4e demande de la session est refusée avec un message.
- **Divers** : une personne connectée est renvoyée vers son espace ; contenu de l'e-mail de lien vide en base après l'envoi.

Limite connue : la limite de 3 demandes par heure et par compte permet à un tiers qui connaît l'adresse de bloquer les demandes de ce compte pendant une heure. C'est un compromis courant contre l'envoi d'e-mails en rafale.
