# Contre-vérification : conditions B1 à B5 de la décision 2B, après reprise

- Date : 2026-11-27
- Objet : commit local `8dfa0f9` (non poussé), au regard de `docs/validations/2026-11-27-fin-etape-2b.md` et du rapport précédent `docs/verifications/2026-11-27-conditions-2b.md` (NON CONFORME)
- Référence des corrections : `docs/gestion-phase-2.md`, section « 4. Reprise après le rapport du vérificateur »
- Arbre de travail : des modifications non commitées et sans lien avec la 2B sont présentes (assistant IA, `bin/create-admin.php`, `docs/deploiement-vps.md`, `bin/serveur-local.bat`). Les outils ont tourné sur cet arbre.

## 1. Verdict technique

**CONFORME AVEC RÉSERVES.**

- **A4 (bloquante) est levée** : 18 captures sur 18, et la charte est respectée.
- **A1, A2, A9 et A10 sont corrigées dans le code** :
  - verrou exclusif autour de la vérification, de la création et du comptage ;
  - aucune sortie ne laisse le verrou pris ;
  - seuils réglables seulement en recette ;
  - HMAC avec une clé ;
  - fenêtre glissante, alias et IPv6.
- **La page d'annulation ne consomme pas le jeton** à l'affichage.
- Tous les outils passent sur les deux moteurs.

Réserves, toutes mineures :
- le test « rafale » du robot ne prouve pas le verrou (R1) ;
- les adresses IPv4 vues en IPv6 (IPv4-mapped) sont regroupées dans un seul compteur (R2) ;
- A6 n'est corrigée qu'en apparence (R4) ;
- six captures ne montrent pas le rendu final des panneaux repliables (R5).

## 2. Contrôles lancés

`$PHP` désigne la commande PHP fournie (php 8.4.15 -n et ses extensions).

| Contrôle | Commande | Résultat |
| --- | --- | --- |
| Syntaxe des 9 fichiers PHP du commit | `php -n -l` | 9/9 sans erreur |
| Robot, MySQL | `$PHP bin/recette.php` | **129/129**, sortie 0, base `taysir_rf414ab29_recette` supprimée |
| Agenda, MySQL | `$PHP bin/recette-agenda.php` | **11 lignes « OK — »**, sortie 0 |
| Répétition 012-013, MySQL | `$PHP bin/repetition-012-013.php` | **13/13**, base `taysir_012_013_ff523410c5_recette` supprimée |
| Robot, MariaDB isolée (port 33317) | `TAYSIR_DB_PORT=33317 $PHP bin/recette.php --sql-user=… --sql-password=…` (compte lu dans le fichier local, jamais affiché) | **129/129**, base `taysir_rf1751b6e_recette` supprimée |
| Agenda, MariaDB | `TAYSIR_DB_PORT/USER/PASSWORD … bin/recette-agenda.php` | **11 lignes « OK — »**, sortie 0 |
| Répétition 012-013, MariaDB | `… --port=33317 … --exiger=10.11` | « Serveur : 10.11.15-MariaDB sur 127.0.0.1:33317 », **13/13**, base supprimée |
| Bases restantes | `SHOW DATABASES LIKE '%recette'` | MariaDB : aucune. MySQL : les deux mêmes bases orphelines que dans le rapport précédent (A12), aucune des miennes |
| Seuils hors recette (A1) | appel par réflexion de `BookingLimiter::limit('RDV_LIMITE_IP_HEURE', 5)` avec `RDV_LIMITE_IP_HEURE=1000` | sans `TAYSIR_DB` : **5** ; avec `TAYSIR_DB=taysir_x_recette` : 1000 |
| Regroupement IPv6 (A10) | copie de `BookingLimiter::ip()` exécutée sur 5 adresses | `2001:db8::1` et `::2` → même /64 (attendu) ; `::ffff:1.2.3.4` et `::ffff:5.6.7.8` → **même clé** `0000000000000000::/64` (R2) |
| Relecture du verrou | `BookingLimiter.php`, `AppointmentController::store`, `Appointment::create` | voir § 3 |
| Page d'annulation | `AppointmentController::guestCancellation`, `Appointment::findByCancellationToken`, vue, scénario P14 du robot | voir § 3 |
| Captures | lecture des 18 fichiers de `docs/captures/gestion-2b/` | 18/18 présentes ; voir R5 et R7 |
| Charte (CSS) | `git show 8dfa0f9 -- public/assets/css/gestion.css` ; recherche de `gradient` et de bordures colorées dans tout `gestion.css` | 12 lignes ajoutées (surfaces `--g-soft`, pastille `--t-rdv-bg`) ; aucun dégradé, aucune bordure colorée |
| Fichiers ignorés | `git check-ignore -v` ; `git status -uall` | `storage/recette/` ignoré (`.gitignore:17`), `storage/cache/rendez-vous/.cle` ignoré (`.gitignore:12`) ; plus aucun `.gitkeep` de recette en attente |
| Secrets du commit | lignes ajoutées par `8dfa0f9` | aucune clé, aucun mot de passe ; adresses seulement en `@recette.test`. Les mentions `--sql-user=root --sql-password=…` viennent de mon rapport précédent et ne contiennent pas le mot de passe |

## 3. Constats sur les points demandés

### 3.1 A4 : captures et charte. Levée.

- Les **18 fichiers** demandés sont présents : 6 écrans à 1440, 768 et 375 px.
- 14 ont été ajoutés par `8dfa0f9`. Les 4 autres datent de `738cab7` : agenda de l'accueil (×3) et réservation à 375 px.
- Les panneaux des disponibilités et des durées sont **ouverts**. La semaine porte des titres « Lundi 07/12 ».
- L'annulation sans compte montre le rappel du rendez-vous et les deux boutons.
- Charte relevée sur les captures :
  - aucune bordure colorée ni dégradé ;
  - titres en Prata, texte en Poppins, texte noir ;
  - boutons verts pour « Enregistrer » et « Mettre à jour », rouges pour « Supprimer » et « Confirmer l'annulation » ;
  - pastille bleue `--t-rdv` sur la tuile « + / – ».
- À 375 px, aucun débordement horizontal n'est visible. La barre des statuts défile dans son cadre, comme déjà noté au rapport précédent.

### 3.2 A1, A2, A9, A10 : `BookingLimiter` et `AppointmentController::store`

- **Le verrou couvre la vérification, la création et le comptage.**
  - `proteger()` (`BookingLimiter.php:36-53`) prend `flock(LOCK_EX)` sur `.verrou`.
  - Dans ce verrou, il appelle `check()`, puis `$creer()`, c'est-à-dire `Appointment::create`, puis `record()`.
  - `create()` valide sa transaction (commit, l. 183) ou passe en écriture directe (`insertAppointment`) **avant** la fin du verrou. Le comptage `actifs()` de la requête suivante voit donc la ligne.
  - L'appel du contrôleur est en `AppointmentController.php:131`, d'après le diff. Les e-mails partent après la libération du verrou.
- **Aucune sortie ne laisse le verrou pris.**
  - La libération est dans un `finally` (l. 47-52). Les exceptions `LimiteAtteinte`, `DomainException` et `PDOException` le traversent.
  - Les `back()` et `redirect_to()` du contrôleur s'exécutent après `proteger()`.
  - Il n'y a aucun `exit`, `die`, `redirect_to` ni `abort` dans `Appointment.php`, `Person.php`, `User.php`, `Model.php` ou `Database.php`.
  - En cas d'erreur fatale, le descripteur est fermé à la fin de la requête.
  - Si la limite est atteinte, rien n'est créé ni compté.
- **Les seuils ne se règlent qu'en recette.**
  - `limit()` (l. 121-129) renvoie la valeur par défaut si `config/recette.php` renvoie `null`.
  - Le mode recette exige `TAYSIR_DB` en `*_recette`, ce qui bascule aussi la base. Il n'est donc pas activable par erreur sans effet visible.
  - Contrôle exécuté : la valeur reste 5 hors recette, malgré `RDV_LIMITE_IP_HEURE=1000`.
- **HMAC avec une clé.**
  - Les noms de fichiers sont calculés par `hash_hmac('sha256', kind|valeur, cle())` (l. 175).
  - La clé fait 32 octets aléatoires. Elle est créée en `fopen('x')`, en 0600, sous `storage/`, et ignorée par git.
  - Le test agenda vérifie son format.
- **Fenêtre glissante** : les noms de fichiers ne contiennent plus de date. `read()` et `append()` gardent 24 h, et le filtre horaire porte sur `now - 3600` (l. 61, 185, 198).
- **Alias** : `email()` retire « +… » (l. 99-108). `actifs()` compte aussi les alias déjà en base par `LIKE local+%@domaine`, en échappant `%`, `_` et `\` (l. 85-96).
- **IPv6 par /64** : corrigé (l. 111-119), avec le cas IPv4-mapped décrit en R2.
- Les tests agenda (alias, /64, 15 sur 24 h, clé) passent sur les deux moteurs.

### 3.3 A6, A7, A11 : page d'annulation

- **Le jeton n'est pas consommé à l'affichage.**
  - Le GET range le jeton en session et redirige (`AppointmentController.php:184-193` du fichier actuel).
  - L'affichage appelle `findByCancellationToken()` (`Appointment.php:262-272`). C'est un simple `SELECT`, sans `FOR UPDATE` ni écriture.
  - Seul `confirmGuestCancellation()`, en POST et avec CSRF, appelle `cancelGuest()`, qui annule et supprime le jeton.
  - Preuve d'exécution : P14 invité, sur les deux moteurs. Deux GET (A et B), puis le POST de A annule A seul : le jeton de A a donc survécu à l'affichage, et B reste « demandé ».
- **A7 (date du report en heure locale)** : corrigé (`Appointment.php:385-388`). Ce point n'est pas couvert par un test hors UTC+0.
- **A11** :
  - `.gitkeep` de recette : corrigé ;
  - titre collé sous l'en-tête : corrigé (captures 375 et 768) ;
  - index sur l'e-mail : non traité (voir R6).

### 3.4 Non-reprises motivées

- **A3 (compteurs en fichiers et non en base) : acceptée.** Sur un seul serveur PHP-FPM, le `flock` exclusif sérialise correctement. Le plafond actif, lui, est lu en base. À revoir s'il y a un jour plusieurs serveurs.
- **A8 (Dakar par défaut sans agence) : acceptée sous condition.** Il n'y a aucun effet tant que toutes les agences actives sont en UTC+0. Le point doit être repris **avant** l'ouverture au public d'une agence hors UTC+0 (Paris). Je recommande de l'inscrire comme préalable dans le plan.
- **A12 (deux bases orphelines) : acceptée.** Je confirme qu'elles existent toujours (`taysir_1239d6ecaf974f7e_recette`, `taysir_5ef1de0dc48b45ff_recette`) et qu'aucun outil actuel n'en est l'auteur. Leur suppression revient à l'utilisateur.

## 4. Anomalies (réserves)

### R1 (mineure) : le test « rafale » ne prouve pas le verrou

- **Preuve** : `bin/recette.php:149` lance `php -S` sans `PHP_CLI_SERVER_WORKERS`, variable sans effet sous Windows de toute façon. Le serveur intégré traite donc une requête à la fois : les 6 requêtes `curl_multi` sont sérialisées par le serveur, pas par le verrou.
- **Effet** : le contrôle « B1 Rafale simultanée … 3 acceptée(s), 3 en base » passerait aussi avec le code de `738cab7`, non atomique. La correction d'A2 est établie par la **lecture du code** (§ 3.2), pas par ce test. Même remarque pour P1 bis.
- **Correctif suggéré** :
  - lancer N processus CLI parallèles qui appellent `BookingLimiter::proteger()` sur la base de recette, avec un `$creer` qui attend un peu ;
  - ou lancer le serveur avec plusieurs workers sous Linux ;
  - ou, à défaut, renommer le contrôle pour ne pas affirmer la simultanéité.

### R2 (mineure) : les adresses IPv4-mapped partagent une seule clé

- **Preuve** : `BookingLimiter.php:113-116`. `inet_pton('::ffff:1.2.3.4')` renvoie 16 octets, dont les 8 premiers sont nuls : toutes les IPv4 vues sous cette forme donnent `0000000000000000::/64` (exécuté, § 2).
- **Effet** : si PHP reçoit un jour `REMOTE_ADDR` sous la forme `::ffff:a.b.c.d` (socket double pile `ipv6only=off`, ou mandataire), tous les visiteurs IPv4 partagent la limite de 5 par heure et de 15 par jour. La prise de rendez-vous serait alors bloquée pour tout le monde.
- **Exposition actuelle** : aucune. `deploy/nginx-goo-bridge.conf:9,28` n'écoute qu'en IPv4. `deploy/nginx.conf:14-15` utilise deux sockets séparées, avec `ipv6only` actif par défaut.
- **Correctif suggéré** : avant le regroupement /64, ramener `::ffff:0:0/96` à l'IPv4 (`inet_ntop(substr($binaire, 12))`).

### R3 (mineure) : verrou « fail-open »

- **Preuve** : `BookingLimiter.php:163-171` et 48. Si `.verrou` ne peut pas être ouvert (droits sur `storage/cache/rendez-vous`), `proteger()` continue sans verrou et sans signal. On revient alors au comportement d'A2.
- **Correctif suggéré** : journaliser l'échec, ou refuser l'envoi. Vérifier au déploiement que `storage/cache/rendez-vous/` est inscriptible par PHP-FPM.

### R4 (mineure) : A6 n'est corrigée qu'en apparence

- **Preuve** : `app/Views/pages/appointment.php:77` affiche `Appointment::city(Appointment::agencyId('dakar'))`. Le libellé reste celui de l'agence de Dakar, quel que soit le mode choisi.
- Pour `agence-ziguinchor`, `slots()` calcule dans le fuseau de Ziguinchor (`Appointment.php`, `$agency = $mode === 'agence-ziguinchor' ? 'ziguinchor' : 'dakar'`). Aucun script de `public/assets/js/` ne met le libellé à jour.
- `gestion-phase-2.md` annonce pourtant un « libellé du formulaire public lu sur l'agence ».
- **Effet** : aucun aujourd'hui, puisque les deux agences sont en `Africa/Dakar`.
- **Correctif suggéré** : libellé lié au mode (attribut `data-` par mode et mise à jour en JS), ou heure de l'agence renvoyée avec les créneaux.

### R5 (mineure) : six captures ne montrent pas le rendu final du panneau replié

- **Preuve** :
  - `1440/768/375-agenda-secretaire.jpg` (commit `738cab7`) et `1440/768/375-semaine-agent.jpg` (`8dfa0f9`) montrent « ▶ Mes disponibilités et absences », avec le marqueur natif ;
  - `1440/768/375-durees.jpg` montrent le même panneau replié avec la tuile « + » de `.g-pli` (`gestion.css`, lignes ajoutées par `8dfa0f9`) ;
  - le code actuel applique `g-pli` aux deux panneaux (`rendez-vous.php`, diff de `8dfa0f9`).
- **Effet** : ces six captures ne reflètent pas l'état livré de ce détail. Le reste de leur contenu est cohérent.
- **Correctif suggéré** : refaire ces six captures sur le code de `8dfa0f9`.

### R6 (mineure, reprise de A11) : `actifs()` sans index utilisable

- **Preuve** : `BookingLimiter.php:90`, `LOWER(TRIM(email)) = ? OR LOWER(TRIM(email)) LIKE ?`. Ce filtre parcourt toute la table `appointments` à chaque envoi, sous le verrou global, ce qui allonge la section critique quand la table grossit.
- **Correctif suggéré** : stocker l'e-mail normalisé (colonne indexée, migration de la 2C).

### R7 (mineure, affichage) : écarts mineurs relevés sur les captures

- **Bulle de l'assistant** : `375-annulation-sans-compte.jpg`. La bulle « Comment puis-je vous aider ? » recouvre le lien « Prendre rendez-vous ». Le widget est commun à tout le site.
- **Libellé de l'annulation** : `findByCancellationToken()` (`Appointment.php:262-272`) n'a pas la condition `a.preferred_date >= UTC_DATE()` de `cancelGuest()` (l. 284). Pour une demande dont la date souhaitée est passée, la page propose « Annuler ce rendez-vous ? », puis la confirmation aboutit à « Lien inutilisable ».
- **Date de l'agenda** : `768-agenda-secretaire.jpg`, capture de `738cab7`. La date « 08/12/2026 » n'est pas centrée verticalement entre « Précédent » et « Suivant ».

## 5. Non vérifié

- **Contention réelle du verrou** : je n'ai lancé ni processus parallèles ni PHP-FPM. La correction d'A2 repose sur la lecture du code (R1).
- **Écrans de la gestion dans le navigateur** : il aurait fallu une base de recette conservée et des comptes. Je me suis appuyé sur les 18 captures et sur les contrôles « sans erreur PHP » du robot.
- **Fuseau hors UTC+0 sur la page d'annulation et sur le report (A7)** : aucun test ne le couvre. Le contrôle est fait par lecture seulement.
- **`bin/repetition-migrations.php`** : non relancé, car aucune migration ne change dans `8dfa0f9`.
- **B6 (préalables de publication)** : hors du périmètre de cette vérification.
