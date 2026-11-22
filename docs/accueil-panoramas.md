# Panoramas du carrousel d’accueil

## Plan

Créer sept illustrations photoréalistes inspirées de lieux réels, puis les intégrer aux diapositives en WebP. Vérifier la lisibilité du titre blanc, le cadrage sur écran étroit et le chargement local. Mettre à jour les légendes et les crédits associés au bandeau.

## Remplacement de la photo France

Le dernier ajustement porte sur la diapositive France. La photo fournie par le client montre la tour Eiffel et le Sacré-Cœur de nuit. Elle a été convertie en WebP à sa taille d’origine (638 × 446 px, 56 Ko) et ajoutée sous `public/assets/images/destinations/paris-nuit.webp`. La configuration utilise ce fichier uniquement dans le bandeau. Le fichier `paris.webp` et son crédit restent utilisés pour les autres affichages.

Le cadrage donne la priorité à la tour Eiffel sur un écran étroit. Vérification sur l’aperçu local à 1 440 × 800 px et contrôle du chargement à 530 px de large. Le carrousel affiche sept diapositives et la sélection France charge le nouveau fichier avec sa légende.

## Remplacement de la photo Canada

Convertir la vue fournie en WebP, l’associer à la première diapositive et adapter la légende et le cadrage au panorama.

La première diapositive affiche désormais `public/assets/images/destinations/calgary.webp` (1 080 × 810 px, 216 Ko) avec la légende « Calgary au coucher du soleil ». Le cadrage privilégie les tours et la rivière. Rendu vérifié sur l’aperçu local à 1 440 × 800 px et chargement contrôlé à 530 px de large. Le fichier de Toronto reste utilisé sur les fiches de destination.

## Remplacement de la photo Maroc

Convertir la photo fournie de Marrakech en WebP pour le bandeau, adapter la légende et vérifier le cadrage du minaret.

La diapositive Maroc utilise désormais `public/assets/images/destinations/marrakech.webp` (1 920 × 1 282 px, 300 Ko), avec la légende « La Koutoubia au crépuscule ». Le cadrage maintient le minaret dans la zone visible. Rendu vérifié sur l’aperçu local à 1 440 × 800 px et chargement contrôlé à 530 px de large. La vue de Casablanca reste disponible pour les pages de destination.

## Remplacement de la photo Tunisie

Intégrer la vue fournie de Tunis au bandeau en WebP et cadrer le premier plan sur la cathédrale.

La diapositive Tunisie charge `public/assets/images/destinations/tunis-avenue.webp` (512 × 311 px, 56 Ko), avec la légende « La cathédrale et l’avenue Habib-Bourguiba à Tunis ». Le cadrage favorise la cathédrale sur écran étroit. Rendu vérifié à 1 440 × 800 px et chargement contrôlé à 530 px de large. La photo `tunis.webp` reste associée à la fiche de destination.

## Remplacement de la photo Sénégal

Convertir la photo du Monument de la Renaissance africaine en WebP, l’associer à la diapositive Sénégal et ajuster le cadrage autour des figures.

La diapositive Sénégal utilise `public/assets/images/destinations/dakar-renaissance.webp` (800 × 628 px, 58 Ko), avec la légende « Le Monument de la Renaissance africaine à Dakar ». Le cadrage favorise le haut du monument. Rendu vérifié à 1 440 × 800 px et chargement contrôlé à 530 px de large. Le lien de la diapositive mène à la fiche de l’UCAD.

## Régénération HD des sept panoramas

Recréer chaque scène en haute définition avec les lieux et ambiances déjà choisis. Exporter des WebP adaptés au bandeau, avec une version plus légère pour les écrans étroits. Vérifier le rendu, le chargement et la correspondance entre les visuels et les légendes.

La génération a produit sept illustrations photoréalistes, exportées en WebP dans `public/assets/images/destinations/hero/`. Les versions panoramiques mesurent 1 672 × 941 px, au format proche de 16:9. Les versions portrait mesurent 706 × 941 px et sont recadrées individuellement sur le sujet principal. Le navigateur charge le portrait lorsque le rapport largeur/hauteur est inférieur ou égal à 4:5. Les sept portraits totalisent environ 872 Ko, contre 2,2 Mo pour les sept panoramas. La première image a la priorité de chargement. Le zoom de transition a été réduit pour préserver les détails et le cadrage.

Les scènes montrent Calgary et la rivière Bow, la tour Eiffel et le Sacré-Cœur, Istanbul et le Bosphore, Westminster et la Tamise, la Koutoubia à Marrakech, l’avenue Habib-Bourguiba à Tunis et le Monument de la Renaissance africaine à Dakar. À Dakar, la nouvelle composition montre le monument et son escalier éclairé en plan large.

Les quatorze WebP ont été chargés et décodés dans le navigateur sur l’aperçu local, à 1 440 × 810 px et à 390 × 844 px. La diapositive France a été contrôlée visuellement dans les deux formats.

### Prompts utilisés avec ImageGen

Consigne commune aux six premières scènes.

> Use case: photorealistic-natural. Generate a single high-resolution wide landscape travel photograph for a website hero. Native output target 2560x1440, preferably at least 1920x1080, composition 16:9. Editorial destination photography, clean crisp architectural detail from edge to edge, realistic proportions and geography, natural lighting and believable textures, no HDR halos, no artificial blur, no smudging. Leave calm negative space around center for white HTML headline and safe headroom for a cropped hero. Main subject within the middle 65% to survive tight cropping. Image only, no typography, labels, watermarks, collages or graphic overlays.

- **calgary** — Canada, Calgary from above the Bow River, late golden hour: the Bow skyscraper with its distinctive diagonal steel exoskeleton is prominent, with the real downtown buildings set across the river, an arched bridge, riverside parks, and autumn trees. Balanced wide frame, towers around 60% across.
- **paris** — France, Paris at blue hour. The Eiffel Tower, in authentic shape, large and illuminated on the left third, and the white domes of the Basilica of Sacré-Cœur on Montmartre illuminated toward the right third, above warm dense Parisian rooftops. A plausible compressed telephoto view evoking the supplied panoramic composition, deep blue sky. Allow headroom above tower tip and domes.
- **istanbul** — Türkiye, Istanbul from across the Bosphorus, the historic skyline and Blue Mosque's distinctive cascade of Ottoman domes and slender minarets, a small passenger ferry and a sharp shoreline. Golden early light, historic peninsula, keep mosque prominent toward right center.
- **londres** — United Kingdom, London across the Thames from the South Bank near Westminster Bridge. Authentic clear silhouette of Elizabeth Tower and its visible clock face and Palace of Westminster on the correct bank, river reflections and soft afternoon light. Tower and palace large, well inside frame.
- **marrakech** — Morocco, Marrakech: the iconic Koutoubia Mosque minaret in warm rosy sandstone, its square silhouette and characteristic top finial fully inside the frame with air above, surrounded by real Marrakech palm trees and earthen walls. Clear blue to peach twilight sky, richly detailed foreground, tower just left of center.
- **tunis** — Tunisia, elevated view down the recognizable Avenue Habib Bourguiba in Tunis. Cathedral of St Vincent de Paul with its characteristic two cream towers and central arched entrance on the left, wide tree-lined Tunis avenue stretching into the distance to the right, urban facades and clear blue daytime sky. Give the cathedral prominence around 32 percent from left and keep both towers in frame.

Consigne finale pour Dakar.

> Create a sharp 16:9 high-definition travel editorial landscape of Dakar, Senegal at blue hour. A distant, small and recognizable silhouette of the Monument de la Renaissance africaine crowns the hill, seen from the bottom of its long staircase as an architectural landmark. Depict the sculpture solely as a single dark bronze silhouette outlined by warm backlighting against a deep indigo sky. The main visual content is a broad, richly detailed illuminated stone plinth and stairway, symmetrical architectural lines, golden lamps leading the eye uphill, subdued Dakar city lights at the edges, deep shadow and clear atmospheric perspective. Keep the entire silhouette well below the top edge, around 60% horizontally. No people on stairs, no human detail on sculpture, no typography, no watermark. Single landscape image, as large as available, natural-looking travel photography.
