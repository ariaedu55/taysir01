# Préparation du déploiement 2B — Agenda

## Révision et recette isolées

- Partir de la révision 2A isolée et approuvée. Inclure les migrations agenda 012 et 013 avec leurs scripts de retour, puis contrôler le diff public. Garder l’assistant et les fichiers locaux dans leur lot.
- Sur cette révision, lancer `php bin/recette-agenda.php` avec `pdo_mysql` et `mbstring`. Le script utilise le serveur SQL local, crée une base fictive `taysir_agenda_…_recette`, applique les migrations jusqu’à 012 et nettoie ses ressources. Un compte SQL dédié peut être fourni par `TAYSIR_DB_USER` et `TAYSIR_DB_PASSWORD`.
- Rejouer la recette complète, dont R2 et R15, sur la révision assemblée. Vérifier aussi que deux agents du même poste ne voient que leurs rendez-vous et fiches autorisés, y compris dans les listes, compteurs et recherches.
- Répéter 012 sur MariaDB 10.11 avec un jeu synthétique et une copie protégée de la base. Contrôler les anciens statuts, les créneaux historiques, le retour arrière et la reprise après interruption. Si la cible est encore en 010, répéter l’enchaînement 011 puis 012 sur la copie.

## Conditions de mise en service

- Obtenir la validation 1B en conditions réelles (premier administrateur actif, recette VPS à 83/83), clore les réserves de publication 2A et obtenir le rapport du vérificateur puis le GO du validateur pour la 2B.
- Contrôler la version et les migrations de départ, la configuration locale du VPS, puis créer et vérifier une sauvegarde restaurable. Le script Webuzo `bin/deploy-goo-bridge.sh` met à jour le code avant d’appliquer les migrations en attente. En cas d’interruption, relever la dernière instruction réussie et contrôler le schéma avant toute reprise.
- Avant un retour arrière, arrêter les écritures et sauvegarder la base. Le retour SQL 012 supprime les disponibilités et l’historique de l’agenda, retire les champs 2B et convertit `honore` et `absent` en `termine`, puis `reporte` en `annule`.
- Après un déploiement autorisé, vérifier la révision servie, le journal des migrations, PHP-FPM, l’accès à l’agenda selon le poste et la demande publique de rendez-vous. Laisser la réservation directe désactivée jusqu’à la mise en place du SMTP et de disponibilités réelles. Garder le cron désactivé avec le pilote mail `log` et la maquette inactive en production.

Références [plan 2B et conditions C7/C9](gestion-phase-2.md), [décision 2A](validations/2026-11-27-fin-etape-2a.md) et [procédure Webuzo](deploiement-vps.md).
