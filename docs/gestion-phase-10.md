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
