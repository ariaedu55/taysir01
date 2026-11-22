# Initialisation du projet

## Plan

- Préparer une arborescence MVC pour PHP natif, CSS et JavaScript.
- Prévoir les emplacements de la configuration, des routes, des migrations et des fichiers privés.
- Vérifier les dossiers créés et consigner leur rôle ici pour les prochains agents.

## Résultat

Les dossiers suivants ont été créés et vérifiés.

| Emplacement | Rôle prévu |
| --- | --- |
| `app/Controllers/` | Traiter les requêtes et sélectionner la réponse. |
| `app/Models/` | Représenter les données métier et gérer leur persistance. |
| `app/Views/` | Stocker les vues PHP, les gabarits et les fragments réutilisables. |
| `app/Core/` | Accueillir les composants communs du MVC, dont le routeur. |
| `app/Services/` | Regrouper les traitements métier partagés. |
| `app/Middleware/` | Accueillir les contrôles communs aux requêtes, dont les vérifications d'accès. |
| `config/` et `routes/` | Stocker la configuration et les déclarations de routes. |
| `database/migrations/` et `database/seeds/` | Gérer les évolutions du schéma et les données d'initialisation. |
| `public/assets/` | Servir les fichiers CSS et JavaScript ainsi que les images publiques. |
| `storage/logs/` et `storage/uploads/` | Stocker les journaux et les pièces de dossier en dehors de la racine web prévue. |
| `tests/` | Accueillir les tests du projet. |
| `docs/` | Conserver la documentation et l'historique des travaux. |

Cette étape a créé l'arborescence. Le code de l'application reste à écrire. Le futur point d'entrée est `public/index.php`. Lors de la configuration d'Apache, utiliser `public/` comme racine web pour garder notamment les pièces de dossier hors de l'accès HTTP direct.
