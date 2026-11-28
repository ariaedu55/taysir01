# Phase 4 — Accueil et doublons

L’agent principal réalise les contrôles et note leurs résultats. Les deux agents de vérification et de validation sont retirés. L’agent de déploiement est réservé aux mises en ligne demandées.

## Plan local

- Ajouter une file de visites du jour à l’accueil, avec statut et conversion vers une fiche nouvelle ou existante.
- Proposer à l’administrateur les fiches qui partagent un nom et une date de naissance, un téléphone ou un e-mail. Comparer les données avant une action explicite. Conserver la fiche absorbée et rediriger son ancienne adresse.
- Garder les réserves de publication de la phase 3 pour la recette d’ensemble.

## Résultat local

Le registre est accessible à l’accueil et à l’administration. Une visite peut être reçue, terminée et rattachée à une fiche existante ou à une fiche créée depuis le registre.

L’administration compare les correspondances possibles et choisit la fiche à conserver. La fusion relie les dossiers, rendez-vous, notes, tâches et visites à cette fiche. L’ancienne adresse redirige après contrôle des droits. Une fusion impliquant une fiche liée à un compte ou un accès tuteur actif est reportée à un parcours dédié, car elle peut transférer des droits de lecture.

La migration 020 est appliquée à WAMP. Le robot MySQL a passé 146 contrôles, dont la création unique de la fiche depuis une visite et la redirection après fusion. La qualification MariaDB 10.11 et le contrôle visuel restent dans la recette d’ensemble. Aucune action sur le VPS.
