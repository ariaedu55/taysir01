# Inventaire de l’export WordPress

## Plan

Vérifier le format de l’export, compter les contenus par type et repérer les pages prioritaires pour la migration PHP MVC.

## Résultat du 20 novembre 2026

Le fichier `taysirorientationvoyage.WordPress.2026-11-20.xml` a été lu depuis le dossier Téléchargements. Il est désormais rangé dans `storage/exports/`. L’export WXR 1.2 contient 1 266 éléments.

| Type | Nombre |
| --- | ---: |
| Pages | 78, dont 77 publiées |
| Articles | 84, dont 83 publiés |
| Références de médias | 909, toutes avec une URL |
| Entrées de menu | 107 |
| Modèles Elementor | 80, dont 79 publiés |
| Quiz QSM | 3 |
| Entrées Shortcoder | 2, dont 1 publiée |
| Styles globaux WordPress | 3 |

L’export déclare aussi 33 catégories, 437 étiquettes et 18 autres termes. Des métadonnées `_elementor_data` figurent sur 199 éléments. Examiner ces données lors de l’extraction pour récupérer les textes, liens et références d’images intégrés aux mises en page Elementor.

## Première sélection pour la refonte

- Accueil et agence — examiner `home-2`, `about-us`, `contact` et `faq`. Confirmer quelle page sert actuellement d’accueil.
- Études — reprendre en priorité `apres-le-bac`, `quelle-filiere`, `universites`, `demande-dadmission` et les fiches établissements.
- Voyage — inventorier `destination`, les fiches pays, `demande-de-visa`, `vente-billet-davion` et `reservation-hotel`.
- Rendez-vous et suivi — examiner `rendez_vous_orientation` et `suivre-son-dossier`. Vérifier les formulaires et extensions qui assurent le traitement et établir si l’historique doit être repris.
- Ressources — trier les articles par catégorie et vérifier la validité des offres avant publication. Vérifier l’usage des quiz avant de les recréer.

## État de la migration

L’inventaire est terminé. L’import PHP et le téléchargement des médias restent à faire. Pour les médias, récupérer une copie de `wp-content/uploads/` depuis LWS. Conserver l’export XML hors de la racine web.
