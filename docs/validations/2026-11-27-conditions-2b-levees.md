# Décision : levée des conditions B1 à B5 de la 2B

## 1. Décision

**GO SOUS CONDITIONS.**

- B1 à B5 sont **levées**.
- La 2C peut **démarrer en local**.
- La publication de la 2B reste **bloquée par B6**, qui n’est pas commencée. Ce GO n’est pas un GO de mise en ligne.

## 2. Objet

- **Soumis** : levée des conditions B1 à B5 de [fin-etape-2b](2026-11-27-fin-etape-2b.md).
- **Commits locaux, non poussés** :
  - `738cab7` : première levée ;
  - `8dfa0f9` : reprise après le rapport NON CONFORME ;
  - `5bb3182` : course réelle sur le verrou et réserves mineures.
- **Hors lot**, toujours non commités :
  - l’assistant (contrôleur, client, base de connaissances, configuration, CSS, documentation) ;
  - `.gitattributes`, `bin/create-admin.php`, `docs/deploiement-vps.md` et `bin/serveur-local.bat`.
- **Pièces relues** :
  - `gestion-phase-2.md`, sections « 2B : conditions B1 à B5 », « 4. Reprise » et « 5. Contre-vérification » ;
  - [conditions-2b](../verifications/2026-11-27-conditions-2b.md) (NON CONFORME, sur `738cab7`) ;
  - [conditions-2b-reprise](../verifications/2026-11-27-conditions-2b-reprise.md) (CONFORME AVEC RÉSERVES, sur `8dfa0f9`).
- **Mes contrôles** :
  - **Diff de `5bb3182`** :
    - verrou SQL de secours `GET_LOCK`, avec refus si le verrou n’est pas obtenu ;
    - IPv4 encapsulée ramenée à l’IPv4 ;
    - page d’annulation alignée sur la règle d’annulation ;
    - libellé public « heure locale de l’agence » ;
    - participant de course protégé par un motif de nom de base `_recette` et une barrière de départ.
  - **`bin/recette-agenda.php` relancé** sur MySQL 8.4 (PHP 8.4.15) au commit `5bb3182` : **12/12**, dont « 6 processus simultanés, 3 acceptés et 3 refusés, 3 en base » et P10 bis. Aucun fichier laissé dans l’arbre, et la clé `.cle` est ignorée par git.
  - **Captures** : relecture de 3 des 18 (disponibilités à 375 px, semaine d’un agent à 1440 px, réservation à 375 px). Surfaces teintées, aucune bordure colorée ni dégradé, Prata et Poppins, texte noir, compact sur téléphone.

## 3. Décisions point par point

1. **B1, anti-abus : levée.**
   - Les limites sont tenues côté serveur :
     - par IP : 5 envois par heure et 15 par 24 h ;
     - par e-mail : 3 envois par heure ;
     - 3 rendez-vous actifs au plus par e-mail, compté en base.
   - La vérification, la création et le comptage se font sous un verrou exclusif.
   - Le verrou est **prouvé par exécution** : 6 processus réellement simultanés. L’essai de mutation fait échouer le test quand le verrou est retiré. Je l’ai rejoué : 12/12.
   - En production, les seuils ne se règlent pas ; les empreintes sont des HMAC à clé ; un même message répond à toutes les limites, sans révéler si l’adresse est connue (critère 1).
   - **A3 accepté** (compteurs en fichiers plutôt qu’en base) : sur un seul serveur, le `flock` suffit (critère 6).
     - Le verrou SQL de secours couvre l’échec d’ouverture du fichier.
     - À revoir si l’application passe un jour sur plusieurs serveurs.
2. **B2, fuseau de l’agence : levée.**
   - Les plages sont lues dans `agencies.timezone` et converties en UTC, changement d’heure compris (P10 bis).
   - L’affichage, la confirmation, les absences, les e-mails et le report suivent le même fuseau.
   - **A8 accepté sous condition** (Dakar par défaut quand aucune agence n’est connue) : aucun effet tant que seules des agences en UTC+0 sont actives.
     - C’est désormais une **condition d’activation** : aucune agence hors UTC+0 (Paris) ne s’ouvre au public avant la reprise d’A8, avec un test hors UTC+0 du report et de la page d’annulation (A7, couverts aujourd’hui par lecture seulement).
3. **B3, recette : levée.**
   - Sont couverts : P1 bis, P3, P10 bis, P12 (créneau falsifié, hors plage ou passé), P15, et deux agents du même poste sur des rendez-vous attribués (listes, fiches, 403 journalisé).
   - Le contrôle « deux agents du même poste » garde les **8 postes distincts**, sans fusion (critère 3).
   - Robot 129/129 et agenda 12/12 sur MySQL 8.4 et MariaDB 10.11.15.
4. **B4, captures : levée.**
   - Les 18 captures attendues sont là : 6 écrans à 3 largeurs.
   - Les réserves restantes sont acceptées, parce que ce sont des écarts d’affichage déjà corrigés dans le code, ou extérieurs au lot :
     - **R5** : marqueur du panneau replié sur six captures ;
     - **R7** : date de la capture à 768 px ;
     - libellé « heure de Dakar » sur `375-reservation-creneaux.jpg`, antérieur à `5bb3182` ;
     - bulle de l’assistant, hors du lot 2B.
   - Ces captures seront **refaites dans le cadre de B6**, sur la version publiée.
5. **B5, répétition rejouable : levée.**
   - `bin/repetition-012-013.php` est versionné, sans chemin ni secret local, avec `--exiger=10.11`.
   - 13/13 sur les deux moteurs. Il servira à rejouer 014.
6. **R6 accepté** (pas d’index sur l’e-mail normalisé) : le volume est faible aujourd’hui.
   - **Décision** : la colonne d’e-mail normalisé et son index entrent dans la **migration 014** du plan 2C, plutôt que « si le volume l’exige ».
   - Le plafond actif se calcule sous un verrou global : un balayage complet de la table allonge la section critique pour tout le monde.
7. **A12 (deux bases `_recette` orphelines)** : leur suppression revient à l’utilisateur. Ce sont des bases de recette, sans enjeu pour la publication.
8. **Preuve pour `5bb3182`** :
   - Le dernier rapport du vérificateur porte sur `8dfa0f9`.
   - `5bb3182` est un petit correctif ciblé, et j’ai rejoué son test principal.
   - Cela suffit pour lever B1 à B5 et démarrer la 2C en local. Ce ne serait pas suffisant pour une mise en ligne (voir B6).

## 4. Conditions

### Pour démarrer la 2C en local

- **C-2C-1** : le plan détaillé 2C passe d’abord en validation, avec les exigences de la décision 2B, point 6. Il doit aussi inscrire dans **014** :
  - les notes, les modèles de procédure et les étapes ;
  - la colonne d’e-mail normalisé indexée (R6) ;
  - le choix explicite « interne » ou « partagé ».
- **C-2C-2** : C7 s’applique à 014 : aller, retour arrière et reprise après interruption, sur MySQL 8.4 et sur MariaDB 10.11.15 via `bin/repetition-*`.

### B6 : préalables de mise en ligne de la 2B (inchangés et complétés)

1. **Validation 1B en conditions réelles** : premier administrateur actif, puis recette VPS 83/83.
2. **Révision isolée** pour la publication. Le push publie tout ce qui est commité : la branche poussée ne doit contenir **aucune** des modifications hors lot (assistant, `.gitattributes`, `bin/create-admin.php`, `docs/deploiement-vps.md`, `bin/serveur-local.bat`). Elles se commitent à part, avec leur propre vérification.
3. **Répétition** de 011, puis de 012-013, sur une **copie protégée** de la production MariaDB.
4. **Sauvegarde** restaurable, vérifiée par une restauration.
5. **Sur le VPS** :
   - `storage/cache/rendez-vous/` est inscriptible par PHP-FPM ;
   - la clé `.cle` est créée en 0600, et elle n’est jamais copiée depuis le poste local ;
   - aucune variable `RDV_LIMITE_*` dans le `.env` de production ;
   - nginx n’écoute pas en double pile IPv4 et IPv6 sur une même socket, ou R2 est vérifiée en conditions réelles.
6. **Nouveau rapport du vérificateur** sur le commit exact à publier, `5bb3182` ou plus récent : robot, agenda, répétitions et 18 captures refaites sur cette version. Puis **GO de mise en ligne** du validateur.
7. **Réglages de production** :
   - `AGENDA_RESERVATION_DIRECTE=0` en production (C9) ;
   - le cron reste désactivé tant que le pilote d’e-mail est `log` ;
   - Paris reste inactive (A8).

## 5. Motifs du refus

Sans objet pour B1 à B5. La mise en ligne n’est pas refusée : elle n’est pas encore soumise, et elle attend B6.

## 6. Réservé à l’utilisateur

- **Ne pas pousser maintenant.** Le push publie les trois commits, et B6 n’est pas tenue. C’est votre push qui vaut mise en production.
- **Le premier administrateur et la recette 1B en production** (mot de passe compris).
- **Les identifiants SMTP**, puis la décision d’activer la réservation directe et le cron.
- **La suppression des deux bases orphelines** : `taysir_1239d6ecaf974f7e_recette` et `taysir_5ef1de0dc48b45ff_recette`, sur MySQL local.
- **À faire vérifier par l’agence** :
  - horaires et disponibilités réelles ;
  - seuils anti-abus, au regard du public réel (familles qui partagent une adresse, par exemple) ;
  - durées par motif ;
  - textes des e-mails et de la page d’annulation.
