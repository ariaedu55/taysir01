# Découverte des destinations

## Plan

Reprendre le bandeau photographique des fiches d’établissements sur l’annuaire et les douze fiches pays. Réutiliser les panoramas HD existants et compléter les destinations avec des scènes reconnaissables, déclinées en WebP portrait et paysage. Conserver le radar et les cartes d’embarquement dans l’annuaire. Dans les fiches, placer le visuel d’origine et un repère pour les études après le bandeau, avec le sommaire et les démarches en accès direct. Vérifier les adresses, les sources institutionnelles, les interactions et le rendu à trois largeurs.

## Résultat

Le nouveau bandeau est en place dans l’annuaire et dans les douze fiches pays. Il réutilise les composants des établissements, avec une navigation transparente, un voile noir uni, un titre Prata animé et une sélection automatique du cadrage portrait. Le fil d’Ariane et la légende situent le visiteur. L’ancre de découverte mène à la liste ou à la présentation.

L’annuaire ouvre sur une vue aérienne de Dakar. Plus bas, le radar montre les douze directions et distances. Le filtre par région actualise les cartes et le nombre de pays affichés. Le focus sur une carte met sa destination en évidence sur le radar. La rotation s’arrête lorsque le radar sort du champ ou que l’onglet devient inactif. La préférence de mouvement réduit laisse le radar consultable avec une mise en évidence statique.

Les fiches distinguent le lieu illustré de l’aéroport de référence. Pour le Canada, le panorama montre Calgary alors que le repère de distance utilise Montréal (YUL). La présentation associe le texte, le visuel d’origine lorsqu’il est disponible et un lien institutionnel. Le guide garde les ancres `#rubrique-N`, les accordéons, les suggestions et l’accès aux démarches du portail. Le sommaire reste disponible sur téléphone. Les guides détaillés de l’Angleterre et de la Turquie restent en attente de contenu Taysir. Ils affichent l’orientation, un lien institutionnel et les démarches du portail.

Les repères et leurs sources sont centralisés dans `config/destination-guides.php`. Au Canada, les rubriques CAQ et examen médical s’appuient sur les informations du [gouvernement du Québec](https://www.quebec.ca/education/etudier-quebec/demande-selection-temporaire) et d’[IRCC](https://www.canada.ca/fr/immigration-refugies-citoyennete/services/demande/medical-police/examens-medicaux/exigences-residents-temporaires.html). La fiche renvoie aussi au [catalogue de formations EduCanada](https://www.educanada.ca/programs-programmes/index.aspx?lang=fra). Les deux mises à jour éditoriales sont appliquées à l’affichage afin de rester en place après un nouvel import WordPress.

## Images

Sept scènes créées avec ImageGen intégré ont été exportées dans `public/assets/images/destinations/guides/`. Chaque scène a une version paysage de 1 672 × 941 px (130 à 269 Kio) et une version portrait de 753 × 941 px (66 à 110 Kio). Les bandeaux du Canada, de la France, de l’Angleterre, de la Turquie, du Maroc et de la Tunisie réutilisent leurs panoramas HD existants. Les six nouveaux panoramas pays sont réservés aux fiches et aux cartes d’embarquement. Les visuels d’origine dans la présentation sont chargés en différé.

### Consigne commune de génération

> Realistic editorial landscape travel photograph for a premium Senegalese study-abroad website, widescreen 16:9. Distinct location, credible architecture and geography, finely textured, nuanced natural color and clear atmosphere. Keep a recognisable focal point in the central 45 percent of frame so it survives a tall portrait crop, and avoid bright white in the top fourth so overlaid white navigation stays readable. Cinematic documentary photography, realistic proportions. No words, no branding, no watermarks, no collage.

### Scènes

- `annuaire` — Photorealistic oblique view from a passenger jet cabin just after takeoff from Dakar, looking down at the Atlantic shoreline of the Cap-Vert peninsula, Senegal. An aircraft wing angles into view on the right, city and curved coast sweep through the center with vivid blue ocean, gentle high haze and sunrise. The frame is mostly outside the aircraft, no window frame.
- `bruxelles` — Brussels Grand-Place, Belgium, seen from the cobbled square at blue hour. Central Gothic Town Hall tower and authentic historic gilded guildhouses in a broad composition, mellow window light, a few distant people, documentary architectural perspective.
- `lisbonne` — Lisbon, Portugal, from a miradouro in Alfama, terracotta roofs and soft Tagus horizon in late sun, layered ochre buildings and the distinctive pale dome of the National Pantheon near frame center. Subtle Atlantic air and detailed city roofs.
- `moscou` — Historic Moscow cityscape viewed across the Moskva River on a cold clear morning, with the Kremlin red-brick towers and the edge of Saint Basil's Cathedral plausibly visible, soft winter light and reflections. Emphasize historic architecture and urban geography, no flags or political symbolism.
- `riyad` — Riyadh skyline at twilight with the recognizable Kingdom Centre tower, seen well from a realistic broad city viewpoint. Warm desert air, grounded modern architecture, subtle dynamic lighting at street level, no invented buildings in the foreground.
- `shanghai` — Shanghai from the Bund, facing the genuine Pudong skyline across the Huangpu River. The Oriental Pearl Tower and surrounding skyline readable as a group, calm sunset reflections, hazy soft water detail, composition stays legible in a tight vertical crop.
- `tokyo` — Tokyo cityscape with the red and white Tokyo Tower framed among dense real-looking contemporary rooftops as seen from a high viewpoint at late blue hour, quiet warm street lights, realistic city perspective and no readable signs.

## Vérifications locales

- Les 13 adresses destinations répondent HTTP 200 sur le port 8080. Les contrôles de non-régression sur l’UCAD, l’annuaire des écoles et le formulaire d’admission passent aussi. La syntaxe PHP et JavaScript des fichiers modifiés a été vérifiée.
- L’annuaire et le Canada s’affichent sans débordement aux largeurs contrôlées. Le Canada a été testé à 1 440, 768 et 390 px. Le titre et le bandeau de l’Arabie saoudite passent à 320 px. Les panoramas portrait se chargent sur mobile.
- Le menu mobile s’ouvre et se ferme. Le filtre Asie affiche exactement la Chine et le Japon. Le focus de la carte Japon active son repère sur le radar. Au clavier, le lien de découverte du Canada ouvre la présentation et l’entrée CAQ ouvre sa rubrique et expose ses deux liens officiels.
