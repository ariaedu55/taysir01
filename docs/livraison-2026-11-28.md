# Livraison des démarches, de l’espace client et des réglages de l’assistant

## Préparation et vérification

La livraison regroupe les sept commits locaux à la suite de `26e23c6`. Elle comprend la navigation basse de l’espace client, la page « Mes demandes », les dépôts groupés avec aperçu, le catalogue de démarches et le formulaire de clé DeepSeek réservé aux administrateurs.

1. Comparer les changements avec la version du VPS et vérifier les fichiers destinés au dépôt.
2. Contrôler les écrans sur ordinateur, tablette et téléphone et corriger les écarts.
3. Vérifier les fonctions et les migrations sur MariaDB 10.11.
4. Vérifier les sauvegardes chiffrées sur le VPS, appliquer les migrations 025 et 026 pendant la maintenance de Taysir et contrôler les routes avant la réouverture.

## Résultat de la revue

- Chrome, à 375, 768 et 1 440 px. Aucun débordement horizontal sur « Mes demandes », le détail d’une demande, le formulaire de visa, « Démarches et pièces » et les réglages de l’assistant.
- Dépôt vérifié dans Chrome, avec ajout successif, aperçus d’image et de PDF, retrait et rejet d’un format non pris en charge. Le libellé indique quand la nouvelle sélection remplace les fichiers précédents.
- La liste des pièces reste synchronisée lors de changements rapides de pays et de motif. Après une erreur de formulaire, le champ « Autre pays » peut s’afficher dès le rendu si cette option était sélectionnée.
- Le bouton de prise de rendez-vous utilise le bleu des actions courantes. Le vert de l’enregistrement des pièces indique la validation.
- Recette MariaDB 10.11.15 et répétition sur données fictives. 241 contrôles fonctionnels et 19 contrôles de migration et de retour arrière réussis.

Les modèles de démarches livrés en brouillon restent à vérifier et à publier depuis l’administration. La vérification de l’assistant couvre la gestion de la clé et les droits d’accès. L’appel à DeepSeek dépend de la clé et du compte configurés en production.
