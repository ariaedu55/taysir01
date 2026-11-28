# Phase 10 — Interfaces client et équipe

## Plan

1. Recomposer l’accueil client autour d’une prochaine action calculée à partir du dossier, puis décliner cette hiérarchie sur la liste des demandes et la fiche. Séparer la navigation de l’espace connecté de celle du site public.
2. Donner aux huit postes une entrée métier dans « Ma journée », réutiliser la navigation contrôlée par les droits et harmoniser les listes, formulaires et indicateurs.
3. Contrôler en données fictives les parcours et le rendu à 1440, 768 et 375 px. Consigner les mesures et les éventuelles limites.

Les espaces utilisent Poppins, des surfaces teintées sans bordures colorées, des indicateurs compacts et les boutons bleus, verts, orange et rouges selon leur fonction. Les permissions, routes métier et formulaires existants restent la référence.

## Résultat

L’espace client a sa propre navigation avec « Mon espace », « Mes demandes » et « Rendez-vous ». La page d’accueil affiche une seule action prioritaire. Une pièce à déposer passe en premier, puis une réponse attendue, un dossier ouvert, un rendez-vous à venir ou une démarche à lancer. Le lien de dépôt mène directement à la ligne de la pièce. Les pages de liste et de détail reprennent les mêmes composants de statut, formulaires et actions.

« Ma journée » présente une action principale par poste, du pilotage pour l’administrateur aux échéances pour le comptable. La barre latérale, la recherche et les raccourcis mobiles restent alimentés par le périmètre réel. Les grilles, tables et compteurs reprennent les mêmes conventions dans les modules.

### Contrat d’interface

- Les contrôles PHP gardent les droits, le CSRF et les redirections. Les boutons et liens désignent la tâche en cours. Les champs, sélecteurs et zones de dépôt utilisent les composants natifs et les classes existantes.
- Le dossier affiche le statut et l’action possible avant l’historique. L’équipe dispose d’un lien principal par poste. Les autres actions restent dans la navigation et dans les sections métier.
- Les liens d’évitement restent en tête de navigation au clavier. À largeur réduite, les boutons s’empilent et les indicateurs gardent le nombre et l’icône sur la même ligne. Les couleurs d’action suivent les jetons de `app.css`.

### Vérifications du 28/11/2026

- Recette HTTP **227/227** sur MySQL et MariaDB 10.11. Les nouveaux contrôles vérifient l’action principale pour les huit postes et l’accès du bon client à la pièce désignée.
- **57 contrôles d’affichage** dans Chrome à **1440, 768 et 375 px**, sur les tableaux de bord et le module principal des huit postes, puis l’accueil, la liste et la fiche du client. La passe finale ne relève aucun débordement horizontal, aucune action principale hors cadre, aucun indicateur contrôlé dont le chiffre passe sous l’icône et aucun échec de focus du lien d’évitement. Les mesures ont été réalisées avec les polices distantes bloquées. Poppins est la police déclarée et une police système prend le relais hors ligne.
- Quatorze [captures fictives](captures/gestion-10/) documentent l’accueil et le dossier client sur les trois formats et les huit accueils équipe sur téléphone. Les vues secondaires ont bénéficié des styles partagés. La vérification visuelle page par page est limitée aux écrans cités ci-dessus.

## Téléversement : plusieurs fichiers et aperçu (28/11/2026)

Demande de l’utilisateur : envoyer plusieurs documents à la fois et les prévisualiser avant de valider.

- **Plusieurs fichiers par pièce attendue** (recto-verso, plusieurs pages), jusqu’à `DocumentStorage::MAX_FILES` (3) fichiers de 5 Mo.
  - La migration **025** ajoute `request_documents.deposit_batch` : les fichiers d’un même envoi partagent ce jeton.
  - `request_requirements.document_id` reste le premier fichier de l’envoi : c’est l’ancre de la version que l’équipe valide ou refuse, donc la validation ne change pas.
  - La limite de 10 dépôts par jour compte les envois, pas les fichiers.
  - Le retour arrière est refusé s’il existe un envoi de plusieurs fichiers.
- **Fiche du dossier :** le client et l’équipe voient tous les fichiers de la version (« fichier 1 sur 2 »…).
- **Dans le navigateur (`portal.js`)**, pour le formulaire de demande, le message au conseiller et les pièces attendues :
  - les fichiers s’ajoutent en plusieurs fois, sans doublon ;
  - chaque fichier a une miniature (l’image, ou une tuile PDF), un bouton « Aperçu » et un bouton « Retirer » ;
  - un compteur indique « 2 fichiers sur 3 » ;
  - l’aperçu s’ouvre dans une fenêtre : image entière, ou PDF dans le lecteur du navigateur, avec un lien « Ouvrir dans un nouvel onglet » pour les téléphones ;
  - sans `DataTransfer` (anciens navigateurs), on garde la sélection simple avec la liste.
- **Vérifications :**
  - robot : 4 fichiers refusés ; 2 fichiers déposés ensemble, visibles par l’équipe et le client ;
  - répétition des migrations 011 à 025 : 19/19 sur MySQL 8.4 et sur MariaDB 10.11 ;
  - parcours réel dans le navigateur, sur le dossier de démonstration local : ajout en deux fois, aperçu de l’image et du PDF, retrait, envoi (« 2 fichiers », pièce « À vérifier »).
