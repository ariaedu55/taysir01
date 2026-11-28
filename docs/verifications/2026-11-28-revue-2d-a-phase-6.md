# Revue du travail de Codex : étape 2D à phase 6 (2026-11-28)

Revue faite en ligne (sans sous-agent) : lecture ciblée du code, robots, navigateur intégré avec les comptes de recette locaux (`storage/recette-comptes-locaux.md`, hors dépôt).

## Contrôles

| Contrôle | Résultat |
| --- | --- |
| Syntaxe de tous les fichiers PHP modifiés ou nouveaux | aucune erreur |
| Migrations 016 à 022 sur la base de développement | toutes appliquées |
| `bin/recette.php`, MySQL 8.4 | **166/166** |
| `bin/recette.php`, MariaDB 10.11.15 isolée | **166/166** |
| `bin/recette-agenda.php` | **échouait** (migrations arrêtées à 014) ; corrigé : **12/12** |
| Parcours dans le navigateur | administrateur, comptable, prospection, commerciale ; téléphone (375 px) sur la fiche prospect |

Parcours joués avec des données fictives : visite à l’accueil → fiche → ouverture d’un dossier → pièce attendue → devis, règlement Wave et reçu PDF → établissement, campagne, prospect → attribution à la commerciale → « Ma journée » de la commerciale.

## Points solides

- Droits par module : finance (administrateur, comptable), doublons (administrateur), accueil (administrateur, secrétaire), prospects (prospection, commerciale, administrateur).
- Import Excel : `DOCTYPE` et `ENTITY` refusés, `LIBXML_NONET`, tailles et nombre de lignes plafonnés.
- Fusion des doublons : transaction, verrou, refus si une fiche a un compte ou un accès tuteur, toutes les tables liées reprises, fiche source conservée (`merged_into`), journal.
- Espace client : reçus réservés au titulaire, accès tuteur à permissions explicites, refus en 404.
- Charte : aucune bordure colorée ni dégradé dans la gestion ; rouge réservé aux actions risquées (refuser, retirer, supprimer, fusionner).

## Écarts

| N° | Écart | Suite |
| --- | --- | --- |
| 1 | `recette-agenda.php` ne passait plus (s’arrêtait à 014) | **corrigé** |
| 2 | « Échéances et résultats » et « Devis et règlements » : très grand titre au lieu de l’en-tête de carte | **corrigé** (robot 166/166 après) |
| 3 | Accueil : « Lier à une fiche » demande le **numéro interne** de la fiche ; laisser vide crée une fiche, sans le dire | **corrigé** : fiches proposées par nom, téléphone ou e-mail, ou « Créer une nouvelle fiche » ; choix explicite obligatoire (plus de création par défaut) |
| 4 | Accueil : la visite ne demande ni téléphone ni e-mail ; la fiche créée n’a aucune coordonnée | **corrigé** : téléphone ou e-mail obligatoire sur la visite (migration 023, aller-retour testé), repris sur la fiche ; e-mail déjà connu → « à rapprocher » |
| 5 | Comptable : aucune entrée « Finance » dans le menu, aucune liste des soldes et échéances ; tout passe par chaque dossier | **corrigé** : page « Soldes et échéances » (`/gestion/finance/`, menu Finance, administrateur et comptable) : totaux, filtres « solde à encaisser » et « en retard », recherche ; retard = échéances arrivées moins encaissé |
| 6 | Reçu PDF minimal (817 octets, texte seul, sans en-tête de l’agence) | **corrigé** : reçu A4 avec logo, coordonnées du siège, numéro, montant mis en avant, détail, mention « ANNULÉ », pied de page (mentions à faire valider par l’agence) |
| 7 | Bloc « Créer une tâche » et bouton « Devis et règlements » placés au-dessus de l’en-tête de la fiche et du dossier | **corrigé** : rangée d’outils sous l’en-tête (fiche et dossier) |
| 8 | Rien n’est commité : environ 70 fichiers dans l’arbre de travail, mêlés aux fichiers de l’assistant | **commité** par lots, sans l’assistant (`5cc9598`, `fbf6025` et suivants) |
| 9 | Contrôle visuel sur tablette et téléphone, écran par écran | reste dans la recette d’ensemble |

Après corrections : `recette.php` **166/166** (scénario 4A adapté : téléphone sur la visite, choix explicite).

Après les écarts 5 et 6 : `recette.php` **168/168** (deux contrôles ajoutés : logo et titre du reçu ; accès à la synthèse finance).
