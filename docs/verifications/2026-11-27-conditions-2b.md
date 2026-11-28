# Vérification : levée des conditions B1 à B5 de la décision 2B

- Date : 2026-11-27
- Objet : commit local `738cab7` (non poussé), au regard de `docs/validations/2026-11-27-fin-etape-2b.md`, §4
- Référence : `docs/gestion-phase-2.md`, section « 2B : conditions B1 à B5 »

## 1. Verdict technique

**NON CONFORME.**

- B2 et B5 sont levées.
- B3 est levée sur l'essentiel, avec trois écarts mineurs de couverture.
- B1 est en place et fonctionne, mais deux points demandés ne sont pas tenus : des seuils d'environnement actifs en production, et une vérification puis un enregistrement non atomiques.
- **B4 n'est pas levée** : il y a 4 captures sur les 18 demandées.

Tous les outils passent, sur les deux moteurs.

## 2. Contrôles lancés

Ci-dessous, `$PHP` désigne la commande PHP fournie (php 8.4.15 -n et ses extensions).

| Contrôle | Commande | Résultat |
| --- | --- | --- |
| Syntaxe des 16 fichiers PHP du commit | `php -n -l` | 16/16 sans erreur |
| Robot, MySQL 8.4.7 | `$PHP bin/recette.php` | **127/127**, base `taysir_ref726cbd_recette` supprimée |
| Agenda, MySQL | `$PHP bin/recette-agenda.php` | **10/10**, sortie 0 |
| Répétition 012-013, MySQL | `$PHP bin/repetition-012-013.php` | **13/13**, base supprimée |
| Répétition 011, MySQL | `$PHP bin/repetition-011.php` | **37/37**, copie supprimée |
| Robot, MariaDB 10.11.15 isolée | `TAYSIR_DB_PORT=33317 $PHP bin/recette.php --sql-user=root --sql-password=…` | **127/127**, base `taysir_r9f7426f8_recette` supprimée |
| Agenda, MariaDB | `TAYSIR_DB_PORT/USER/PASSWORD … bin/recette-agenda.php` | **10/10** |
| Répétition 012-013, MariaDB | `… --port=33317 --sql-user=root --sql-password=… --exiger=10.11` | « Serveur : 10.11.15-MariaDB sur 127.0.0.1:33317 », **13/13** |
| Bases restantes après les tests | `SHOW DATABASES LIKE '%recette'` | MariaDB : aucune. MySQL : `taysir_1239d6ecaf974f7e_recette` et `taysir_5ef1de0dc48b45ff_recette`, antérieures à mes exécutions (voir A12) |
| Relecture B1 | `BookingLimiter.php`, `AppointmentController::store` | voir A1 à A3, A9 et A10 |
| Relecture B2 | diff de `Appointment`, `RendezVousController`, `Notifier` et des vues ; recherche de « Dakar » et `'UTC'` dans `app/` hors maquette | voir A6 à A8 |
| Relecture B3 | diff de `bin/recette.php` (l. 554-626) et de `bin/recette-agenda.php` (l. 313-367) | voir A5 |
| Captures B4 | `docs/captures/gestion-2b/` | 4 fichiers (A4) |
| Charte (CSS du commit) | `git show 738cab7 -- public/assets/css/gestion.css` ; recherche de `gradient` et de bordures colorées dans `gestion.css` | 3 lignes ajoutées (taille et alignement), aucun dégradé, aucune bordure colorée |
| B5 : chemins et secrets | recherche de `C:/`, `wamp`, `storage/recette`, `root`, `password=` dans `bin/repetition-012-013.php` | aucun ; seul `33317` apparaît, comme exemple d'usage dans le commentaire. `DROP DATABASE` ne vise que sa propre base (l. 96-97) |
| Secrets du commit | noms de fichiers et lignes ajoutées | aucun `.env`, `*.local.php`, `storage/` ni `.sql` ; adresses uniquement en `@recette.test` |
| Affichage public (serveur 8080) | page d'annulation (jeton invalide) et formulaire public, à 1440 et 375 px | aucun débordement horizontal, aucune erreur PHP. Réservation directe inactive en local : créneaux non affichables |

## 3. Anomalies

### A1 (majeure, B1) : les seuils d'environnement s'appliquent aussi en production

- **Preuve** :
  - `app/Services/BookingLimiter.php:65-70` lit `getenv('RDV_LIMITE_*')` sans condition et accepte toute valeur > 0 ;
  - `app/Helpers/env.php:27-28` charge `.env` par `putenv()`.
- **Effet** : une ligne `RDV_LIMITE_IP_HEURE=1000` laissée dans le `.env` de production (par copie d'un réglage de recette, par exemple) désactive la limite sans aucun signal.
- **Écart** : le commentaire de la classe (l. 19) et `gestion-phase-2.md` disent « pour le robot uniquement » ; le code ne l'impose pas.
- **Correctif suggéré** :
  - n'accepter la surcharge que si `config/recette.php` ne renvoie pas `null` ;
  - ou n'accepter que des valeurs ≤ au seuil par défaut hors recette.

### A2 (majeure, B1) : vérification et enregistrement non atomiques, contournables par rafale

- **Preuve** :
  - `AppointmentController.php:123` appelle `BookingLimiter::check()` ;
  - puis `Appointment::create()` (l. 132) ;
  - puis seulement `BookingLimiter::record()` (l. 143).
- **Mécanisme** : `check()` lit les fichiers sans verrou. `BookingLimiter::actifs()` (l. 54-61) compte en dehors de la transaction de `create()`. Des requêtes parallèles **sans cookie**, donc chacune dans sa propre session, non sérialisées par le verrou de session, passent toutes la vérification avant qu'une seule ne soit comptée.
- **Effet** : les limites (5 par heure, 3 actifs) sont dépassées d'autant de requêtes simultanées. Je ne l'ai pas reproduit en HTTP : c'est un constat de lecture de code.
- **Correctif suggéré** : compter avant la création, sous `flock` exclusif (vérifier et ajouter dans le même verrou), puis décompter en cas d'échec. Pour le plafond actif, refaire le comptage dans la transaction, sous le verrou du responsable déjà pris.

### A3 (mineure, B1) : écart à la lettre « en base »

- La décision demande des limites « côté serveur et en base ».
- Les compteurs par IP et par e-mail sont dans des fichiers (`storage/cache/rendez-vous/`, `BookingLimiter.php:73-77`). Seul le plafond actif est lu en base.
- Le fonctionnement est correct sur un seul serveur. Le validateur décide si l'écart est acceptable.

### A4 (bloquante pour B4) : captures incomplètes

- **Preuve** : `docs/captures/gestion-2b/` ne contient que 4 fichiers :
  - `1440-agenda-secretaire.jpg` ;
  - `768-agenda-secretaire.jpg` ;
  - `375-agenda-secretaire.jpg` ;
  - `375-reservation-creneaux.jpg`.
- **Demandé** : 6 écrans à 3 largeurs, soit 18 captures.
- **Manquent** :
  - la semaine d'un agent ;
  - les plages et absences (le panneau est replié sur toutes les captures) ;
  - les durées ;
  - la page publique avec créneaux à 1440 et 768 px ;
  - la page d'annulation sans compte, aux trois largeurs.
- `gestion-phase-2.md` (§ B4 livré) reconnaît le périmètre réduit.
- **Relecture des 4 captures existantes** :
  - aucune bordure colorée ni dégradé ;
  - titres en Prata, texte en Poppins, texte noir ;
  - l'agenda à 375 px est compact : la barre de filtres défile horizontalement, avec une barre de défilement grise visible ;
  - le bouton « Mettre à jour » est vert (`g-btn-success`), pas rouge `#ce0f0f`. Ce choix est antérieur au lot (commit `3bd660d`, couleurs par action) et je ne le compte pas comme anomalie du lot.
- **Correctif** : produire les 14 captures manquantes à partir d'une base de recette (`recette.php --garder`, puis nettoyage par l'outil).

### A5 (mineure, B3) : couverture en retrait de la demande sur trois points

1. **Compteurs non contrôlés.** La décision demande pour deux agents du même poste « liste, compteurs, fiche et 403 journalisé ». Le robot (`bin/recette.php`, lignes « Agenda ») vérifie la liste, le 403 journalisé et la fiche, mais pas les compteurs : ni `countsByStatus` sur la page, ni le badge de la coquille (`Coquille.php:57`). Ces compteurs appliquent bien `Access::rendezVousSql` (`Appointment.php:297-300`), mais aucun test ne le prouve.
2. **P3 ne discrimine rien.** Le scénario se joue à Dakar (UTC+0) : « UTC stocké » et « affiché à 15:00 » ne détecteraient pas une conversion absente. Aucun test HTTP ne couvre en fuseau non nul la confirmation manuelle, les absences, l'e-mail, la confirmation publique ou l'espace client.
3. **P10 bis et P12 incomplets.**
   - P10 bis prévoit « visio en Europe/Paris ». Le test utilise le mode `agence-ziguinchor` avec Ziguinchor basculée en Paris : il couvre `slots()`, pas la visio ni l'heure « pour la personne ».
   - P12 vise « créneau falsifié **ou passé** ». Seuls les créneaux falsifiés sont testés (10:07 et 15:00 sans plage), pas un créneau passé ou à moins de 24 h.

En plus de ces trois points, le robot HTTP relève les limites par IP à 1000 (`bin/recette.php:67`). Le branchement du limiteur dans `store()` (refus HTTP, message affiché, rien d'enregistré) n'est donc testé qu'en appel direct, dans `recette-agenda.php`. Le seuil de 15 par jour n'est testé nulle part.

### A6 (mineure, B2) : libellé « heure de Dakar » encore codé en dur sur le formulaire public

- **Preuve** : `app/Views/pages/appointment.php:77`, `Créneau confirmé · heure de Dakar`.
- Les créneaux de `agence-ziguinchor` sont calculés dans le fuseau de Ziguinchor (`Appointment.php:131-146`).
- Le libellé est exact aujourd'hui, puisque 012 met `Africa/Dakar` par défaut et que seule Paris passe en `Europe/Paris` ; il ne suivrait pas un changement de fuseau.
- **Correctif** : dériver le libellé de `Appointment::city()` de l'agence du mode choisi.

### A7 (mineure, B2) : report daté en UTC

- **Preuve** : `Appointment.php:364-365`. La demi-journée et la date (`preferred_date`, `preferred_slot`) du rendez-vous de remplacement sont tirées de `$startUtc`, pas de l'heure locale de l'agence.
- **Effet** : pour une agence hors UTC+0, mauvaise demi-journée près de 13:00 et mauvaise date près de minuit.
- **Correctif** : convertir avec `Appointment::zone()` avant de dériver ces champs.

### A8 (mineure, B2) : fuseau par défaut pour les rendez-vous sans agence

- **Constats** :
  - les demandes publiques n'ont pas d'`agency_id` (`insertAppointment`, `Appointment.php:186-191`). La confirmation manuelle interprète donc l'heure en Dakar (`RendezVousController.php:154-155`) ;
  - l'e-mail est construit sur l'enregistrement **d'avant** la mise à jour (`RendezVousController.php:188`, `Notifier.php:231-232`). Si le responsable choisi appartient à une agence hors UTC+0, l'e-mail annonce l'heure « de Dakar », alors que l'agenda affichera ensuite l'heure de l'agence ;
  - un compte sans agence (administrateur) voit l'agenda de toutes les agences en heure de Dakar.
- Il n'y a aucun effet tant que seules des agences en UTC+0 sont actives.
- **Correctif** : utiliser le fuseau de l'agence du responsable retenu, et notifier avec l'enregistrement relu après `planifier()`.

### A9 (mineure, B1) : empreinte réversible pour les IPv4

- **Preuve** : `BookingLimiter.php:81`, `sha256(kind|valeur|date)` sans secret.
- Avec la date connue, une IPv4 se retrouve par énumération de 2^32 valeurs. « Rien en clair » est respecté à la lettre, pas l'intention de pseudonymisation.
- **Correctif** : HMAC avec une clé de l'application.

### A10 (mineure, B1) : contournements secondaires

- Les alias d'une même boîte (`nom+1@…`) contournent la limite par e-mail et le plafond actif. Il reste la limite IP de 15 par jour.
- Le nom du fichier change à minuit (`date('Y-m-d')`) : la fenêtre horaire repart à zéro à ce moment-là.
- IPv6 n'est pas agrégée par /64. Ce n'est pas exploitable sur le VPS actuel, qui n'écoute qu'en IPv4 (`deploy/nginx-goo-bridge.conf:9,28`), mais `deploy/nginx.conf:15` écoute `[::]:80`.

### A11 (mineure, divers)

- `storage/recette/mariadb-10.11.15-isolee/` contient 6 fichiers `.gitkeep` non ignorés (`git status -uall`). Un `git add -A` les ajouterait. Le fichier de mot de passe de l'instance, lui, est bien ignoré (`/storage/**/*`).
- `BookingLimiter::actifs()` filtre sur `LOWER(TRIM(email))`, ce qui empêche l'usage d'un index : la table entière est parcourue à chaque envoi.
- La page d'annulation (lien invalide) à 375 px : le titre `h1` commence à 52,5 px, sous le bas de l'en-tête fixe (54,6 px). Il est collé à l'en-tête, sans marge haute. C'est un défaut d'affichage léger.

### A12 (observation) : bases de recette orphelines sur MySQL

- `taysir_1239d6ecaf974f7e_recette` et `taysir_5ef1de0dc48b45ff_recette` existent sur MySQL 8.4.7.
- Elles ne viennent pas de mes exécutions : mes bases portent d'autres noms et ont été supprimées. Leur format (16 caractères hexadécimaux, sans préfixe) ne correspond à aucun outil actuel de `bin/`.
- Je n'y ai pas touché.

## 4. Points conformes

- **B1** :
  - limites par IP (5 par heure, 15 par jour), par e-mail (3 par heure) et plafond de 3 rendez-vous actifs ;
  - message unique `BookingLimiter::MESSAGE` pour toutes les limites ;
  - fichiers nommés par empreinte, contenant seulement des horodatages, rangés hors de `public/` et ignorés par git ;
  - en production, `REMOTE_ADDR` est bien l'adresse du client : Nginx parle directement à PHP-FPM (`docs/deploiement-vps.md:152`).
- **B2** :
  - `slots()` convertit les plages depuis le fuseau de l'agence de la règle, `24:00` compris, et borne la journée en heure locale ;
  - l'agenda, la confirmation manuelle, les absences, `Notifier`, la confirmation publique, l'espace client et la fiche personne passent par `Appointment::zone()` et `Appointment::local()` ;
  - P10 bis vérifie 09:00 UTC le 2027-03-26 et 08:00 UTC le 2027-03-30, sur les deux moteurs ;
  - aucun `Africa/Dakar` codé en dur hors maquette, hors valeurs par défaut documentées et hors A6.
- **B3** : P1 bis (requêtes réellement simultanées), P12 (CSRF 419 et créneaux falsifiés), P3, P15 et le 403 journalisé entre deux agents du même poste passent.
- **B5** :
  - `bin/repetition-012-013.php` est sans chemin ni secret local ;
  - il refuse une version inattendue avant toute écriture ;
  - il supprime sa base dans `finally` ;
  - `TAYSIR_DB_PORT` n'est lu que si `TAYSIR_DB` se termine par `_recette` (`config/recette.php`), puis repris dans `Database.php:22` et `database/migrate.php:30`.

## 5. Non vérifié

- **Écrans de la gestion dans le navigateur** (agenda, semaine, plages, durées) : il aurait fallu des comptes, donc une base de recette conservée, que je n'aurais pas pu supprimer moi-même. Je me suis appuyé sur les 4 captures et sur les contrôles « sans erreur PHP » du robot.
- **Formulaire public avec créneaux** : la réservation directe est désactivée sur le serveur local 8080.
- **Contournement par rafale (A2)** : non reproduit en HTTP. C'est un constat de lecture de code.
- **`bin/repetition-migrations.php`** (22/22 annoncé) : non relancé. Aucune migration ne change dans `738cab7` ; seule la transmission du compte SQL change (2 lignes).
- **B6 (préalables de publication)** : hors du périmètre de cette vérification.
