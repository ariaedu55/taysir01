# Découpage du gabarit en partials

## Plan (20 novembre 2026)

`app/Views/layouts/main.php` était propre à l'accueil. Il incluait `home.php` en dur, définissait ses propres fonctions `$escape`, `$link` et `$media`, et utilisait des chemins `assets/…` relatifs. Objectif : un gabarit commun à toutes les pages, sans changer le rendu de l'accueil.

- `View::render($vue, $données, $gabarit)` : la vue est rendue d'abord, puis insérée dans le gabarit sous la variable `$slot`.
- `app/Views/layouts/main.php` : structure HTML commune, avec `$title`, `$description`, `$styles` et `$scripts` fournis par chaque page.
- `app/Views/partials/header.php` et `footer.php` : en-tête, navigation et pied de page. Le pied de page affiche les crédits photo seulement si la page les fournit.
- `config/site.php` : informations communes à tout le site (adresse de l'ancien site, logo, navigation, coordonnées, liens du pied de page), sorties de `config/home.php`.
- Nouveaux helpers `legacy_url()` (liens vers les pages WordPress pas encore migrées) et `media_url()` (médias WordPress copiés en local, sinon l'URL d'origine), à la place des fonctions définies dans les vues.
- `app/Views/home.php` : remplacer `$escape`, `$link` et `$media` par les helpers, et utiliser `asset()` pour les images des destinations.
- Vérification : comparer le HTML de l'accueil avant et après, puis contrôler le rendu dans le navigateur.

## Résultat

| Fichier | Changement |
| --- | --- |
| `app/Core/View.php` | `render($vue, $données, $gabarit)` : la vue est mise en mémoire tampon, puis le gabarit reçoit `$slot` et toutes les variables. |
| `app/Core/Controller.php` | `render()` utilise le gabarit `main` par défaut. Passer `null` pour une vue autonome. |
| `app/Views/layouts/main.php` | Gabarit commun. Variables : `$title` (affiché « Titre — Taysir Orientation Voyage »), `$documentTitle` (titre complet, prioritaire), `$description`, `$styles` et `$scripts` (chemins relatifs à `public/assets/`), `$photoCredits`. |
| `app/Views/partials/header.php` | Logo, menu et bouton « Mon dossier », lus dans `config/site.php`. |
| `app/Views/partials/footer.php` | Coordonnées, liens de l'agence, réseau Taysir. Les crédits photo sont affichés seulement si `$photoCredits` n'est pas vide. |
| `config/site.php` | Nouveau : `name`, `legacyUrl`, `logo`, `contact`, `navigation`, `agencyLinks`, `network`. `navigation` et `logo` ont été retirés de `config/home.php`. |
| `app/Helpers/functions.php` | Ajout de `legacy_url()`, `media_url()` et `partial($nom, $données)`. |
| `app/Views/home.php` | Les fonctions locales `$escape`, `$link` et `$media` sont remplacées par `e()`, `e(legacy_url())` et `e(media_url())`. Les images des destinations passent par `asset()`. |
| `HomeController`, `HomeContent` | Le contrôleur rend `home` dans le gabarit et fournit le titre, le CSS, le JS et les crédits. `HomeContent` ne rajoute plus l'URL du site. |

### Créer une nouvelle page

```php
// Dans un contrôleur
$this->render('pages/contact', [
    'title' => 'Contact',
    'styles' => ['css/contact.css'], // facultatif : app.css est toujours chargé
]);
```

La vue `app/Views/pages/contact.php` contient uniquement le `<main id="contenu">…</main>`. L'en-tête et le pied de page sont ajoutés par le gabarit.

### Vérifications

- `php -l` sans erreur sur tous les fichiers modifiés.
- Comparaison du HTML de l'accueil avant et après. Les seules différences sont les chemins devenus absolus (`/assets/…` au lieu de `assets/…`), le lien du logo (`/` au lieu de `./`) et l'ancre du menu (`/#temoignages` au lieu de `#temoignages`, pour fonctionner depuis toutes les pages). Le titre de l'onglet est inchangé.
- Les 66 fichiers distincts référencés par l'accueil répondent 200. La page 404 fonctionne toujours.
- Script de test avec un sous-dossier WAMP : les 78 références aux fichiers sont préfixées par `/projet%20taysir%20orientation/public/assets/`. Sans `$photoCredits`, les crédits sont masqués.
- Navigateur intégré : le diaporama, l'en-tête et le pied de page s'affichent comme avant, aucune image en erreur, 26 liens dans le pied de page.

### Points restant à traiter

- Fait, voir [css-commun.md](css-commun.md). `public/assets/css/home.css` contenait à la fois les styles communs (en-tête, pied de page, boutons) et ceux de l'accueil. Le séparer en `app.css` (commun) et `home.css` lors de la création de la première autre page.
- Les liens du menu et du pied de page pointent encore vers l'ancien site WordPress via `legacy_url()`. Les remplacer par `url()` au fur et à mesure que les pages sont migrées.
