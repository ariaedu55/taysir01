# Contrôle du lot Codex : reprise de la 2A et étape 2B (2026-11-27)

Lot contrôlé : modifications non commitées après `ceeabd3`. Elles comprennent les corrections des réserves de la 2A, puis la 2B : agenda, migrations 012 et 013, créneaux publics, report, annulation avec ou sans compte, durées réglables. Les fichiers de l’assistant sont hors lot.

## Verdict

**CONFORME, avec une correction d’outil apportée pendant le contrôle.**

## Contrôles relancés

| Contrôle | Résultat |
| --- | --- |
| `php -l` sur tous les fichiers PHP modifiés ou nouveaux | aucune erreur |
| Migrations 012 et 013 sur la base de développement (MySQL 8.4) | appliquées |
| `bin/recette.php` (MySQL 8.4) | **120 sur 120** |
| `bin/recette-agenda.php` (MySQL 8.4) | **8 scénarios sur 8** : concurrence à début identique et à débuts différents, créneaux contigus, replanification, report lié et conflit, disponibilité publique et absence, annulation sans compte (jeton invalide et rejeu refusés), durée modifiée |
| `bin/repetition-011.php` | **37 sur 37** après correction (voir plus bas) |
| `bin/repetition-migrations.php` (1B) | **22 sur 22** |
| Qualification MariaDB 10.11.15 faite par Codex (instance isolée, port 33317) | robot **120/120** ; agenda **8/8** ; 012 et 013 : aller, retours arrière identiques en schéma, données et journal, interruption puis reprise équivalente. Journaux dans `storage/recette/mariadb-10.11.15-isolee/` |
| Lot testé sous MariaDB comparé au code actuel | identique, à l’exception de la correction d’outil ci-dessous |

## Relecture

- **Annulation sans compte**
  - Le jeton est aléatoire (32 octets) et seule son empreinte SHA-256 est stockée.
  - Le format est contrôlé, le lien expire et il est consommé à l’usage.
  - La recherche du rendez-vous se fait sous verrou et ne vise que les rendez-vous sans compte.
  - Ouvrir le lien ne fait qu’afficher un formulaire : seule sa confirmation annule.
- **Réservation**
  - Le créneau est recalculé sous verrou du responsable, avec contrôle des intervalles qui se chevauchent.
  - Un index unique empêche deux rendez-vous actifs au même début pour un même responsable.
  - Un jeton de formulaire empêche la double soumission.
- **Interrupteur** : `AGENDA_RESERVATION_DIRECTE=0` par défaut ; la lecture n’accepte que `1`.
- **Charte** : enregistrer en vert, supprimer en rouge ; aucune bordure colorée ni dégradé ajouté.
- **012** : la colonne calculée et les contraintes CHECK s’écartent du principe « pas de colonne générée ni de CHECK » du plan 2A. Elles fonctionnent sous MySQL 8.4 et sous MariaDB 10.11.15, qualification à l’appui : écart accepté. Le retour arrière fusionne les nouveaux statuts (`honore`/`absent` → `termine`, `reporte` → `annule`), comme prévu par C7.
- **013** : sans `IF NOT EXISTS` ; une reprise passe par `--reprendre`, comme les autres migrations.
- **Secrets** : aucun dans le lot. L’instance MariaDB et ses journaux sont ignorés par git (`/storage/**/*`).

## Correction apportée

`bin/repetition-011.php` appliquait aussi 012 et 013, puis ne retirait que 011 : le schéma ne pouvait plus être identique (2 échecs sur 37). Les appels sont limités à `--jusqua=011`, comme pour l’outil de la 1B : **37 sur 37**.

## Reste

- Décision de clôture de la 2B par le `validateur`.
- Le script de répétition MariaDB de Codex (`storage/recette/mariadb-10.11.15-isolee/repetition.php`) n’est pas versionné. Le placer dans `bin/` le rendrait rejouable.
- Mise en ligne : seulement après la validation de la 1B en conditions réelles et ton push. La réservation directe reste désactivée.
