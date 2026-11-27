# Phase 1 : socle sécurisé et coquille de l'espace équipe

Référence : [gestion-plan.md](gestion-plan.md) (version 2.3), §5 (phase 1), §3 (droits) et §12 (parcours par poste).

La phase 1 se fait en **deux étapes**, chacune validée par l'utilisateur :

- **1A. Maquette navigable**, avec des données **fictives**, pour valider l'expérience **avant** de construire les écrans réels :
  - la navigation des 8 postes ;
  - « Ma journée » ;
  - une liste et une fiche dossier ;
  - le suivi côté client, en parcours *études* et *voyage*.
- **1B. Socle réel** :
  - postes, permissions et filtre de périmètre ;
  - règle unique de redirection après connexion ;
  - passage de `/admin/` à `/gestion/` avec ses redirections ;
  - correction de l'accès aux pièces par affectation ;
  - journal d'audit ;
  - remplacement des cascades par `RESTRICT` ;
  - robot de recette.

---

## 1A. Maquette navigable : plan (avant réalisation)

### Objectif

Faire essayer à l'utilisateur, **sur ordinateur et sur téléphone**, la nouvelle interface de gestion dans la charte Taysir, sur des tâches concrètes. Recueillir ses remarques, corriger, puis seulement décliner les autres écrans.

### Où et comment

- **Où** : dans la plateforme elle-même, sous `/gestion/maquette/`.
- **Accès** : **uniquement en local**, si `GESTION_MAQUETTE=1` dans `.env`. En production la variable est absente, donc la maquette n'existe pas (404).
- **Données** : **toutes fictives** (`app/Views/gestion/maquette/donnees.php`) : noms inventés, aucune donnée réelle, aucune écriture en base. Les actions (confirmer, valider, attribuer…) s'animent dans la page pour montrer leur effet, sans rien enregistrer.
- **Sélecteur « Voir en tant que »** (maquette seulement) : il fait passer d'un poste à l'autre sans compte.
- **Ce qui sera réutilisé ensuite** : la mise en page de l'espace équipe et la bibliothèque de composants (`css/gestion.css`, `js/gestion.js`), qui seront branchées aux vraies données en 1B et dans les phases suivantes.

### Écrans

| Écran | Adresse locale | Contenu |
| --- | --- | --- |
| Navigation | toutes les pages | **Ordinateur** : barre latérale par groupes, avec compteurs, repliable. Barre supérieure : titre, recherche (Ctrl+K), « + Créer », notifications, poste affiché. **Téléphone** : onglets en bas et menu en tiroir |
| Ma journée | `/gestion/maquette/?poste=…` | une version par poste (8) : à faire maintenant, en retard, rendez-vous du jour, nouveautés, indicateurs du poste |
| Liste des dossiers | `/gestion/maquette/dossiers/` | onglets de vue, recherche, filtres, tri, sélection multiple et barre d'actions groupées, panneau latéral d'aperçu |
| Fiche dossier | `/gestion/maquette/dossiers/TAY-2027-0142/` | en-tête (personne, service, destination, rentrée, statut, responsables par poste), étapes du parcours, onglets : aperçu, pièces, procédure, orientation, paiements, notes, historique |
| Prospects | `/gestion/maquette/prospects/` | liste de l'agent de prospection, avec sélection multiple et « Attribuer à une commerciale » |
| Suivi client | `/gestion/maquette/client/?parcours=etudes` ou `voyage` | « Ce qu'il vous reste à faire », carte du dossier avec ses étapes, pièces, rendez-vous, paiements, messages |
| Recherche | palette (Ctrl+K) | personnes et dossiers fictifs, ouverture du résultat |

### Tâches à essayer (plan, §12.3)

| # | Tâche | Poste | Réussite si… |
| --- | --- | --- | --- |
| T1 | Confirmer un rendez-vous proposé par une commerciale ou demandé sur le site | Secrétaire | depuis « Ma journée », en moins de 3 actions, sans changer de page ; statut « confirmé » |
| T2 | Reprendre le dossier dont le client vient de déposer une pièce, et valider la pièce | Agent de traitement | le dossier est dans « Ma journée » ; on valide la pièce sans chercher |
| T3 | Comprendre quelle pièce manque et pourquoi | Client (études, puis voyage) | la pièce, sa raison et le bouton de dépôt sont visibles sans défiler sur téléphone |
| T4 | Attribuer un lot de prospects à une commerciale | Agent de prospection | sélection multiple, puis « Attribuer » ; confirmation visible |
| T5 | Transmettre un dossier orienté au traitement | Agent d'orientation | recommandation, puis « Affecter à l'agent de traitement » dans la fiche |
| T6 | Trouver un client par son nom ou son téléphone | tous | palette Ctrl+K, résultat immédiat |
| T7 | Ouvrir le dossier d'un collègue par son adresse | Conseiller | **testé en 1B**, sur le socle réel (refus 403) ; la maquette n'a pas de droits réels |
| T8 | Naviguer au clavier jusqu'à une fiche et revenir | tous | focus visible, Échap ferme les panneaux, aucun piège clavier |

### Contrôles techniques de l'étape 1A

- `php -l` sur tous les fichiers ajoutés ; feuilles CSS lues en entier ; console du navigateur sans erreur.
- Affichage à 1440 px, 768 px et 375 px ; aucun débordement horizontal.
- Navigation entièrement au clavier ; contrastes de la charte.
- Maquette inaccessible (404) si `GESTION_MAQUETTE` est absent, comme en production.

---

## 1A. Résultat

État : **maquette terminée, en attente de la validation de l’utilisateur** (charte et ergonomie). L’étape 1B ne commence qu’après son accord explicite.

### 1. Livré

| Élément | Fichier | Contenu |
| --- | --- | --- |
| Contrôleur | `app/Controllers/Gestion/MaquetteController.php` | 5 pages en GET, poste choisi par `?poste=` et gardé en session ; **404 si `GESTION_MAQUETTE` n’est pas à 1** |
| Routes | `routes/web.php` | `/gestion/maquette/`, `…/dossiers/`, `…/dossiers/{référence}/`, `…/prospects/`, `…/client/` |
| Gabarit équipe | `app/Views/layouts/gestion.php` | barre latérale par groupes (repliable), barre du haut (recherche Ctrl+K, « + Créer » selon le poste, notifications), onglets en bas sur téléphone, bandeau « maquette » |
| Écrans | `app/Views/gestion/maquette/` | Ma journée (8 postes), liste des dossiers (vues, filtre, sélection, panneau d’aperçu), fiche dossier (7 onglets), prospects (attribution en lot), suivi côté client (études et voyage) |
| Données fictives | `app/Views/gestion/maquette/donnees.php` | équipe, navigation par poste, 10 dossiers, prospects, tâches, rendez-vous |
| Styles et scripts | `public/assets/css/gestion.css`, `gestion-client.css`, `public/assets/js/gestion.js` | charte du site (blanc, gris clair, texte noir, rouge pour l’action) ; notifications avec « Annuler », panneaux, palette, onglets au clavier |
| Configuration | `.env.example` | `GESTION_MAQUETTE=0` documenté (1 en local seulement) |

Les **8 postes restent distincts** : le sélecteur « Voir en tant que » montre une navigation, une « Ma journée » et un menu « + Créer » propres à chacun.

### 2. Tests (attendu, puis obtenu)

Navigateur intégré, 1440 × 900 (ordinateur), 768 × 1024 (tablette), 375 × 812 (téléphone).

| Tâche | Attendu | Obtenu | Résultat |
| --- | --- | --- | --- |
| T1 Secrétaire confirme un rendez-vous | depuis « Ma journée », moins de 3 actions | 1 clic « Confirmer » : la ligne s’efface, le compteur passe de 4 à 3, notification avec « Annuler » | réussi |
| T2 Agent de traitement valide une pièce | le dossier est dans « Ma journée », pas de recherche | « Valider » ouvre la fiche sur l’onglet Pièces, la pièce est mise en avant ; 1 clic la passe à « Validée » | réussi après correction (compteur d’onglet) |
| T3 Client : quelle pièce manque et pourquoi | pièce, raison et bouton visibles sans défiler, sur téléphone | études : les 3 actions sont visibles sans défiler, la pièce refusée en tête avec son motif ; voyage : 2 pièces et leurs boutons ; dépôt : « Envoyée, en cours de vérification » | réussi après corrections (ordre, compteur, bulle) |
| T4 Attribuer un lot de prospects | sélection multiple, « Attribuer », confirmation | « Non attribués » (6), « Tout sélectionner », « Attribuer » : les 6 passent à Khady Diouf, le compteur tombe à 0, message de liste vide | réussi après corrections |
| T5 Transmettre un dossier orienté | recommandation, puis « Affecter » dans la fiche | « Orienter » depuis « Ma journée » ouvre l’onglet Orientation ; « Affecter » : formulaire figé, responsable mis à jour, trace durable | réussi après corrections |
| T6 Trouver une personne | Ctrl+K, résultat immédiat | « kane », « sene » (sans accent) et « 77 41 » (téléphone) trouvent la bonne fiche ; Entrée ouvre ; Échap ferme | réussi après correction (périmètre) |
| T7 Dossier d’un collègue par son adresse | refus 403 | non testable en maquette (aucun droit réel) | **reporté en 1B** |
| T8 Clavier | focus visible, Échap ferme, aucun piège | 1er Tab : « Aller au contenu » avec contour de 3 px ; flèches entre les onglets de la fiche ; Échap ferme la palette, le panneau et le menu | réussi après correction (retour du focus) |

Contrôles techniques :

- **404 sans le drapeau** : avec `GESTION_MAQUETTE=0`, les 5 adresses répondent 404 ; elles répondent 200 avec 1.
- **Syntaxe** : tous les fichiers PHP passent `php -l`, et `gestion.js` est valide.
- **Console** : aucune erreur, seulement l’avertissement attendu du sélecteur de fichier ouvert par script pendant le test.
- **Largeur** : aucun défilement horizontal à 375 px.
- **Navigation** : un seul lien actif dans la barre latérale.

### 3. Corrections apportées pendant les tests

| Défaut constaté | Correction |
| --- | --- |
| Page 500 « gestion_icone() non définie » (les vues sont rendues avant le gabarit) | chargement des icônes dans `MaquetteController::show()` |
| Boutons « menu » et « fermer » visibles sur ordinateur | sélecteurs plus précis dans `gestion.css` |
| Méta des tâches coupée sous l’horloge sur téléphone ; carte d’indicateur orpheline | `white-space: nowrap` et grille `auto-fit` |
| « Ma journée » : l’échéance dépassée n’était pas en tête | tâches triées par urgence (en retard, à traiter, pour information) |
| Deux liens actifs dans la barre latérale (« Mes dossiers » et « Pièces à valider ») | seul le premier lien correspondant est actif |
| Le compteur de l’onglet « Pièces » ne baissait pas après validation | compteur décrémenté puis retiré à zéro |
| Prospects : le bouton « Non attribués » ne filtrait rien | vue réelle, combinée à la recherche |
| « Tout sélectionner » cochait aussi les lignes masquées par un filtre (risque d’attribuer des prospects invisibles) | sélection limitée aux lignes visibles ; une ligne masquée est décochée (prospects et dossiers) |
| Liste vide sans explication | message « Aucun… » dans les prospects et les dossiers ; compteur « Non attribués » mis à jour après attribution |
| Orientation : le formulaire proposait la France pour un projet au Canada | destination, rentrée et brouillon de recommandation pré-remplis d’après le dossier |
| Après « Affecter », rien de durable n’indiquait la transmission | trace « Transmis à … » à la place du bouton, champs figés et grisés |
| La recherche montrait les prospects (et leurs téléphones) à tous les postes, malgré « limités à votre périmètre » | index filtré selon la matrice : prospects pour l’admin, la prospection et la commerciale ; pas de dossiers pour la prospection |
| Échap fermait le menu mobile sans rendre le focus | le focus revient au bouton « Menu » |
| Client : « Relevé de notes » (en vérification, rien à faire) passait avant les vraies actions | actions d’abord (à refaire, puis à faire), « en vérification » à la fin |
| Client : le compteur « Ce qu’il vous reste à faire » ne baissait pas après un dépôt | compteur décrémenté |
| Client sur téléphone : la bulle « Comment puis-je vous aider ? » cachait la carte de paiement | bulle masquée sur téléphone dans l’espace client, marge basse pour le bouton de l’assistant |
| Client voyage : « répondez oui à votre conseiller » sous un bouton « Déposer » | texte aligné sur l’action proposée |

### Ce qui reste hors de la maquette

- Les actions ne sont pas enregistrées (aucune base) ; les liens marqués « bientôt » (agenda, établissements, export…) affichent une notification.
- Les droits réels, la redirection `/admin` vers `/gestion`, le journal d’audit et T7 relèvent de l’étape 1B.

## 1A bis. Passe de design et ajustements de la revue : plan (avant réalisation)

Demande de l’utilisateur du 2026-11-26 : l’espace équipe paraît trop sobre. Il faut une identité plus colorée, élégante et dynamique, cohérente avec Taysir, et traiter les 4 ajustements de la revue. La 1B reste bloquée jusqu’à la validation.

### Direction de couleur (espace équipe uniquement)

Le site public garde sa charte. Dans `/gestion/`, une palette secondaire **à fonds teintés pâles**, sans aucun dégradé, aide à lire le travail du jour. Chaque couleur porte une famille de missions, toujours avec un libellé explicite (jamais la couleur seule).

| Famille | Teinte | Usage |
| --- | --- | --- |
| Rendez-vous et accueil | bleu clair | agenda, à confirmer, file d’attente |
| Orientation et conseil | lilas | à orienter, entretiens |
| Pièces et validations | vert doux | validées, transmis |
| Échéances proches | ambre | à traiter bientôt |
| Urgences | rouge clair et rouge | en retard, échéance dépassée |
| Finance | sarcelle | encaissements, règlements |
| Prospection | orange doux | prospects, campagnes |

Les boutons principaux passent sur le rouge d’action `--action` (#ce0f0f, contraste d’environ 5,7:1 avec le blanc ; le rouge d’accent #f11515 n’offre que 4,3:1, sous le seuil AA de 4,5:1). Le texte reste noir sur les fonds teintés.

### Travaux

1. **Indicateurs** : fond teinté, icône colorée dans une pastille, gros chiffre, libellé et détail ; une couleur par famille.
2. **Navigation** : chaque groupe métier a sa couleur. L’icône active est colorée, l’état actif est un fond teinté avec un filet, et le compteur est collé à sa rubrique.
3. **Raccourcis mobiles selon le poste** : la prospection ouvre sur Prospects, la comptabilité sur À encaisser, etc. Les destinations encore en chantier sont marquées « bientôt » et ne sont jamais proposées comme raccourci principal.
4. **« Ma journée » composée selon le poste** :
   - secrétaire : rendez-vous à confirmer et accueil d’abord ;
   - traitement : pièces à vérifier et échéances ;
   - comptable : règlements et échéances financières (nouvelle section).
5. **Fiche dossier** :
   - onglets ordonnés et filtrés selon la mission (secrétaire : aperçu, rendez-vous, historique ; comptable : paiements d’abord ; etc.) ;
   - progression plus lisible ;
   - **référence inexistante = 404** (au lieu de la première fiche) ;
   - le refus d’une pièce met à jour le compteur comme la validation ;
   - retour à la liste conservé.
6. **Parcours clients** :
   - la « prochaine action » est mise en évidence ;
   - l’assistant flottant ne recouvre rien sur les écrans courts (375 × 667) ;
   - les boutons sont sur le rouge d’action.
7. **Simulation des transferts** : le bandeau et les messages disent clairement que l’état n’est gardé que dans la page courante.
8. **Finitions** : transitions courtes (panneaux, filtres, confirmations), focus visible, états vides et d’erreur, `prefers-reduced-motion` respecté.

### Recette prévue

- Reprise de T1 à T6 et T8.
- Refus d’une pièce avec son motif (compteur mis à jour).
- Transfert relu depuis le poste destinataire (limite indiquée).
- Retour depuis la liste des dossiers.
- Référence inconnue : 404.
- Captures en 1440, 768 et 375 px pour la secrétaire, l’agent de traitement et le comptable (plus la prospection sur mobile), et pour les parcours études et voyage.

## Finition visuelle de la 1A

Reprendre les écrans existants. Donner une couleur à chaque famille de travail par les surfaces, les pastilles et les icônes. Réduire les cartes d’indicateurs à l’icône, au chiffre et au libellé. Compacter les écrans sur téléphone et vérifier les parcours de la secrétaire, du traitement, de la comptabilité et du client. Les contours décoratifs restent neutres. Documenter les résultats avant la validation de la maquette.

Les mini-cartes des huit postes affichent une icône sur un aplat, un chiffre et un libellé court. Les périodes utiles figurent dans le libellé. Le bleu identifie les rendez-vous, le lilas l’orientation, le vert les validations, l’ambre les échéances et le turquoise la finance. La navigation, les en-têtes et les onglets reprennent les mêmes repères de couleur. La prochaine action du client se distingue par sa pastille et son bouton. Les cartes et alertes utilisent des fonds teintés avec des contours neutres.

Sur téléphone, les cartes et les espacements sont resserrés. Les principales cibles tactiles mesurent au moins 44 px. Les transitions des indicateurs et des panneaux respectent la préférence de mouvement réduit.

Vérification dans le navigateur local à 1440 px pour le traitement, 768 px pour la comptabilité et 375 px pour l’accueil, la prospection et les parcours clients. Les pages mesurées ne débordent pas horizontalement. Le raccourci mobile « Prospects » ouvre la liste du bon poste. La confirmation simulée passe de quatre à trois rendez-vous et « Annuler » rétablit le compteur. L’agent de traitement ouvre la fiche sur « Pièces » et peut valider le dépôt. À 375 × 667 px, les boutons de dépôt des deux parcours clients sont visibles à l’ouverture et l’assistant flottant ne les recouvre pas. La syntaxe des quatre vues PHP modifiées est valide.

## 1A ter. Refonte du design (2026-11-27)

Demande de l’utilisateur : il n’est pas satisfait du rendu. Il veut un design très beau, organisé, coloré et bien structuré, et des éléments plus petits sur mobile. Les éléments ne doivent pas avoir de bordure colorée. Les mini-cartes indicatives ne gardent que l’essentiel. Il donne carte blanche et demande de vérifier les correctifs déjà appliqués par Codex.

### Plan (avant)

1. Vérifier ce que Codex a modifié (doc « Finition visuelle de la 1A », vues, feuilles de style).
2. Remplacer les surcharges empilées par une feuille de style unique et cohérente, pour l’espace équipe comme pour l’espace client.
3. Porter la couleur par des surfaces teintées et des tuiles d’icône, jamais par des bordures.
4. Réduire les mini-cartes à l’icône, au chiffre et au libellé.
5. Resserrer toutes les tailles sur téléphone, puis retester à 1440, 768 et 375 px.

### Constat sur l’existant (Codex)

- Correct et conservé :
  - mini-cartes réduites à l’icône, au chiffre et au libellé (le détail est passé dans le libellé) ;
  - icône de navigation dans une tuile ;
  - bandeau d’accueil teinté avec la mission du poste.
- À reprendre :
  - `gestion.css` empilait trois générations de règles qui se contredisaient ;
  - des indicateurs restaient invisibles ou à moitié transparents : leur animation décalée partait d’une opacité nulle et pouvait se figer quand l’onglet était en arrière-plan ;
  - il restait des bordures colorées sur le parcours client (prochaine action, pièce refusée, boutons à contour) ;
  - les montants « 850 000 FCFA » passaient sur deux lignes ;
  - les étapes du dossier débordaient sur téléphone ;
  - les fiches prospects étaient désordonnées sur téléphone.

### Livré

| Élément | Changement |
| --- | --- |
| `public/assets/css/gestion.css` | Réécrit d’un seul tenant (≈ 500 lignes au lieu de 540 de surcharges). Il contient : jetons, familles de couleur, coquille, navigation, cartes, indicateurs, tâches, listes, fiche, palette, notifications, et 3 paliers responsive (62rem, 48rem, 36rem). |
| `public/assets/css/gestion-client.css` | Réécrit sur les mêmes principes : prochaine action sur une surface rouge pâle avec pastille, sans contour ; boutons secondaires blancs. |
| `app/Views/gestion/maquette/donnees.php` | Unité monétaire déplacée dans le libellé : « 850 000 » + « FCFA encaissés aujourd’hui », « 6,4 M » + « FCFA encaissés ce mois ». |

L’ancienne version des deux feuilles de style est conservée hors du dépôt (copie de travail) en cas de retour arrière.

### Principes du système

- **Aucune bordure colorée.**
  - Les contours sont absents ou réduits à un filet gris très clair.
  - Les cartes se détachent par une ombre douce.
  - Les tâches sont des tuiles gris clair sans contour.
- **Couleur par famille**, toujours en surface pâle ou en tuile d’icône, avec un texte noir.

  | Famille | Couleur |
  | --- | --- |
  | Rendez-vous | bleu |
  | Orientation | lilas |
  | Validations | vert |
  | Échéances | ambre |
  | Urgences | rouge |
  | Finance | turquoise |
  | Prospection | orange |

  Les urgences ont une tuile rouge pâle entière, pas un filet.
- **Mini-cartes** : tuile pleine de la couleur de la famille avec l’icône en blanc, le chiffre en Prata et un libellé court. Aucun commentaire secondaire.
- **Hiérarchie** :
  - un bandeau d’accueil teinté selon la mission ;
  - des cartes blanches avec une tuile d’icône dans l’en-tête ;
  - des onglets de fiche en contrôle segmenté (pastille blanche sur fond gris) ;
  - une progression par pastilles : vert pour les étapes faites, rouge d’action pour l’étape en cours.
- **Téléphone (≤ 48rem)** : tout est réduit.
  - Texte de base : 0,84rem.
  - Barre du haut : 3,4rem.
  - Cartes : marges de 0,8rem.
  - Mini-cartes compactes avec l’icône au-dessus.
  - Étapes de la fiche ajustées à la largeur, sans défilement.
  - Pastille « Validée » à droite de la pièce plutôt que sur une ligne à part.
  - Fiches prospects et dossiers avec un champ par ligne.
- **Tablette (576 à 768 px)** : les mini-cartes gardent l’icône à gauche et les boutons des tâches restent à droite.
- **Animations** : courtes, sans état invisible persistant ; les lignes de tableau apparaissent en fondu sans décalage, ce qui supprime la barre de défilement parasite. Le mouvement réduit est respecté.

### Tests (attendu, puis obtenu)

| Contrôle | Attendu | Obtenu |
| --- | --- | --- |
| Bordures colorées | aucune | recherche dans les deux feuilles : **aucune** règle `border` en couleur |
| Mini-cartes | icône, chiffre, libellé | conforme pour les 8 postes ; montants sur une ligne à 375 px |
| T1 Secrétaire | 4 → 3, « Annuler » rétablit | 4 → 3 → 4 |
| T4 Prospection | 6 attribués, liste vide expliquée | 6 sélectionnés, compteur 0, message affiché |
| Refus de pièce | motif obligatoire, compteur mis à jour | erreur sans motif ; avec motif, « Refusée : … », compteur retiré |
| Recherche Ctrl+K | s’ouvre, limitée au poste | conforme |
| Référence inconnue | 404 | 404 |
| Parcours client 375 × 667 | actions visibles, rien de recouvert | études : 3 actions visibles ; voyage : 2 ; assistant intégré à la carte « Votre interlocuteur » |
| Débordement horizontal | aucun | aucun à 1440, 768 et 375 px |
| Syntaxe | valide | PHP (`php -l`) et `gestion.js` valides ; console sans erreur (hors le 404 provoqué par le test) |

Captures dans `docs/captures/gestion-1a-bis/` :

- 1440 px : secrétaire, traitement (journée et fiche) et comptable ;
- 768 px : traitement ;
- 375 px : secrétaire, comptable, liste des prospects et fiche dossier ;
- 375 × 667 px : parcours clients études et voyage.

### Corrections pendant les tests

| Défaut | Correction |
| --- | --- |
| Indicateurs invisibles ou transparents (animation décalée) | Animation retirée des mini-cartes. |
| Barre de défilement verticale dans le tableau des dossiers | Fondu sans décalage et `overflow-y: hidden`. |
| « 850 000 FCFA » sur deux lignes à 375 px | Unité dans le libellé, chiffre sans retour à la ligne. |
| Étapes de la fiche qui défilaient sur téléphone | Étapes ajustées à la largeur. |
| Fiches prospects désordonnées sur téléphone | Un champ par ligne sous le nom. |
| Mini-cartes vides et étirées à 768 px | Icône à gauche entre 576 et 768 px. |

La 1B reste bloquée jusqu’à la validation de cette version par l’utilisateur.

---

## 1B. Socle réel : plan (avant réalisation)

Accord de l’utilisateur le 27/11/2026 (« Oui, lance la 1B »), après validation du design 1A ter.

### Constat sur l’existant (vérifié dans le code)

- **Rôles** : `users.role` n’accepte que `client`, `conseiller` et `admin`. Les routes `/admin/` sont protégées par `role:admin,conseiller`.
- **Faille** : `Admin\RequestController::document()` ne vérifie **que le rôle**. N’importe quel conseiller peut télécharger les pièces de n’importe quel client, et ces téléchargements ne sont pas journalisés.
- **Listes** : la liste, les compteurs et les fiches montrent toutes les demandes à tous les conseillers.
- **Redirection après connexion** : la règle est écrite à trois endroits différents (connexion, réinitialisation du mot de passe, middleware `guest`), plus le lien « Mon compte » de l’en-tête.
- **Cascades** :
  - `service_requests.user_id` est en `ON DELETE CASCADE` vers `users` ;
  - `request_documents` et `request_events` sont en cascade vers `service_requests` ;
  - la suppression d’un compte effacerait donc ses dossiers, ses pièces et leur historique.
- **Base locale** : aucun compte ni aucune demande. Le robot crée ses propres données fictives dans une base séparée.

### Livrables

1. **Migrations**
   - `007_postes_agences.sql` :
     - les 8 postes dans `users.role` (`admin`, `secretaire`, `prospection`, `commerciale`, `conseiller`, `orientation`, `traitement`, `comptable`), plus `client` ;
     - une table `agencies` : Dakar active, Ziguinchor et Paris inactives ;
     - un champ `users.agency_id`, Dakar par défaut ;
     - une table `user_missions` pour les missions additionnelles nominatives, datées, avec l’auteur et un retrait possible.
   - `008_affectations_journal.sql` :
     - une table `request_assignments` (une affectation par poste et par dossier : conseiller, orientation, traitement), alimentée par la reprise des `assigned_to` existants ;
     - une table `audit_log` (auteur, action, objet, adresse IP, détail, date).
   - `009_restrict.sql` : les trois cascades vers les dossiers passent en `RESTRICT`.
2. **Droits** (`app/Services/Access.php`), en un seul endroit :
   - les postes et leurs libellés ;
   - la matrice du §3.5 (pour les actions présentes en 1B) ;
   - le **filtre de périmètre** SQL, utilisé partout : listes, compteurs, recherche, fiche, pièce ;
   - les contrôles `peutOuvrir`, `peutTelecharger` et `peutAffecter`.
3. **Journal** (`app/Services/Audit.php`). Événements enregistrés :
   - connexion ;
   - accès refusé ;
   - téléchargement de pièce ;
   - affectation ;
   - changement de poste, désactivation ;
   - attribution d’une mission.
4. **Une seule règle de redirection** (`Access::accueil()` et `redirect_after_login()`) :
   - un chemin de retour interne si le compte y a droit ;
   - sinon `/gestion/` pour l’équipe et `/mon-espace/` pour un client.
   - Elle sert à la connexion, à la réinitialisation du mot de passe, au middleware `guest` et à l’en-tête.
5. **Espace `/gestion/` réel**, dans la coquille validée en 1A (même gabarit, alimenté par le compte connecté) :
   - **Ma journée** : compteurs du périmètre, dossiers à reprendre, rendez-vous à confirmer selon le poste ;
   - **Dossiers** (liste et fiche) :
     - statut et note ;
     - affectation selon la matrice ;
     - pièces téléchargeables seulement par les postes autorisés et affectés ;
     - **tout accès hors périmètre renvoie 403 et est journalisé** ;
   - **Rendez-vous**, **Messages**, **E-mails** (réservé à l’administrateur), **Équipe** (8 postes, agence, missions, invitation) ;
   - recherche Ctrl+K sur les seuls dossiers du périmètre.
6. **Transition `/admin/` → `/gestion/`** : redirections **302** pour les GET (filtres conservés) et routes POST de compatibilité (même traitement, mêmes droits, même jeton CSRF), comme au §2.3. Les liens des e-mails pointent vers `/gestion/`.
7. **Robot de recette** (`bin/recette.php`) :
   - il crée une base dédiée `taysir_recette` et y applique les migrations ;
   - il y crée des données **fictives** : un compte par poste, deux conseillers, des clients, des dossiers et des pièces ;
   - il lance un serveur local sur le port 8091, rejoue les scénarios en HTTP (connexion réelle, jeton CSRF, cookies), puis s’arrête ;
   - il ne touche jamais la base du site.

### Scénarios du robot (attendu)

| N° | Scénario | Attendu |
| --- | --- | --- |
| R1 | Chaque poste se connecte | Arrive sur `/gestion/` ; un client arrive sur `/mon-espace/` |
| R2 | Retour mémorisé `?retour=/demande-de-visa/` (client) | Revient sur la page du formulaire |
| R3 | Client sur `/gestion/` et `/gestion/dossiers/` | 403 |
| **T7** | Le conseiller A ouvre le dossier du conseiller B, puis sa pièce | 403 sur la fiche et sur la pièce ; dossier absent de la liste, des compteurs et de la recherche de A ; deux lignes `acces_refuse` au journal |
| R4 | Le conseiller A télécharge la pièce de **son** dossier | 200 ; ligne `piece_telechargee` au journal |
| R5 | Secrétaire et comptable | Voient la liste et l’en-tête, mais la pièce renvoie 403 |
| R6 | Prospection et commerciale | `/gestion/dossiers/` : 403 (aucun dossier en 1B) |
| R7 | Agent de traitement affecté | Ouvre son dossier et sa pièce : 200 |
| R8 | `/admin/demandes/{réf}/` | 302 vers `/gestion/dossiers/{réf}/` ; POST de compatibilité accepté avec un jeton CSRF valide, refusé (419) sans |
| R9 | `/gestion/e-mails/` et `/gestion/equipe/` par un conseiller | 403 |
| R10 | Suppression d’un compte qui a un dossier (SQL direct) | Refusée par la base (`RESTRICT`) |
| R11 | Compte désactivé | Connexion refusée |

La maquette (`/gestion/maquette/`) reste disponible en local pour comparer.

## 1B. Résultat (2026-11-27)

État : **socle réel livré et testé en local**, en attente de la validation de l’utilisateur. Rien n’est commité, rien n’est déployé. Les migrations n’ont été appliquées que sur la base locale.

### 1. Livré

| Élément | Fichiers |
| --- | --- |
| Migrations | `database/migrations/007_postes_agences.sql` (8 postes, `agencies` : Dakar active, Ziguinchor et Paris inactives, `users.agency_id`, `user_missions`), `008_affectations_journal.sql` (`request_assignments` alimentée par la reprise des `assigned_to`, `audit_log`), `009_restrict.sql` (3 cascades remplacées par `RESTRICT`) |
| Droits | `app/Services/Access.php` : postes, missions, matrice des modules, **filtre de périmètre unique**, `peutOuvrir`, `estResponsable`, `affectables`, navigation, règle d’accueil et retour permis |
| Journal | `app/Services/Audit.php`, écran `/gestion/journal/` (administrateur) |
| Middleware | `acces:<module>` (`app/Middleware/RequireAccess.php`) : refus journalisé puis 403 |
| Redirection unique | `Access::accueil()` et `Access::retourPermis()`, utilisés par la connexion, l’inscription, la réinitialisation du mot de passe, le middleware `guest` et l’en-tête du site |
| Espace `/gestion/` | `app/Controllers/Gestion/` (`EspaceController`, `JourneeController`, `DossierController`, `RendezVousController`, `MessageController`, `EquipeController`), coquille `app/Views/layouts/espace.php` (`app/Services/Coquille.php`), vues `app/Views/gestion/*.php` dans le système de design 1A ter |
| Transition | redirections 302 depuis `/admin/` (filtres conservés) et routes POST de compatibilité (`routes/web.php`) ; liens des e-mails vers `/gestion/dossiers/` |
| Retraits | anciens `app/Controllers/Admin/`, `app/Views/admin/` et `partials/admin-nav.php` (remplacés, toujours dans l’historique git) |
| Robot | `bin/recette.php` : base dédiée `taysir_recette`, données fictives, serveur sur le port 8091, 56 contrôles HTTP et SQL, nettoyage complet |

**Principes appliqués** :
- Le menu n’est jamais la seule barrière : chaque fiche, pièce, statut et affectation est revérifié dans le contrôleur avec le même filtre que les listes, les compteurs et la recherche.
- Un dossier existant hors périmètre renvoie 403 et la tentative est journalisée. Une référence inconnue renvoie 404.
- La secrétaire et le comptable voient l’en-tête, les responsables, le suivi visible du client et les coordonnées, mais **ni les pièces, ni l’identité, ni les notes internes**.
- Un poste affectable (conseiller, orientation, traitement) voit ses dossiers et ceux qui n’ont encore personne à son poste. Il **prend** un dossier avant d’en ouvrir les pièces.
- Prospection et commerciale : « Ma journée » seulement. Leurs écrans arrivent en phase 6.

### 2. Tests (robot `bin/recette.php`, 56 contrôles, 56 réussis)

| N° | Contrôle | Obtenu |
| --- | --- | --- |
| R1 | 9 postes et 1 client se connectent | 303 vers `/gestion/` pour les 9 postes, `/mon-espace/` pour le client |
| R2 | Retour mémorisé | client vers `/demande-de-visa/` : gardé ; client vers `/gestion/` : remplacé par `/mon-espace/` ; secrétaire vers `/mon-espace/` : remplacé par `/gestion/` |
| R3 | Client sur 4 adresses de l’équipe | 403 partout |
| **T7** | Conseiller A face au dossier du conseiller B | fiche 403, pièce 403 ; dossier absent de la liste et de la recherche ; compteur de A = 2 (son dossier et un dossier à prendre) ; **2 lignes au journal** |
| R4 | A télécharge la pièce de son dossier | 200, contenu exact, 1 ligne au journal |
| R5 | Secrétaire et comptable | liste complète 200, fiche 200 sans numéro de passeport, pièce 403 |
| R6 | Prospection et commerciale | Ma journée 200, Dossiers 403 |
| R7 | Agent de traitement | affecté : fiche et pièce 200 ; non affecté : fiche (à prendre) 200, pièce 403 |
| R8 | Transition `/admin/` | 302 vers la fiche, vers la liste avec `?statut=nouvelle`, vers la pièce ; POST de compatibilité avec jeton 303 (statut modifié), sans jeton 419 |
| R9 | Modules réservés | E-mails, Équipe, Journal : conseiller 403, admin 200 ; 5 pages admin affichées sans erreur PHP |
| R10 | `DELETE` d’un client avec dossiers | refusé par la base (erreur 1451) |
| R11 | Compte désactivé | renvoyé à la connexion |
| R12 | A prend un dossier libre | 303, pièce 200 ; B reçoit ensuite 403 ; 1 ligne au journal |
| R13 | Matrice d’affectation | conseiller vers orientation : permis ; secrétaire vers traitement : 403 ; B modifie le dossier de A : 403 |

**Vérifications visuelles** (navigateur, base de recette) :
- conseiller : « Ma journée », fiche, page de refus ;
- administrateur : équipe, journal ;
- fiche sur téléphone.

Captures dans `docs/captures/gestion-1b/`.

**Site public** : l’accueil, les destinations et la connexion répondent 200 ; `/admin/` renvoie 302 vers `/gestion/`, puis vers la connexion ; la maquette reste disponible.

**Syntaxe** : tous les fichiers PHP modifiés passent `php -l`.

### 3. Corrections pendant les tests

| Défaut | Correction |
| --- | --- |
| La page 403 renvoyait l’équipe vers l’accueil public sans explication | Pour l’équipe : message (périmètre, journal, demande d’affectation) et lien « Retour à Ma journée » ; pour un client : « Retour à mon espace » |
| La fiche montrait l’identité (passeport, date de naissance, précisions) à la secrétaire et au comptable | Ces informations sont réservées au responsable ; la vue transversale garde les coordonnées (§3.5) |
| La liste de l’équipe affichait 4 actions par membre | Actions regroupées derrière « Gérer » |
| Les dates et objets du journal passaient sur deux lignes | Colonnes sur une ligne |
| `git rm` avait mis les suppressions dans l’index | Index rétabli : les fichiers sont seulement supprimés du dossier de travail |

### Mise en ligne (à faire après validation, avec accord)

1. Sauvegarder la base `jomas_taysir` du VPS.
2. Appliquer les migrations 007 à 009 (`php database/migrate.php`).
3. Vérifier que les comptes `admin` et `conseiller` existants sont rattachés à Dakar et que leurs attributions sont reprises en affectations « conseiller ».
4. Lancer le robot sur le VPS (base `taysir_recette` créée puis supprimée).
5. Après validation de la phase 1, passer les redirections `/admin/` en 301.

### Hors 1B (phases suivantes)

- Agenda complet et dossier issu d’un rendez-vous (phase 2) ; pièces attendues et rappels (phase 3).
- Écrans de prospection et de finance (phases 5 et 6).
- Filtre par agence : le champ est en place, mais invisible tant qu’une seule agence est active.

---

## 1B bis. Corrections de la revue (plan, avant réalisation)

Revue du 27/11/2026 : [gestion-revue-1b.md](gestion-revue-1b.md). Les résultats de la recette locale sont validés, mais la mise en ligne est **reportée** jusqu’aux corrections ci-dessous. Les quatre points sont fondés et sont tous traités.

### 1. Rendez-vous

- **Constat** : les rendez-vous n’ont pas encore de responsable (l’affectation arrive en phase 2). Le module ne peut donc pas filtrer « ses » rendez-vous.
- **Correction** :
  - le module est **réservé à l’administrateur et à la secrétaire** jusqu’à la phase 2 ;
  - la même règle s’applique à la liste, aux compteurs, à « Ma journée », à la navigation et aux modifications (POST et route de compatibilité).
- **Recette** : deux conseillers et un agent d’orientation reçoivent 403 en lecture comme en modification, et aucun compteur de rendez-vous n’apparaît pour eux.

### 2. Prise et transmission d’un dossier

Règle précisée, appliquée par la base et par `Access` :

| Notion | Règle |
| --- | --- |
| **File d’un poste** | Un dossier entre dans la file du **conseiller** dès sa création, puis tant qu’aucun conseiller ne le tient. Il n’entre dans la file de l’**orientation** ou du **traitement** que lorsqu’il y est **transmis** par le poste qui le tient, ou par l’administrateur. Un poste vacant ne suffit plus. |
| **Prendre** | Pour soi, seulement si le dossier est dans la file de son poste. La prise est **atomique** : un `UPDATE … WHERE user_id IS NULL`, ou un `INSERT` contre la clé (dossier, poste). Si deux personnes prennent en même temps, une seule réussit ; l’autre reçoit « déjà pris par … ». |
| **Transmettre** | Au poste suivant de la chaîne (secrétaire → conseiller, conseiller → orientation, orientation → traitement), soit **dans la file** de ce poste, soit à **une personne** de ce poste. Seul le responsable actuel du dossier peut transmettre (la secrétaire et l’administrateur ont la vue transversale). |
| **Réaffecter** | L’administrateur peut changer la personne d’un poste à tout moment. C’est une réaffectation explicite, jamais une prise. |
| **Désactivation ou changement de poste** | Les dossiers en cours du membre **retournent dans la file** de son poste, avec une trace dans l’historique et le journal. Le message de confirmation donne leur nombre. |

- **Données** : `010_files_affectation.sql`. `request_assignments.user_id` devient facultatif (`NULL` = dans la file), et deux colonnes s’ajoutent : `queued_at`, `queued_by`.
- **Recette** :
  - dossier non transmis invisible pour l’orientation et le traitement ;
  - transmission dans la file, puis prise ;
  - **double prise simultanée** : une seule réussite, la première affectation intacte ;
  - réaffectation par l’administrateur ;
  - désactivation qui remet le dossier dans la file ;
  - mission « traitement » attribuée, puis retirée.

### 3. Retour après connexion

- **Correction** : `Access::retourPermis()` déduit le **module** du chemin mémorisé (`/gestion/rendez-vous/` → rendez-vous, et ainsi de suite, y compris les anciennes adresses `/admin/`). Si le poste n’y a pas droit, le retour est remplacé par « Ma journée ». Le contrôle sur la ressource reste fait à son ouverture.
- **Recette** : un conseiller avec `?retour=/gestion/equipe/` arrive sur `/gestion/`, un administrateur sur `/gestion/equipe/`.

### 4. Recette isolée et répétition des migrations

**Robot** (`bin/recette.php`) :
- une base **unique par exécution** (`taysir_r<aléa>_recette`), créée puis supprimée par le robot lui-même. Il ne supprime jamais une base qu’il n’a pas créée et refuse de démarrer si le nom existe déjà ;
- un **dossier de pièces, de sessions et d’e-mails propre à l’exécution** (`storage/recette/<base>/`), supprimé à la fin ;
- un **port libre** choisi au lancement ;
- une vérification que le serveur qui répond est bien celui de la recette : l’en-tête `X-Taysir-Recette` doit porter le nom de la base ;
- un compte SQL dédié en option (`--sql-user`, `--sql-password`).

**Répétition des migrations** (`bin/repetition-migrations.php`), sur une **copie protégée** :
- elle part d’une base au nom unique, soit construite jusqu’à la migration 006 avec des affectations existantes fictives, soit chargée depuis une **sauvegarde de la production** (`--depuis=fichier.sql`, à fournir) ;
- elle applique 007 à 010 et vérifie : agence, reprise des `assigned_to` en affectations, `RESTRICT`, files ;
- elle **revient en arrière** avec `database/rollback/` et vérifie le retour à l’état d’avant ;
- elle réapplique les migrations, puis supprime la copie.

**Documentation** : la reprise après une migration interrompue (MySQL valide chaque changement de schéma au fil de l’eau) avec, pour chaque fichier, des instructions qu’on peut relancer et la procédure manuelle à suivre.

**Journal** : un échec d’écriture du journal devient visible. Il est signalé dans les journaux du serveur et comptabilisé dans un fichier d’alerte `storage/logs/audit-echecs.log`. Le service n’est pas bloqué.


## 1B bis. Résultat (2026-11-27)

État : les **4 points de la revue sont corrigés et testés en local**. Le robot passe **83 contrôles sur 83** (lancé quatre fois de suite) et la répétition des migrations passe **22 contrôles sur 22**. Rien n’est commité ni déployé.

### 1. Livré

| Point de la revue | Correction | Fichiers |
| --- | --- | --- |
| 1. Rendez-vous | Module réservé à l’administrateur et à la secrétaire jusqu’à la phase 2 (liste, compteur, Ma journée, navigation, modifications, route de compatibilité) | `app/Services/Access.php` |
| 2. Prise et transmission | Files par poste (`user_id` NULL = dans la file) ; trois opérations séparées : **prendre**, **transmettre**, **remettre dans la file** ; prise atomique ; réaffectation d’un poste tenu réservée à l’administrateur ; dossiers remis dans la file en cas de désactivation, de changement de poste ou de retrait de mission | `database/migrations/010_files_affectation.sql`, `Access` (`fileSql`, `enFile`, `prenables`, `transmissibles`, `liberables`), `app/Models/Assignment.php`, `DossierController::assign`, `EquipeController`, `app/Views/gestion/dossier.php` |
| 3. Retour après connexion | Le module de l’adresse mémorisée est vérifié (anciennes adresses `/admin/` comprises) : s’il est interdit au poste, le compte arrive sur « Ma journée » | `Access::retourPermis`, `Access::moduleDuChemin` |
| 4. Recette et migrations | Voir le tableau suivant | |

**Point 4 en détail :**

| Mesure | Fichiers |
| --- | --- |
| Base au nom unique par exécution, créée et supprimée par le robot seulement | `bin/recette.php` |
| Pièces, e-mails et sessions de l’exécution dans `storage/recette/<base>/`, supprimés à la fin | `bin/recette.php` |
| Port libre, serveur identifié par l’en-tête `X-Taysir-Recette` | `bin/recette.php`, `public/index.php` |
| Compte SQL dédié en option | `bin/recette.php`, `config/recette.php` |
| Répétition des migrations sur une copie, avec retour arrière | `bin/repetition-migrations.php`, `database/rollback/007…010.down.sql` |
| Reprise d’une migration interrompue | `database/migrate.php` (`--jusqua`, `--reprendre`, message d’échec précis) |
| Échecs d’écriture du journal visibles | `app/Services/Audit.php` → `storage/logs/audit-echecs.log` |

### 2. Tests

**Robot** (`bin/recette.php`, 83 contrôles) : les 56 contrôles de la 1B sont repris, avec les mises à jour suivantes.

| N° | Contrôle | Obtenu |
| --- | --- | --- |
| R0 | Le serveur qui répond est celui de la recette | en-tête `X-Taysir-Recette` = la base de l’exécution |
| R2 | Retour après connexion (7 cas) | conseiller vers Équipe ou Rendez-vous : arrive sur `/gestion/` ; administrateur vers Équipe : gardé ; secrétaire vers l’ancienne adresse des rendez-vous : gardé |
| R7 | Dossiers jamais transmis | invisibles pour le traitement (R2) et l’orientation (R3) : 403 |
| R13 | Réaffectation | un conseiller ne réaffecte pas une orientation déjà tenue (403, titulaire inchangé) ; l’administrateur le peut |
| R14 | File et **prise simultanée** | dossier mis dans la file de l’orientation : visible des deux agents, pièce fermée ; deux prises envoyées **en même temps** : une seule réussit, l’autre est renvoyée à la file avec un message, l’affectation du gagnant est intacte ; le traitement ne voit le dossier qu’après transmission |
| R15 | Rendez-vous | deux conseillers et un agent d’orientation : liste, modification et ancienne adresse POST en 403 ; ni lien ni compteur ; rendez-vous inchangé en base ; la secrétaire le voit |
| R16 | Désactivation | le dossier du conseiller B retourne dans la file ; le conseiller A le voit ; journal : `dossiers_remis: 1` |
| R17 | Mission « traitement » | attribuée : le conseiller A reçoit un dossier en traitement ; retirée : le dossier retourne dans la file ; deux lignes au journal |

**Répétition des migrations** (`bin/repetition-migrations.php`, 22 contrôles, sur une copie à l’état d’avant la 1B avec des attributions existantes) :

| Étape | Résultat |
| --- | --- |
| Migration 007 à 010 | agences correctes, 3 membres sur 3 rattachés à Dakar, 2 attributions sur 2 reprises en affectations, 3 cascades passées en `RESTRICT`, files en place, données des dossiers identiques |
| Retour arrière 010 → 007 | schéma identique (colonnes, clés, index), dossiers et comptes identiques, aucune migration 007+ enregistrée |
| Interruption simulée de 010 après l’instruction 1 | la relance échoue avec un message précis (instruction 1, migration non enregistrée, commande de reprise proposée) ; `--reprendre=010_files_affectation.sql:2` termine la migration ; toutes les vérifications repassent |

**Vérification visuelle** : dossier « dans la file », bouton « Prendre le dossier », puis carte « Transmettre : orientation » et « Remettre dans la file ». Le retour après connexion amène directement à la fiche demandée.

**Nettoyage vérifié** : aucune base `taysir_r…_recette` ni `taysir_m…_recette` restante, et `storage/recette/` vide.

Deux bases `taysir_<16 caractères>_recette`, qui ne viennent pas de ces scripts (probablement l’exécution de la revue), sont présentes sur le MySQL local. Elles n’ont pas été touchées.

### 3. Corrections pendant les tests

| Défaut | Correction |
| --- | --- |
| Prise simultanée : le perdant recevait 403. Le dossier sortait de son périmètre avant même sa tentative, ce qui était sûr mais déroutant. | Si un collègue du même poste vient de prendre le dossier, renvoi à « À prendre » avec « vient d’être pris par … ». Tout autre cas reste un refus journalisé. |
| Libellé « Dans la file du poste (le premier disponible) » coupé dans la liste | Libellé raccourci |
| 4 e-mails fictifs laissés par la première version du robot dans `storage/recette/mails/` | Supprimés ; chaque exécution a maintenant son dossier, supprimé à la fin |

### Migrations interrompues (procédure)

MySQL valide chaque changement de schéma au fil de l’eau : une migration coupée en cours de route laisse ses premières instructions appliquées, **sans** qu’elle soit enregistrée dans la table `migrations`.

1. `database/migrate.php` indique le fichier et le numéro de l’instruction en échec, et propose la commande de reprise.
2. Vérifier l’état réel avec `SHOW CREATE TABLE …` sur les tables concernées.
3. Ensuite, deux possibilités :
   - **reprendre** : `php database/migrate.php --reprendre=<fichier>:<n>` saute les instructions 1 à n−1 (déjà passées) puis enregistre la migration ;
   - **annuler** : exécuter à la main les lignes correspondantes de `database/rollback/<fichier>.down.sql`, pour les seules instructions déjà passées, puis relancer normalement.
4. Toujours partir d’une **sauvegarde** de la base, prise juste avant la mise en ligne.

### Mise en ligne (à faire après validation, avec accord)

1. Sauvegarder `jomas_taysir` (mysqldump) sur le VPS.
2. **Préproduction** : lancer `bin/repetition-migrations.php --depuis=<sauvegarde.sql>` avec un compte SQL limité aux bases `taysir\_%\_recette`. C’est la répétition des migrations sur la **copie** des vraies données.
3. Lancer `bin/recette.php` sur le VPS avec ce même compte.
4. Appliquer les migrations 007 à 010 sur `jomas_taysir`, puis vérifier les affectations reprises.
5. Après validation de la phase 1 : redirections `/admin/` en 301.

### Points de suivi (revue)

- Le périmètre entre agences reste à faire, à tester avant d’activer une deuxième agence.
- L’espace client réel garde sa présentation actuelle. Son raccordement aux dossiers et aux pièces attendues, avec validation sur mobile, est un point de contrôle des phases 2 et 3.
- Le commit de la 1B doit être **isolé** : l’arbre de travail contient aussi des changements de l’assistant.


## 1B. Commit et préproduction (2026-11-27)

### Commit isolé (branche `gestion-1b`, non poussée)

| Commit | Contenu |
| --- | --- |
| `71e9590` | Espace équipe réel 1B et 1B bis (93 fichiers) |
| `c0e1328` | Processus enfants de test lancés avec les extensions PHP du parent (correctif trouvé en préproduction) |
| `12fd520` | Scripts de retour arrière suivis par git (cachés par la règle `*.sql`) ; la répétition s’arrête si un fichier SQL manque |
| `0fc1862` | Migration 007 : `updated_at` des comptes inchangé lors du rattachement à Dakar |
| `d4cfb6c` | Chargement de la sauvegarde en TCP quand l’hôte est `localhost` |

**Restent hors des commits :**
- les changements de l’assistant (5 fichiers) ;
- `.gitattributes` et `bin/serveur-local.bat` (lanceur local) ;
- les inventaires de l’ancienne application GAOV, gardés **hors du dépôt public**.

**Avant le push** : les détails de sécurité de l’ancienne application ont été retirés du plan (§3.7) et gardés dans un document local.

La branche seule, extraite dans un dossier vierge, passe le contrôle de syntaxe et le robot (83 sur 83).

### Préproduction sur le VPS

**Isolement** :
- dossier `/root/taysir-preprod-20261127` (accès root seul), séparé du site ;
- code extrait de la branche ;
- **compte SQL temporaire** `taysir_preprod`, avec un mot de passe aléatoire jamais affiché et des droits limités aux bases `taysir\_%\_recette`, supprimé automatiquement à la fin.

**Le site n’a pas été touché.**

**Constat** : la production tourne sous **MariaDB 10.11**, et non MySQL 8.4 comme en local. C’est la raison d’être de cette répétition.

| Exécution | Résultat |
| --- | --- |
| Sauvegarde de `jomas_taysir` (`mysqldump --single-transaction`) | 600 Ko, droits 600 ; la base contient le contenu du site, **aucun compte ni aucune demande** pour l’instant |
| Répétition sur la **copie de la production** | **22 sur 22** : migration 007-010, retour arrière identique (schéma et données), interruption puis reprise |
| Répétition synthétique avec attributions existantes | **22 sur 22** (2 attributions sur 2 reprises en affectations) |
| Robot de recette | **83 sur 83** |
| Après coup | 0 base de recette restante, 0 compte temporaire, `jomas_taysir` toujours en 006, site en 200 |

**Défauts trouvés par la préproduction et corrigés** (les 4 derniers commits ci-dessus) :

| Défaut | Correctif |
| --- | --- |
| Sur le VPS, `pdo_mysql` exige `pdo` : les scripts imposaient une liste d’extensions fixe | `bin/php-enfant.php` |
| Les scripts de retour arrière n’étaient pas dans le commit, et la répétition ne l’avait pas signalé | Fichiers suivis ; un fichier SQL absent arrête tout |
| La migration 007 modifiait `updated_at` des comptes de l’équipe | Corrigé ; la répétition attend une seconde pour toujours le voir |
| Le client `mysql` lisait le `~/.my.cnf` de root, puis ne trouvait plus le socket | Fichier d’options exclusif, connexion TCP |

### Mise en ligne (en attente d’accord)

1. Décider du sort de §3.7, puis fusionner `gestion-1b` dans `main` et pousser.
2. Déployer (`bin/deploy-goo-bridge.sh`), puis appliquer `database/migrate.php` sur `jomas_taysir`. Point de retour : `/root/taysir-preprod-20261127/sauvegarde-jomas_taysir-avant-1b.sql`, à supprimer une fois la mise en ligne validée.
3. Créer le premier compte administrateur (la base n’en a aucun), puis inviter l’équipe.
4. Relancer le robot sur le VPS avec un compte SQL temporaire, comme en préproduction.
