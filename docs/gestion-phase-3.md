# Phase 3 — Suivi du dossier et des pièces

## Plan

1. Livrer la liste des pièces attendues, le dépôt par pièce et la validation ou le refus motivé. Réutiliser les exigences des étapes déjà copiées dans le dossier et permettre à l’équipe de préciser les exigences au cas par cas.
2. Compléter les échéances et rappels, puis suivre les résultats par établissement.
3. Ajouter le lien parent ou tuteur après vérification et définir sa révocation. Vérifier les droits et le parcours complet avant la mise en ligne.

## Première tranche

Le client voit, sur son dossier, la pièce à fournir et son état. Le retour après refus indique ce qu’il doit corriger. L’équipe utilise la même liste et les contrôles existants du dossier. Les pièces restent privées. Les conditions de validité doivent être explicites pour le dossier concerné et refléter les règles vérifiées de sa destination et de sa démarche.

Les écrans réutilisent les cartes de gestion, le statut partagé, le retour de formulaire et le composant de dépôt existants. Les actions courantes sont bleues, la validation verte et le refus rouge dans l’espace équipe.

## Bilan local du lot 3A — 28 novembre 2026

- La migration 017 ajoute les pièces attendues et relie le fichier courant à la bonne exigence. Les titres issus d’une procédure sont tirés des étapes figées du dossier. L’équipe peut aussi ajouter une exigence manuelle et préciser sa date minimale de validité.
- Le client peut déposer une pièce, lire le motif d’un refus et déposer une nouvelle version après un refus ou une expiration. Une ancienne page ne peut ni remplacer une version plus récente ni prendre une décision sur celle-ci. Les fichiers antérieurs restent accessibles aux comptes autorisés.
- Le dépôt libre utilise la même transaction de dossier pour contrôler la clôture, le quota et les écritures. L’historique et l’e-mail de notification indiquent le nombre de fichiers déposés.
- La recette HTTP passe 139 contrôles sur MySQL 8.4.7 et MariaDB 11.4.9. Les 137 contrôles précédents passent sur MariaDB 10.11.15, ainsi que la protection du retour arrière 017. Le dernier passage sur 10.11.15 a été bloqué par le mode d’authentification du compte local.
- La condition de clôture locale du validateur est remplie avec 139 contrôles réussis sur les deux moteurs. La migration 017 est appliquée à la base WAMP de navigation locale. Aucune action sur le VPS.

La suite de la phase 3 couvre les échéances et les rappels, les résultats d’admission par établissement, et l’accès parent ou tuteur vérifié. Les règles documentaires propres à chaque destination doivent être renseignées à partir des règles validées par l’agence. La qualification complète de la dernière recette sur MariaDB 10.11.15 et le contrôle visuel responsive restent à faire avant publication.

## Suite de la phase 3 — Plan local

Ajouter des échéances explicites par dossier, des décisions d’admission distinctes par établissement et une délégation vérifiée par dossier. Contrôler les écritures sur les dossiers ouverts et vérifier la révocation à chaque accès. Rappeler les échéances ouvertes la veille et vérifier encore leur état à la livraison.

## Bilan local du lot 3B — 28 novembre 2026

- L’équipe ajoute et clôture les échéances du dossier. Le client les voit dans son espace. « Ma journée » reprend les échéances des dossiers affectés. Les résultats d’admission sont suivis séparément par établissement.
- Le cron prépare les rappels la veille, heure de Dakar, avec dédoublonnage par échéance et compte destinataire. La livraison revérifie l’état de l’échéance, du dossier et l’accès du compte. La qualification spécifique d’une réaffectation après mise en file reste à couvrir.
- L’administrateur confirme le lien vérifié hors outil et saisit la référence interne du justificatif avant de partager le dossier. Le droit de dépôt ciblé est facultatif. L’administrateur et le titulaire peuvent révoquer l’accès. La référence privée n’est pas affichée aux clients.
- Recette finale : 144/144 sous MySQL 8.4.7 et MariaDB 11.4.9. Le passage MariaDB 10.11.15 à 144/144 et les 10 contrôles des migrations 018 et 019 précèdent les derniers ajustements de revue. Le validateur a donné son GO local sous condition des deux relances, désormais réussies.
- Les migrations 018 et 019 sont appliquées à WAMP. Le contrôle visuel à 1440, 768 et 375 px, la recette de la révision finale sur MariaDB 10.11 et l’activation des rappels en production restent en attente. Le VPS n’a pas été modifié.
