# Découverte des universités et écoles supérieures

## Plan

Recenser les fiches et les annuaires. Vérifier les repères de chaque établissement auprès de sources institutionnelles lorsque disponibles. Créer des scènes éditoriales adaptées aux campus publics et aux domaines des écoles supérieures. Mettre en place un bandeau commun, une présentation avec les images d’origine et une navigation par rubriques. Contrôler les liens, le clavier et le rendu sur plusieurs largeurs.

## Résultat

- Huit fiches d’universités publiques, vingt fiches d’écoles supérieures et les deux annuaires ont reçu le nouveau bandeau photographique. Huit scènes sont propres à une université. Les écoles se partagent cinq scènes par domaine, gestion, apprentissage pratique, technologie, santé et énergie. Les annuaires reprennent respectivement les scènes UCAD et apprentissage pratique.
- Le bandeau laisse apparaître l’image sous la navigation transparente. Il combine un voile noir uni, un titre Prata animé, une accroche Poppins, le fil d’Ariane et une ancre vers la suite. L’apparition des textes et le mouvement léger de la photo s’appuient sur les animations existantes et respectent la préférence de mouvement réduit.
- Sous le bandeau, la présentation et le visuel d’origine occupent deux colonnes sur grand écran et se superposent sur les écrans étroits. Les rubriques utilisent des accordéons natifs. Les ancres historiques, les galeries disponibles et les établissements à découvrir restent accessibles. Les liens d’accompagnement mènent aux demandes d’admission et aux rendez-vous du portail.
- Les repères éditoriaux et leurs liens sont centralisés dans `config/institutions.php`. Les huit universités et douze écoles ont un lien vers une source directe, ministérielle ou d’évaluation publique. Les repères de huit écoles s’appuient sur les fiches Taysir importées. La fiche de l’INPG affiche son nom officiel tout en conservant l’adresse héritée. Le site propose notamment les [facultés de l’UCAD](https://ucad.sn/index.php/listefacultes), les [Espaces numériques ouverts de l’UN-CHK](https://www.unchk.sn/eno-de-lunchk/presentation-de-leno/) et les [formations du CEFER](https://ceferdakar.org/formations/). L’[annuaire du MESRI](https://espaceuniversitaire.mesrisenegal.sn/public/etablissements) complète le recensement.

## Images

Les 13 scènes générées sont livrées dans `public/assets/images/institutions/`. Chaque image existe en WebP paysage de 1 672 × 941 px, de 128 à 402 Kio, et en portrait de 753 × 941 px, de 53 à 160 Kio. Le navigateur sélectionne le portrait sur les fenêtres au format étroit. Chaque bandeau porte la mention « Image d’illustration ». Le visuel d’origine de chaque fiche est chargé plus bas, en différé.

### Consigne commune de génération

> Single photorealistic landscape 16:9 editorial photograph for a Senegalese higher-education discovery page, intended as a wide website banner. Contemporary young adult Black Senegalese students, candid, plausible equipment and wardrobe, balanced warm or neutral colors, fine texture, premium reportage photography. Keep the students' faces and defining scene details in the central 45 percent of the width and upper 55 percent of the frame to work on a portrait screen. Use softly shaded backgrounds and frame subjects mid-distance with headroom. No readable text, logos, watermark, or claimed likeness of a real school building. Distinct believable image, no collage or web design.

### Scènes

- `ucad` — Four Senegalese young adult students walking toward the viewer together in an airy Dakar university courtyard with mature shade trees, low stone and concrete buildings, books and backpacks. Genuine joyful interaction. Natural warm evening light, prominent group, reference the feeling of a classic campus portrait; central students toward the middle, darker outer framing, no staged stock-photo smiles.
- `unchk` — Two young adult students with headsets at a modern Senegalese digital learning center, reviewing a video course and typing notes in a shared workspace. Light from tall side windows, muted workstations and other students behind them, screen content unreadable.
- `uam` — Small group of adult engineering students in a bright airy technical studio in Diamniadio, building a small electromechanical prototype at a workbench with modern equipment, hands and faces visible. Crisp concrete and brushed metal, daylight. Horizontal open perspective.
- `uidt` — Senegalese civil engineering students in a technical classroom in Thiès, taking measurements and comparing a scale structure model with a lecturer. Open windows, warm clay stone and palms outside, documentary viewpoint with the model and group centered.
- `ugb` — Students sharing books under a broad shade tree on an expansive West African campus on the edge of Saint-Louis, dry northern grass and sandy footpaths, simple shaded campus buildings far behind, wind in the branches, documentary portrait with students central.
- `uadb` — Young Senegalese applied-sciences students inspecting a computer-linked laboratory instrument in a modest daylight lab in Bambey, two adults working together, distinctive warm ochre tones and glimpses of the campus courtyard through windows.
- `ussein` — Adult agronomy students working in a teaching plot in Senegal's Sine Saloum region, examining healthy crops and taking notes together, light work clothes, red-brown soil, realistic agricultural landscape, filtered morning sun, students centered.
- `uasz` — Young adult university students walking outside a university teaching block in Casamance, Ziguinchor, lush green campus after rain with palms and bright tropical foliage, thoughtful composition of three students sharing a notebook, realistic low-rise shaded architecture.
- `gestion` — Senegalese business and management students around a table in Dakar developing a project, focused discussion, analysts with unmarked documents, laptops and simple practical workspace, late-afternoon side light, deep muted neutral colors.
- `pratique` — Adult Senegalese students in a practice-focused higher-education class discussing their project with a mentor in a modern Dakar teaching studio. Several disciplines in a generic collaborative workshop, natural scene, concrete textures, warm sun.
- `technologie` — An adult Senegalese software and technology class collaborating on a prototype, centered small group, laptops with no readable text, cables and a small electronics board, subtle green studio lights and real daylight.
- `sante` — Adult Senegalese healthcare students practicing in a modern skills lab around a clearly artificial training manikin with instructor, neat light neutral scrubs, warm humane educational atmosphere, no patient distress or exposed body.
- `energie` — Senegalese vocational energy engineering students in clean work attire and hard hats on a training site, discussing a technical reading beside solar panels and a compact demonstrator for industrial instrumentation, dry ground and warm neutral daylight.

## Vérifications locales

- Syntaxe PHP vérifiée sur les fichiers modifiés. Les 30 pages ont renvoyé un statut HTTP 200 sur le port 8080. Les 28 fiches intègrent chacune leur visuel d’origine.
- Dans le navigateur, vérification sur ordinateur à 1 440 px, sur tablette à 768 px et sur téléphone à 390 px. Sur les pages contrôlées, les titres et les colonnes s’adaptent sans débordement horizontal. Le cadrage portrait et le visuel d’origine se chargent correctement.
- Sur l’UCAD, le lien de découverte et le sommaire fonctionnent au clavier. L’ancre « Admissions » ouvre la rubrique correspondante. La recherche des écoles affiche un résultat pour « UNIPRO » et un état vide pour une recherche sans correspondance.
