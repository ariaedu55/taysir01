# Offres et activités

Menus « Offres » et « Activités » : 8 pages encore servies par l'ancien site. Voir [plan-migration.md](plan-migration.md) et [vision-plateforme.md](vision-plateforme.md).

## Constat sur WordPress (20 novembre 2026)

| Page | Contenu WordPress |
| --- | --- |
| `/offre_stage/` | Liste des articles « offre_stage » (5), puis « Offres de formation » (5 articles) |
| `/offre-demploie/` | Liste des articles « offre_emploie » (5), puis « Offres de formation » |
| `/avis-de-recruteurs/` | **Page vide** |
| `/avis-de-professionnels/` | **Page vide** |
| `/parlons-de-voyage/` | **Page vide** |
| `/salon-collegien/` | Liste des articles « salon-collegien » (3 forums dans des collèges et lycées, mars 2024) |
| `/salons-internationaux/` | Un paragraphe, une liste de 5 vidéos (toutes la même : « Panel L'immigration au Québec », chaîne YouTube tierce), une grille d'articles « challenge » (catégorie vide) |
| `/taysir-club-challenge/` | Un paragraphe, « nos lauréats » : 3 fois la même vidéo tierce (« Avis aux étudiants », Ecobank Sénégal), sans lien avec le club ; grille « challenge » vide |

Les articles (offres, formations, bourses, salons) sont déjà en base, archivés avec la mention de leur date ([metiers-orientation.md](metiers-orientation.md)). Toutes les offres datent de juin 2024.

Images : `salon-international.jpeg` est l'affiche d'un salon d'un autre organisateur (novembre 2021) et `ifaaa.jpg` une remise de diplômes d'école. Elles ne sont pas reprises pour ne pas les présenter comme des activités Taysir.

## Choix

- Une seule description des 8 pages dans `config/activities.php` (titre, accroche, texte, sections d'articles, vidéo, actions), un contrôleur et un gabarit communs, comme pour les services.
- Textes WordPress repris par un script d'import (`database/import/wordpress-activities.php` vers `database/content/activities/`).
- **Offres** : les offres de 2024 restent consultables, sous un avertissement clair ; chaque page mène au portail (compte, rendez-vous) et propose les formations et bourses.
- **Pages vides** : pas de faux avis. « Parlons de voyage » devient une page utile (services de voyage et destinations). Les deux pages d'avis présentent leur but et invitent recruteurs et professionnels à écrire à l'agence ; elles affichent clairement qu'aucun avis n'est encore publié.
- **Vidéo** : la seule vidéo pertinente (panel sur l'immigration au Québec) est gardée sur « Salons internationaux », signalée comme vidéo externe et chargée seulement au clic (`youtube-nocookie.com`). La vidéo Ecobank n'est pas reprise.

## Plan

1. Script d'import et `config/activities.php`.
2. `ActivityController`, vue `pages/activity.php`, styles dans `content.css`.
3. Routes aux adresses WordPress (avant les articles).
4. Vérifications : 8 pages en 200, liens du menu basculés, affichage mobile et bureau.
5. Résultat ici, plan de migration et mémoire.

## Résultat (20 novembre 2026)

Les 8 pages répondent sur le nouveau site et les menus « Offres » et « Activités » y pointent (`legacy_url()`).

| Fichier | Rôle |
| --- | --- |
| `database/import/wordpress-activities.php` | Extrait le texte de « Salons internationaux » et « Taysir Club Challenge » (espaces corrigés) vers `database/content/activities/` |
| `config/activities.php` | Les 8 pages : groupe, titre et mot en italique, accroche, encadré, listes d'articles, cartes de liens, vidéo, encadré latéral. `{whatsapp}` désigne le WhatsApp de l'agence |
| `app/Controllers/ActivityController.php` | `show($slug)` : texte repris, listes d'articles non vides (`Article::byTypes`), pages sœurs ; `href()` pour les actions |
| `app/Views/pages/activity.php` | Gabarit commun (bandeau, encadré, texte, compétences, vidéo, listes, liens, encadré latéral, autres pages du menu) |
| `public/assets/css/content.css`, `public/assets/js/content.js` | Styles « Offres et activités » ; vidéo YouTube (`youtube-nocookie.com`) chargée au clic seulement |
| `routes/web.php` | Une route par page, avant les articles |

Contenu par page :

- `/offre_stage/`, `/offre-demploie/` : avertissement « publiées en juin 2024, conservées en archive », puis stages ou emplois (5), formations certifiantes (5) et bourses (4) ; encadré « Créer mon compte » et « Prendre rendez-vous ».
- `/avis-de-recruteurs/`, `/avis-de-professionnels/` : but de la rubrique, mention « aucun avis n'est encore publié », liens utiles, invitation à écrire à l'agence (WhatsApp, contact@taysir-orientation.com). **Aucun avis inventé.**
- `/parlons-de-voyage/` : les 5 services de voyage et les destinations, rendez-vous « Billet d'avion ou hôtel ».
- `/salon-collegien/` : les 3 forums de mars 2024 ; « Invitez Taysir » pour les établissements.
- `/salons-internationaux/` : texte WordPress, vidéo « Panel : l'immigration au Québec » (chaîne tierce, signalée), liens Canada, visa, destinations.
- `/taysir-club-challenge/` : texte WordPress, les 4 compétences du texte, « Rejoindre le club ».

Corrections communes faites au passage :

- `.flash` déplacé de `auth.css` vers `app.css` : l'encadré d'information n'était pas mis en forme sur les pages qui ne chargent pas `auth.css` (dont « Quel métier ? »).
- `html { overflow-x: clip; }` dans `app.css` : sur mobile, les éléments `data-reveal="right"` (décalés avant leur apparition) élargissaient la zone d'affichage à 430 px pour un écran de 375 px, ce qui faisait dézoomer la page. Concernait aussi les pages existantes. Les éléments collants (en-tête, encadré latéral) fonctionnent toujours.

Vérifications : 8 pages en 200 ; liens des menus basculés (restent 9 liens vers l'ancien site : pages de l'agence, blog, témoignages, « Partir à l'étranger », « Journal des écoles ») ; vidéo chargée au clic ; affichage bureau et mobile sans débordement.

### Reste à faire

- Les offres sont figées (articles de 2024). Pour une agence vivante, il faudra **publier les offres depuis le back-office** (titre, entreprise, lieu, date limite, statut) et permettre aux clients connectés de postuler ou d'être alertés.
- Faire fournir par le client les vrais avis de recruteurs et de professionnels, les lauréats du club et les prochaines dates de salons.

## Bandeaux photographiques

### Plan

Appliquer le bandeau de la page Destinations aux huit pages qui utilisent la vue commune Offres et Activités. Créer une illustration pour chaque sujet, en WebP paysage et portrait. Adapter le lien de découverte au contenu de la page, puis vérifier les huit routes, le cadrage mobile et les accès aux rubriques.

### Résultat

- Les huit pages chargent le bandeau commun aux destinations. La navigation se superpose à la photo, avec un titre Prata animé, une accroche Poppins, un voile noir uni et le fil d’Ariane. Le lien de découverte mène au contenu de la rubrique. Les avis vides et les offres archivées conservent leur mention sous le bandeau.
- Les illustrations et les libellés sont définis dans `config/activities.php`. Les contenus, les offres, les ressources, la vidéo externe et les liens entre les pages passent toujours par `pages/activity.php`.
- L’outil ImageGen intégré a produit huit scènes, enregistrées dans `public/assets/images/activities/`. Chaque scène est disponible en WebP paysage de 1 672 × 941 px et en portrait de 753 × 941 px. Les bandeaux pèsent de 92 à 152 Kio en paysage et de 44 à 79 Kio en portrait. La mention « Image d’illustration » accompagne chaque scène.

### Consignes de génération

> Photorealistic editorial reportage for Taysir, a Senegalese study and career guidance site. Widescreen 16:9 photograph, natural documentary light and believable modern Senegalese settings and clothing. Put the main subjects and identifying action in the central 42% of the frame, with comfortable headroom and readable faces even after a centered portrait crop. Keep the upper band tonally calm for an overlaid white navigation bar. Real textures, genuine expressions, premium travel magazine color, no logos, watermarks, readable text, graphics, or collage. This is a fictional illustrative scene.

- `emplois` — A professional job interview at a Dakar office with warm timber and broad urban windows. Senegalese young adult woman candidate in a tailored deep olive jacket offering her résumé to a member of the hiring team across a table. Both visible near center, natural eye contact, confident conversation. Subtle city texture outside.
- `stages` — A young Senegalese adult intern learning from an older colleague in a Dakar architecture and engineering studio. They bend over a small physical building model, annotated plans without readable lettering and a scale rule, creative light and authentic teamwork. Younger learner and mentor centered together across the workbench.
- `recruteurs` — Three Senegalese hiring managers in a contemporary boardroom comparing applicant folders together, one woman leading the conversation with the other two listening. Human business portrait during deliberation, papers and neutral wooden table, mid-distance and subdued daylight. No candidates in the scene.
- `professionnels` — An experienced Senegalese female electrical engineer in a navy work shirt showing two visiting young adult students a compact solar-training installation in a neat workshop. She's telling a story and pointing to a component, with tool cabinet and coils behind. Human mentorship and the everyday reality of a technical profession, warm morning light.
- `voyage` — Senegalese young adult traveller with carry-on luggage walking toward a panoramic terminal window with a commercial passenger jet visible beyond, believable modern West African airport, rain fading at dawn and warm interior reflections, central traveller in three-quarter view with navy backpack. No visible identity documents, readable screens, or signage.
- `salon-collegien` — A lively Senegalese school career forum in a shaded school courtyard. A warm adult guidance counsellor in a large booth talking to a small group of Senegalese secondary-school students holding notebooks, natural curiosity and conversation, simple covered tables and sunlit ochre courtyard in the distance. Arrange key councillor and students together in the middle.
- `salons-internationaux` — A professional international education fair in a modern convention hall, viewed across the aisle toward a booth where an adviser meets two adult prospective students from Dakar. A deeper row of neutral booths, subtle exhibition lighting and attendees in the background. Quietly ambitious, no visible artwork or branded backdrops.
- `club-challenge` — A diverse team of Senegalese teenage pupils presenting a collaborative classroom project to their peers, a student holds a small handmade prototype while the others explain, bright focused energy, warm indoor daylight. Central group with the project visible, classmates softly out of focus. Authentic Dakar school event, no award, trophy or lettering.

### Vérifications locales

- Les huit routes et leurs 16 WebP répondent sur le port 8080. La syntaxe PHP passe.
- Les bandeaux ont été contrôlés à 320, 390, 768 et 1 440 px. L’image portrait se charge sur les fenêtres étroites, et le rendu des pages contrôlées tient dans la largeur disponible.
- Sur les offres d’emploi, le lien du bandeau est utilisable au clavier et place le focus sur les annonces et leur mention d’archive. La page des salons internationaux affiche toujours le bouton de lecture de la vidéo.
