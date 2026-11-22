# Optimisation et compression des images

Demande du 22 novembre 2026 :
- optimiser et compresser toutes les images du projet pour un chargement plus rapide ;
- convertir en WebP celles qui ne le sont pas encore ;
- donner le poids total du projet à la fin.

## Existant (mesuré avant intervention)

| Dossier | Images | Poids | Rôle |
| --- | --- | --- | --- |
| `public/assets/images/` | 689 (677 WebP, 10 PNG, 1 JPEG, 1 SVG) | 48,8 Mo | images servies par le site |
| `storage/exports/uploads/` | 850 | 782 Mo | export brut de la médiathèque WordPress : source des imports, **jamais servie** ; contient des documents personnels de clients (passeports, visas) |
| `storage/cache/` | 5 | 0,9 Mo | captures d'un audit des médias |

Projet complet : 1,1 Go, dont 924 Mo pour `storage/exports`.

`database/import/publish-media.php` produit déjà des WebP de 1 920 px au plus, en qualité 80. Les images non WebP qui restent sont :
- les logos (`brand/*.png`) ;
- les 7 drapeaux (`destinations/*.png`) ;
- `wordpress/2023/03/canada-tourisme-….jpeg`, qui n'est plus utilisé ;
- `wordpress/2023/04/testi-500x500.png` (avis clients de l'accueil).

## Plan

1. **Sauvegarde** : copie de `public/assets/images/` hors du projet (`C:\wamp64\www\_sauvegarde-images-taysir-2026-11-22`). Le projet n'a aucun commit git, donc aucune autre copie n'existe.
2. **Script relançable** `bin/optimize-images.php` (extension GD de PHP, WebP pris en charge) :
   - **Conversion en WebP** des PNG et JPEG de `public/assets/images/`, avec la transparence conservée. L'original est supprimé une fois converti.
   - **Recompression des WebP existants** :
     - redimensionnement à 1 600 px de large au plus (les cartes et visuels de contenu ne dépassent pas cette taille) ;
     - les visuels plein écran (`destinations/hero`, `hero-flight`) gardent 1 920 px ;
     - qualité 76, transparence conservée ;
     - un fichier n'est remplacé que si le gain dépasse 10 %, pour ne pas dégrader une image sans bénéfice.
3. **Références** : mise à jour des chemins `.png` et `.jpeg` dans le code (logos, drapeaux, avis), et alignement de `publish-media.php` sur les mêmes réglages pour que les futurs imports restent optimisés.
4. **`storage/exports`** : non modifié. C'est l'archive source des imports et elle contient des documents personnels ; la compresser détruirait les originaux. Elle ne doit pas être mise en ligne.
5. **Vérification et mesures** :
   - pages principales ;
   - images toutes en 200 ;
   - rendu visuel des logos, drapeaux et visuels ;
   - poids avant et après, et poids total du projet.

## Résultat (22 novembre 2026)

### Images du site (`public/assets/images/`)

| | Avant | Après |
| --- | --- | --- |
| Poids | 48,8 Mo | 35,6 Mo (−13,2 Mo, −27 %) |
| Formats | 677 WebP, 10 PNG, 1 JPEG, 1 SVG | 688 WebP, 1 SVG, plus 9 PNG orphelins (voir plus bas) |

- **11 images converties en WebP** :
  - logos : 11 Ko → 5,6 Ko chacun, sans perte ;
  - 7 drapeaux, sans perte, un peu plus légers ;
  - `canada-tourisme` : 158 → 73 Ko ;
  - `testi-500x500` : 111 → 27 Ko.
- **488 WebP recompressés** : 1 600 px au plus (1 920 px pour les visuels plein écran), qualité 76.
- **189 WebP inchangés**, car le gain était inférieur à 10 %.
- **0 échec.**

**Références mises à jour :**
- logos dans `header.php`, `footer.php`, `mobile-menu.php` et `auth-aside.php` ;
- drapeaux dans `home.php` et `destination-cards.php` ;
- `config/home.php` (`review`) et la description de `flag` dans `config/destinations.php`.

`database/import/publish-media.php` utilise maintenant 1 600 px et la qualité 76, les mêmes réglages.

**Outil :** `bin/optimize-images.php`, relançable, avec une option `--dry-run` pour simuler.

### Vérifications

- **22 pages principales** (accueil, destinations, fiches pays, universités, métiers, blog, agence, connexion…) : toutes répondent 200.
- **Images** : 232 images distinctes, toutes en WebP, toutes en 200, et aucune ne pointe encore vers l'ancien site.
- **Rendu** : logo net, drapeaux et photos des cartes d'embarquement corrects, aucune image cassée.

### Points restants

- **9 PNG orphelins** : `brand/logo-navy.png`, `brand/logo-white.png` et les drapeaux `ca`, `fr`, `gb`, `ma`, `sn`, `tn`, `tr`. Ils ne sont plus utilisés, et leur version WebP est en place. Leurs droits Windows refusent la suppression et même la lecture des droits (fichiers créés par un autre outil). Ils pèsent 24 Ko et sont à supprimer à la main.
- **Anciennes photos inutilisées** : `destinations/toronto`, `casablanca`, `paris-nuit`, `tunis-avenue`, `dakar-renaissance`, ainsi que `horizon-dakar` et `horizon-dakar-mobile` (0,5 Mo). Elles ne sont plus référencées ; à supprimer si vous le confirmez.
- **Sauvegarde des originaux** : `C:\wamp64\www\_sauvegarde-images-taysir-2026-11-22` (51 Mo), hors du projet. Elle peut être supprimée une fois le rendu validé.

### Poids du projet

| Partie | Poids |
| --- | --- |
| **Projet complet** | **1 007,6 Mo (environ 1 Go)** |
| `storage/exports` (export WordPress brut, non servi, non modifié) | 921,7 Mo |
| `.git` | 48,2 Mo |
| `public/` (dont 35,6 Mo d'images) | 35,8 Mo |
| Code, configuration, contenus et documentation | 1,9 Mo |
| **Ce qui part en ligne** (sans `storage/exports` ni `.git`) | **37,8 Mo** |

`storage/exports` n'a pas été compressé : c'est la source des imports, et il contient des documents personnels de clients. Il n'a pas à être déployé chez LWS.
