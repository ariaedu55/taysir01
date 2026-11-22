# Nouvelles images du bandeau reprises dans tout le site

Demande du 21 novembre 2026 : les images du bandeau d'accueil ont été changées (voir [accueil-panoramas.md](accueil-panoramas.md)). Il faut remplacer les anciennes images partout où elles apparaissent encore et vérifier que tout est complet.

## Existant

Le bandeau utilise sept illustrations HD dans `public/assets/images/destinations/hero/`, chacune en deux versions :
- `<ville>.webp`, panorama 1672 × 941 ;
- `<ville>-portrait.webp`, 706 × 941.

Les villes sont Calgary, Paris, Istanbul, Londres, Marrakech, Tunis et Dakar.

Les anciennes photos (`destinations/toronto`, `paris`, `istanbul`, `londres`, `casablanca`, `tunis`, `dakar`) restent utilisées à plusieurs endroits :

| Endroit | Fichier | Ancienne image |
| --- | --- | --- |
| Carrousel des destinations de l'accueil | `home.php`, via `Destination::imageUrl` | 6 pays |
| Cartes de `/destination/` et « autres destinations » des fiches pays | `partials/destination-cards.php` | 6 pays |
| Grand visuel en haut des fiches pays | `pages/destination.php` | 6 pays |
| Panneau photo de connexion | `auth/login.php` | Toronto |
| Panneaux photo d'inscription et de mot de passe oublié ou réinitialisé | `auth/register.php`, `auth/forgot.php`, `auth/reset.php` | Paris |
| Photo de la carte « Voyage et démarches » de l'accueil | `home.php` | Paris |
| Crédits photo du pied de page | `config/home.php` `photoCredits` | photographes des anciennes photos |

Les autres pays (Belgique, Portugal, Russie, Arabie saoudite, Chine, Japon) n'avaient pas d'image dans le bandeau : ils ne changent pas.

## Plan

1. **`config/destinations.php`** : Canada, France, Angleterre, Turquie, Maroc et Tunisie pointent vers `hero/<ville>`. La ville du Canada devient Calgary, celle du Maroc Marrakech.
2. **`Destination::imageUrl($country, $portrait)`** : version portrait quand elle existe, pour les formats hauts (carrousel de l'accueil). Les panoramas restent pour les cartes larges et le grand visuel des fiches.
3. **Pages d'authentification** : `hero/calgary-portrait` (connexion) et `hero/paris-portrait` (inscription, mot de passe), avec les légendes à jour.
4. **Carte « Voyage et démarches »** : `hero/paris`.
5. **Crédits** :
   - retirer les photographes des anciennes photos ;
   - indiquer à la place que les sept visuels sont des illustrations photoréalistes générées par IA ;
   - adapter le pied de page aux crédits sans lien.
6. **Vérification** :
   - aucune page ne charge plus une ancienne image ;
   - toutes les images chargées répondent 200 ;
   - vérification visuelle des cartes, des fiches et de la connexion.

Les anciens fichiers ne sont pas supprimés (décision à prendre par l'utilisateur).

## Résultat (21 novembre 2026)

### Remplacements

| Endroit | Nouvelle image |
| --- | --- |
| Carrousel des destinations (accueil) | `hero/<ville>-portrait.webp` (format haut 3:4), via `Destination::imageUrl($country, true)` |
| Cartes de `/destination/` et « autres destinations » des fiches | `hero/<ville>.webp`, cadré sur le sujet |
| Grand visuel des fiches Canada, France, Angleterre, Turquie, Maroc, Tunisie | `hero/<ville>.webp`, cadré sur le sujet |
| Connexion | `hero/calgary-portrait`, légende « Calgary · Canada » |
| Inscription, mot de passe oublié, réinitialisation | `hero/paris-portrait` |
| Carte « Voyage et démarches » de l'accueil | `hero/paris`, cadrée sur la tour Eiffel |

Changements de code :
- `config/destinations.php` :
  - nouvelles clés `photo` (`hero/…`) ;
  - villes Calgary et Marrakech ;
  - nouvelle clé `focus`, la position horizontale du sujet, comme le bandeau : 73 % pour Calgary, Istanbul et Londres ; 23 % pour Paris, Marrakech et Tunis. Sans ce réglage, la carte France ne montrait que le ciel.
- `Destination::imageUrl()` : paramètre `$portrait`.
- `destination-cards.php` et `destination.php` : appliquent `focus` en `object-position`.

### Crédits

Les sept photographes des anciennes photos (Pexels, Wikimedia) ne sont plus cités, car leurs photos ne sont plus affichées. Le pied de page indique désormais « Calgary · illustration générée par IA » et ainsi de suite, avec la mention « Visuels des villes : illustrations photoréalistes générées par IA ». Le pied de page accepte maintenant des crédits sans lien.

### Vérifications

- **Anciennes images** : aucune n'est chargée sur 16 pages, analysées depuis leur code HTML :
  - accueil, destinations ;
  - les six fiches concernées, ainsi que Belgique et Japon ;
  - connexion, inscription, mot de passe oublié ;
  - universités, à propos, contact.
- **Chargement** : les 95 images distinctes de ces pages répondent 200.
- **Rendu** : vérifié dans le navigateur pour les cartes `/destination/`, la fiche France, la connexion et le carrousel de l'accueil, qui charge bien les versions portrait de 706 px.

### Fichiers devenus inutiles (non supprimés)

Ces fichiers ne sont plus utilisés nulle part ; ils peuvent être supprimés si vous le confirmez :
- `destinations/` : `toronto`, `paris`, `istanbul`, `londres`, `casablanca`, `tunis`, `dakar` ;
- `destinations/` : les versions intermédiaires `calgary`, `marrakech`, `paris-nuit`, `tunis-avenue`, `dakar-renaissance` ;
- `images/horizon-dakar.webp` et `horizon-dakar-mobile.webp`.

Les pays sans visuel dans le bandeau (Belgique, Portugal, Russie, Arabie saoudite, Chine, Japon) gardent leur photo.
