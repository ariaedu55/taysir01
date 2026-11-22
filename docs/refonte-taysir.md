# Refonte de Taysir Orientation Voyage

## Plan

- Inventorier les contenus et parcours du site public.
- Définir une navigation adaptée aux projets d’études et de voyage.
- Préparer la reprise des contenus WordPress dans le projet PHP natif MVC.

## Inventaire public

| Source | Contenus à reprendre | Traitement proposé |
| --- | --- | --- |
| [Accueil](https://taysir-orientation.com/) | Secteurs professionnels, destinations, écoles partenaires et témoignages | Reprendre les éléments validés par l’agence et les répartir selon le projet du visiteur. |
| [Choix de filière](https://taysir-orientation.com/quelle-filiere/) | Accompagnement pour le choix d’une formation et la candidature | Créer une entrée « Après le bac » dans le parcours Études. |
| [À propos](https://taysir-orientation.com/about-us/) | Orientation, admission, voyage, accompagnement consulaire, préparation aux entretiens et recherche de logement | Présenter les services avec leurs étapes et un accès à la prise de rendez-vous. |
| [Rendez-vous](https://taysir-orientation.com/rendez_vous_orientation/) | Filières, métiers, renseignements, logement, prise en charge et visa | Réunir les motifs dans un formulaire et permettre la présélection selon la page de départ. |
| [Contact](https://taysir-orientation.com/contact/) | Coordonnées, horaires et formulaire | Faire confirmer les coordonnées par l’agence. Le numéro parisien affiché est incomplet. |
| [Offres d’emploi](https://taysir-orientation.com/offre-demploie/) et [stages](https://taysir-orientation.com/offre_stage/) | Annonces et publications, dont plusieurs datent de 2024 | Regrouper les offres, les bourses et les formations sous « Ressources », avec archivage selon leur validité. |

## Direction proposée

- Navigation principale avec Études, Voyage, Ressources et À propos. Accès direct à « Prendre rendez-vous » et « Suivre mon dossier ».
- Accueil conçu d’abord pour le mobile, avec deux parcours visibles dès le premier écran. Réutiliser le logo et une sélection de photos de l’agence après validation, puis présenter les destinations, les partenaires et les témoignages dans des sections dédiées.
- Créer des fiches pour les destinations et les établissements, en conservant les adresses existantes lorsqu’elles restent adaptées.
- Vérifier les chiffres affichés, les partenariats et les publications avant leur remise en ligne.

## Reprise WordPress

Récupérer l’export XML via **Outils → Exporter → Tout le contenu**, puis obtenir séparément les fichiers de la médiathèque dans `wp-content/uploads/`. Le [format WXR](https://wordpress.org/documentation/article/tools-export-screen/) contient les données éditoriales exportables. Les [fichiers médias et la configuration](https://developer.wordpress.org/cli/commands/export/) sont à récupérer séparément si nécessaires.

Identifier les extensions qui gèrent les formulaires, les rendez-vous et le suivi des dossiers. Examiner leurs données et leur mode d’export uniquement si le client souhaite reprendre l’historique. Stocker les exports contenant des données personnelles dans un emplacement privé.

Associer les anciennes URL aux nouvelles pages. Prévoir une redirection permanente pour les URL remplacées et vérifier les liens, les médias et les parcours sur une version locale avant le basculement.

## Résultat de cette étape

L’inventaire initial et la proposition de navigation sont consignés ici. Le fonctionnement des formulaires, du suivi des dossiers et les données de l’administration restent à vérifier avec les exports. L’export WordPress, les fichiers médias et le code de la refonte restent à intégrer.
