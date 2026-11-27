# Revue indépendante de l’étape 1B

## Périmètre

Comparer le code à la matrice des droits de `gestion-plan.md`, examiner le passage de l’ancien back-office au nouvel espace et rejouer les scénarios de recette sur des données fictives.

## Résultat du 27 novembre 2026

- Les fichiers PHP examinés passent la vérification de syntaxe.
- La recette passe ses 56 contrôles sur 56 sous PHP 8.4.15 et MySQL 8.4.7. Elle a tourné dans une copie temporaire avec une base au nom unique, un port distinct et des dossiers de sessions et de pièces dédiés. Les scénarios et leurs assertions ont été conservés. La base et la copie de test ont été supprimées.
- T7 confirme deux refus HTTP 403, l’absence du dossier dans la liste, le compteur et la recherche du conseiller, et deux entrées dans le journal. La protection SQL contre la suppression du client est également vérifiée.
- La vérification porte sur les scénarios HTTP et SQL existants. Les migrations sur une copie de production et l’ergonomie dans le navigateur restent à vérifier avant mise en ligne.

## À corriger avant mise en ligne

1. **Rendez-vous.** La matrice réserve aux conseillers et agents d’orientation leurs propres rendez-vous. Le contrôleur, « Ma journée » et les compteurs utilisent actuellement une liste commune. Appliquer un périmètre cohérent à la liste, au compteur et aux modifications. Si l’affectation des rendez-vous arrive dans la phase suivante, réserver temporairement le module à l’administrateur et à la secrétaire. Ajouter un contrôle avec deux comptes de même poste. Voir `app/Controllers/Gestion/RendezVousController.php` et `app/Services/Coquille.php`.
2. **Prise et transmission d’un dossier.** Un poste vacant suffit pour que le dossier soit visible à ce poste. La règle de prise donne aussi accès au formulaire d’affectation. Séparer la prise pour soi de la transmission à un autre membre et préciser le moment où un dossier entre dans la file du poste suivant. Garantir côté base qu’une prise simultanée conserve un seul résultat, sans écrasement de l’affectation du premier arrivé. Prévoir la réaffectation ou le signalement des dossiers d’un membre désactivé ou qui perd son habilitation. Voir `app/Services/Access.php`, `app/Controllers/Gestion/DossierController.php` et `app/Models/Assignment.php`.
3. **Retour après connexion.** Le contrôle distingue actuellement les chemins équipe et client par leur préfixe. Une destination d’équipe peut être conservée même si le module est interdit à ce poste. Vérifier le module pour éviter une page 403 juste après connexion et conserver le contrôle d’accès sur la ressource à son ouverture. Voir `app/Services/Access.php`.
4. **Recette et migrations.** `bin/recette.php` supprime une base de nom fixe au démarrage et utilise le dossier de pièces de l’instance où il tourne. Prévoir un environnement de préproduction séparé, une base unique par exécution, un compte SQL dédié et une vérification que le serveur HTTP lancé appartient à la recette. Répéter les migrations sur une copie protégée avec des affectations préexistantes et vérifier la restauration. L’outil enregistre chaque migration après le fichier entier et MySQL applique les modifications de schéma au fil des instructions. Documenter la reprise après une migration interrompue.

## Points de suivi

- Le rattachement des comptes à Dakar est en place. Le périmètre entre agences reste à implémenter et à tester avant d’activer une deuxième agence.
- Ajouter une recette du cycle de vie des missions et des affectations, ainsi que du passage d’un dossier d’un poste à l’autre. Vérifier l’alimentation du journal pour les changements sensibles et rendre visibles les erreurs d’écriture du journal.
- L’espace client réel rend encore son tableau de bord avec la présentation existante. La maquette du parcours client reste une référence pour les phases qui doivent le raccorder aux dossiers et aux pièces attendues.
- Isoler le commit de la 1B. L’arbre de travail contient également des changements concernant l’assistant.
