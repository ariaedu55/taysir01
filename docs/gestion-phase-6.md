# Phase 6 — Prospection scolaire

## Plan local

- Relier chaque prospect à une fiche personne existant dans le référentiel commun. Tenir le suivi commercial dans le module de prospection et transmettre la même fiche à l’accueil.
- Gérer les établissements et campagnes par agence. L’administrateur a la vue d’ensemble. Un agent de prospection voit ses prospects, et une commerciale voit ceux qui lui sont attribués. Une attribution par lot se fait dans la même agence.
- Importer des classeurs Excel `.xlsx` ou des CSV exportés depuis Excel. Montrer les écarts et les doublons avant confirmation, puis recalculer les doublons lors de l’écriture. Les contacts déjà présents sont signalés et ignorés pour un rapprochement explicite.
- Enregistrer le résultat d’un appel, la date de relance et une note de suivi interne. Une proposition de rendez-vous entre dans la file de confirmation de l’accueil. Les propositions attribuées sont consultables dans l’agenda de la commerciale.
- Tester en recette isolée l’import, le rejeu, les droits, l’attribution, la conversion et le passage à l’accueil. Documenter les résultats avant de poursuivre.

## Livré le 28 novembre 2026

- **Prospects** (`/gestion/prospects/`) regroupe la saisie, la recherche, les filtres par établissement, campagne, niveau et état, ainsi que l’attribution par lot. La prospection et la commerciale retrouvent leurs compteurs dans « Ma journée » et leurs résultats dans la recherche globale.
- **Établissements et campagnes** présentent les catalogues par agence et les volumes de collecte du périmètre courant. Une campagne peut avoir une date de fin. La commerciale peut consulter les catalogues et saisir ses propres prospects. Le lot est attribué intégralement à une commerciale active de la même agence ou intégralement refusé.
- L’import accepte la première feuille `.xlsx` ou un CSV UTF-8, à raison de 500 lignes et 2 Mo par fichier. Le préchargement rapporte les numéros de ligne des doublons et des saisies invalides. Le fichier original n’est pas conservé. L’aperçu expire après vingt minutes. L’historique des imports conserve les compteurs dans le journal d’audit. À la confirmation, les correspondances sont revérifiées par e-mail ou par prénom, nom et téléphone normalisé.
- Une relance se programme depuis la fiche et apparaît à échéance dans la liste de la commerciale. Le suivi interne reste dans la prospection. Une proposition rejoint l’agenda d’accueil. La confirmation et le report s’effectuent dans le module de rendez-vous existant. La commerciale consulte les rendez-vous des prospects qui lui sont attribués.
- La conversion de cette phase correspond à la transmission à l’accueil. La fiche personne, l’historique et le rendez-vous sont conservés. Le compte client et l’ouverture du dossier suivent leurs parcours habituels.

## Environnement local

La migration additive `022_prospection.sql` est appliquée sur WAMP. Sa relance a confirmé que la base était à jour. Le retour arrière SQL s’arrête si les catalogues contiennent des données. Le fichier `bin/serveur-local.bat` charge l’extension PHP `zip`. Le format XLSX requiert `zip` et `SimpleXML` lors d’une future mise en service. Le CSV UTF-8 reste utilisable dans un environnement sans `zip`.

## Recette

**166 contrôles réussis sur MySQL et 166 sur MariaDB**, dont 12 pour la prospection. La recette HTTP utilise des données fictives et couvre notamment le rapport CSV/XLSX, le rejeu, l’isolement entre commerciales, le refus d’attribution entre agences et le rendez-vous jusqu’à la confirmation puis au report. Les fichiers PHP et le script de gestion passent les contrôles de syntaxe. Le serveur local répond sur `127.0.0.1:8080`. Le contrôle visuel sur téléphone et tablette reste à faire.
