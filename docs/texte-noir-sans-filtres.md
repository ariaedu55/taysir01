# Texte noir et retrait des filtres par pastilles

Demande du 21 novembre 2026 :
1. Sur `/destination/` et sur toutes les pages qui ont cette rangée, supprimer les pastilles de filtre (« Toutes 12 · Amérique du Nord 1 · Europe 6 … »), jugées peu pratiques en matière de design.
2. Dans tout le site, retirer les textes en bleu et les mettre en noir.

## Existant

- **Pastilles `.region-filter`** :
  - `pages/destinations.php` (régions, script `destinations.js`) ;
  - `pages/blog.php` (rubriques) ;
  - `pages/careers.php` (secteurs).

  Sur le blog et les métiers, elles sont à côté d'un champ de recherche (`content.js`). Les filtres du back-office (`.filters`) sont un autre composant, utile au travail des conseillers : non concernés.
- **Couleur du texte** : `--ink: #041b51` (bleu nuit), utilisé partout pour le texte, les titres, les bordures au survol et les états actifs. `--muted: #516078` (gris bleuté), `--ink-soft: #0b2a6f`. Teintes transparentes `#041b51xx` pour les fonds légers et les ombres. Couleurs en dur dans la page d'erreur et le gabarit des e-mails.

## Plan

1. **Filtres** :
   - retirer la rangée de pastilles des trois pages ;
   - côté destinations : supprimer `destinations.js`, le passage de `regions` à la vue et les styles `.region-filter` ;
   - côté blog et métiers : garder la recherche, simplifier `content.js` (plus de boutons) et retirer les variables devenues inutiles.
2. **Texte noir** :
   - `--ink` passe au noir (`#0a0a0a`), `--ink-soft` à `#2b2b2b` et `--muted` à un gris neutre (`#5f5f5f`) ;
   - les teintes `#041b51xx` deviennent `#000000xx` ;
   - mêmes valeurs dans la page d'erreur et les e-mails ;
   - les boutons rouges restent inchangés. Les états actifs qui avaient un fond `--ink` (pastille de secteur courante, onglet du back-office) deviennent noirs : il n'y a plus de fond bleu.
3. **Vérification** : sur les pages principales, recherche dans le navigateur de tout texte dont la couleur calculée est bleue ; feuilles CSS lues en entier ; console.

## Résultat (21 novembre 2026)

**Filtres retirés :**
- Les pastilles ont disparu de `/destination/`, `/blog/` et `/metiers/`. Sur le blog et les métiers, le champ de recherche reste : avec « ingenieur », la page métiers affiche 7 résultats.
- Destinations :
  - `destinations.js` est supprimé ;
  - `DestinationController` ne passe plus `regions` ni le script ;
  - le statut de filtre de la vue est retiré.
- `destination.css` : 5 règles `.region-filter` en moins.
- `content.js` ne gère plus que la recherche.
- `careers.php` : la variable `$counts` est retirée.
- `Destination::byRegion()` reste dans le modèle, sans appel.

**Texte en noir :**

| Variable ou couleur | Avant | Après |
| --- | --- | --- |
| `--ink` | `#041b51` | `#0a0a0a` |
| `--ink-soft` | `#0b2a6f` | `#2b2b2b` |
| `--muted` | `#516078` (gris bleuté) | `#5f5f5f` (gris neutre) |
| Teintes `#041b51xx` (toutes les feuilles) | navy transparent | `#000000xx` |
| Texte des articles (`.prose`) | `#24324f` | `#1f1f1f` |
| Texte indicatif des champs | `#8d97aa` | `#8a8a8a` |
| Couleur du navigateur mobile (`theme-color`) | `#041b51` | `#ffffff` |

Les e-mails (`emails/layout.php`) et la page d'erreur prennent les mêmes valeurs.

Restent volontairement bleus :
- le logo (image de marque) ;
- l'anneau de focus clavier (`#2b6bff`), repère d'accessibilité ;
- les pastilles de statut « bleu » du suivi des demandes, qui sont des codes couleur et non du texte.

**Vérifications :**
- **Texte bleu** : dans le navigateur, aucun élément de texte n'a une couleur calculée bleue sur 19 pages :
  - accueil, destinations, fiche Canada, métiers, secteur santé, fiche médecin ;
  - blog, universités, après le bac, formations professionnelles ;
  - à propos, témoignages, contact, FAQ, règles, confidentialité ;
  - rendez-vous, connexion, inscription.
- **Feuilles CSS** : toutes lues en entier (app 166, home 337, destination 96, content 102, auth 79, portal 168, agency 80 règles).
- **Console et réseau** : aucune erreur. Les seules 404 de la console venaient de trois adresses de test erronées (`/a-propos/`, `/canada/`, `/ecoles/`) ; toutes les ressources des pages répondent 200.
