# Navigation mobile et tablette

## Plan

Conserver les liens du registre de navigation. Concevoir un panneau blanc inspiré des pages de découverte, avec un accès illustré aux destinations, des rubriques reconnaissables et des actions de compte et de rendez-vous accessibles pendant le défilement. Utiliser un dialogue natif et des accordéons pour le clavier, le toucher et la lecture d’écran. Vérifier les petites largeurs, la tablette, la fermeture, le retour du focus et la navigation sur ordinateur.

## Résultat

- Le bouton « Menu » ouvre un panneau pleine largeur sur téléphone. À partir de 42 rem, le panneau se place à droite et présente le visuel des destinations et les rubriques côte à côte. Le menu horizontal prend le relais au-delà de 78 rem.
- L’accès aux destinations réutilise le WebP portrait de l’annuaire, chargé en différé. Chaque rubrique porte un pictogramme blanc sur fond bleu marine `#041B51` et une description de ses liens. La page active est signalée et son accordéon se déplie à l’ouverture lorsque la page appartient à une rubrique.
- Le haut du panneau conserve le bouton de fermeture. Les liens de rendez-vous et de compte restent accessibles dans le pied du panneau pendant le défilement. Le contact, le téléphone et WhatsApp figurent dans la partie défilante.
- La vue `app/Views/partials/mobile-menu.php` lit les mêmes liens que le menu horizontal. Le dialogue natif isole le panneau et gère Échap. `app.js` maintient la tabulation dans le panneau, restitue le focus à la fermeture et ferme le dialogue au passage sur grand écran. Les transitions du panneau et des rubriques suivent la préférence de mouvement réduit.

## Vérifications

- Rendu contrôlé à 320 et 390 px sur téléphone, à 768 et 1 024 px sur tablette, et en paysage à 844 × 390 px. Les commandes restent visibles et le contenu des rubriques défile dans le panneau. À 1 440 px, le menu horizontal reprend le focus après le changement de largeur.
- Ouverture avec Entrée, ouverture des rubriques avec Espace ou Entrée, passage au lien suivant et boucle avec Tab et Maj+Tab. Fermeture avec le bouton et avec Échap, puis retour du focus sur « Menu ».
- Depuis l’UCAD, la rubrique « Après le bac » s’affiche ouverte et le lien UCAD est indiqué comme page active. Le lien illustré ouvre l’annuaire et ferme le menu. Les fichiers PHP et JavaScript ont passé le contrôle de syntaxe.
