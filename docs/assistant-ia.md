# Assistant IA Taysir

Demande du 23 novembre 2026 : un assistant IA qui répond à toutes les questions d'orientation.
- **Connaissances** : il connaît tout le contenu de la plateforme.
- **Recherche en ligne** : quand une information manque, il cherche sur internet avec la recherche avancée de DeepSeek.
- **Accès** : tout visiteur du site peut dialoguer avec lui.
- **Affichage** : les réponses arrivent au fil de l'eau, comme dans ChatGPT, Claude ou DeepSeek (effet machine à écrire).
- **Clé** : la clé API est provisoire ; elle est rangée dans `.env`, et l'utilisateur la remplacera.

## Constat

- **API DeepSeek** (documentation lue le 23/11/2026) :
  - `POST https://api.deepseek.com/chat/completions`, au format OpenAI, avec l'en-tête `Authorization: Bearer`.
  - Modèles : `deepseek-flash` (rapide, économique) et `deepseek-v4-pro`.
- **Recherche web côté serveur DeepSeek** : outils `{"type": "deepseek_web_search"}` et `{"type": "deepseek_read_url"}`. Le modèle décide seul d'aller en ligne, et DeepSeek exécute la recherche.
  - Sans le mode réflexion : recherche simple.
  - Avec `"thinking": {"type": "enabled"}` : recherche approfondie, environ 20 recherches et lectures de pages au plus.
  - Les sources arrivent dans `annotations` (`url_citation`).
- **Test de la clé** : elle est acceptée, mais le compte n'a **aucun crédit** (HTTP 402 « Insufficient Balance »). Il faut recharger le compte DeepSeek pour obtenir des réponses réelles.
- **Configuration** : le site utilise `config/*.local.php`. `.env` est déjà exclu de Git, mais n'était lu par rien.
- **Contenus à indexer** (tous publics) :
  - 81 articles en base (métiers, formations, blog, offres) ;
  - 12 fiches pays et leurs guides ;
  - 8 universités ;
  - les services et leurs démarches ;
  - les secteurs ;
  - les pages d'orientation ;
  - les activités ;
  - les pages de l'agence (à propos, règles, témoignages) ;
  - la FAQ ;
  - les coordonnées et horaires ;
  - les établissements.
- **Contenus jamais envoyés à l'IA** : les demandes, les documents, les comptes et les messages des clients (données personnelles).

## Conception

1. **Configuration**
   - Chargeur `.env` (`app/Helpers/env.php`), lu au démarrage, avec la fonction `env()`.
   - `.env.example` versionné, sans clé.
   - `config/assistant.php` : modèle, limites, activation.
2. **Base de connaissances** (`app/Services/Assistant/KnowledgeBase.php`)
   - Tout le contenu public est découpé en passages d'environ 900 caractères, chacun avec son titre et son adresse.
   - Les passages sont mis en cache dans `storage/cache/assistant-index.php`, reconstruit automatiquement dès qu'un contenu change (signature des fichiers et des articles).
   - Recherche BM25 en PHP, sans service externe : les accents et les mots vides sont ignorés.
   - À chaque question, les 6 passages les plus pertinents sont envoyés à l'IA, avec un sommaire du catalogue : pays, universités, services et leurs adresses.
3. **Point d'entrée** `POST /assistant`
   - Protégé par CSRF.
   - Réponse en flux (`text/event-stream`), relayée en direct depuis DeepSeek.
   - Événements envoyés au navigateur : `status` (« Recherche en ligne… »), `delta` (texte), `sources`, `error`, `done`.
   - Session libérée avant l'appel, pour ne pas bloquer les autres onglets.
   - En-tête `X-Accel-Buffering: no`, pour que Nginx ne retienne pas le flux.
4. **Consignes de l'assistant**
   - Il se présente comme l'assistant de Taysir Orientation Voyage.
   - Il répond dans la langue de la question.
   - Il s'appuie d'abord sur le contenu de la plateforme, avec des liens vers les pages.
   - Il cherche en ligne si l'information manque ou peut avoir changé (dates, frais, procédures), et cite ses sources.
   - Il n'invente jamais une date, un prix ou une condition.
   - Il propose un rendez-vous avec un conseiller pour les cas personnels.
   - Il ne demande pas de données sensibles (numéro de passeport, documents).
5. **Limites (coût et abus)**
   - 15 messages par 10 minutes et 80 par jour par adresse IP ; 3 000 par jour pour tout le site.
   - Questions de 1 500 caractères au plus, 8 derniers échanges conservés.
   - Fichiers de compteurs dans `storage/cache/assistant/`.
6. **Interface** (`partials/assistant.php`, `css/assistant.css`, `js/assistant.js`), présente sur toutes les pages
   - Un bouton flottant « Assistant Taysir » en bas à droite.
   - Le panneau de conversation : titre en Prata, suggestions de questions, zone de saisie, interrupteur « Recherche approfondie ».
   - **Machine à écrire** : le texte reçu entre dans une file, puis s'affiche caractère par caractère à vitesse régulière, avec un curseur qui clignote. La vitesse s'adapte pour ne jamais prendre de retard sur le flux.
   - **Mise en forme** : gras, listes, titres et liens, construite de façon sûre (texte échappé).
   - **Recherche et sources** : un statut animé pendant la recherche en ligne, puis les sources en liens sous la réponse.
   - **Commandes** : bouton « Arrêter », conversation gardée dans l'onglet (`sessionStorage`), bouton « Nouvelle conversation ».
   - **Accessibilité** : panneau en dialogue, réponse finale annoncée aux lecteurs d'écran, clavier (Échap pour fermer).
   - **Mobile** : panneau plein écran.
   - **Charte** : blanc, texte noir, rouge pour le bouton d'envoi et les accents, aucun dégradé.
7. **Mode simulation** (`ASSISTANT_MOCK=1` dans `.env`) : un flux factice avec statut, texte et sources, pour tester l'interface sans crédit. Il est désactivé par défaut.

## Plan

1. Chargeur `.env`, `.env.example`, `config/assistant.php`.
2. Base de connaissances et script `bin/assistant-index.php` (reconstruction manuelle et statistiques).
3. Client DeepSeek en flux, limiteur et `AssistantController`, route `POST /assistant`.
4. Interface : partial, CSS, JS, inclusion dans le gabarit.
5. Tests :
   - index : nombre de passages, pertinence sur des questions types ;
   - flux complet en simulation ;
   - erreur 402 gérée proprement ;
   - CSRF et limites ;
   - affichage sur ordinateur et mobile.

## Résultat (23 novembre 2026)

### Fichiers

| Fichier | Rôle |
| --- | --- |
| `.env` (non versionné, droits 600) | `DEEPSEEK_API_KEY` (clé provisoire), `DEEPSEEK_MODEL=deepseek-flash`, `ASSISTANT_ENABLED=1`, `ASSISTANT_MOCK=0` |
| `.env.example` (versionné, sans clé) | modèle à copier sur chaque serveur |
| `app/Helpers/env.php` | `load_env()` et `env()`, chargés dans `app/bootstrap.php` |
| `config/assistant.php` | modèle, délais, longueurs, limites |
| `app/Services/Assistant/KnowledgeBase.php` | index du contenu public, recherche BM25, sommaire du catalogue |
| `app/Services/Assistant/DeepSeekClient.php` | appel en flux, outils serveur de recherche, analyse des événements |
| `app/Services/Assistant/RateLimiter.php` | limites par empreinte d'adresse IP et par jour |
| `app/Controllers/AssistantController.php` | `POST /assistant`, consignes de l'IA, flux, mode simulation, journal |
| `bin/assistant-index.php` | reconstruit l'index ; avec une question, affiche les passages trouvés |
| `app/Views/partials/assistant.php` | interface |
| `public/assets/css/assistant.css` | styles de l'interface |
| `public/assets/js/assistant.js` | flux, machine à écrire, mise en forme |
| `app/Views/layouts/main.php` | interface ajoutée à toutes les pages si l'assistant est activé |
| `routes/web.php` | route `/assistant` |

### Base de connaissances

Elle couvre **129 pages** et **768 passages**. Elle se construit en 0,3 s, puis est mise en cache et reconstruite dès qu'un contenu ou un article change.

| Question de test | Première source trouvée |
| --- | --- |
| « Comment obtenir un visa étudiant pour la France ? » | Étudier en France |
| « Je veux devenir médecin, quel parcours ? » | Fiche Médecin |
| « Combien coûte l'accompagnement pour le visa Canada CAQ ? » | FAQ, puis Canada |
| « Quels sont vos horaires et votre adresse ? » | Coordonnées et horaires |

Ce dernier test a conduit à rédiger les coordonnées en toutes lettres.

### Vérifications

- **Sécurité** : sans jeton CSRF, la réponse est **419**. Les contenus privés (demandes, documents, comptes) ne sont jamais indexés.
- **Flux simulé** (`ASSISTANT_MOCK=1`), du navigateur jusqu'à l'écran :
  - statuts « Consultation des contenus Taysir », « Réflexion approfondie » et « Recherche en ligne » ;
  - texte écrit en direct, avec le curseur rouge au bout du dernier mot ;
  - mise en forme : titres, listes numérotées, liens ;
  - sources, avec des pastilles « T » pour les pages Taysir et l'initiale du site pour le web ;
  - bouton « Arrêter » pendant la réponse ;
  - conversation gardée dans l'onglet et retrouvée sur une autre page ;
  - réponse annoncée aux lecteurs d'écran.
- **Appel réel** : DeepSeek est bien joint, mais répond **402 Insufficient Balance**. Le visiteur voit « L'assistant est momentanément indisponible. Un conseiller Taysir peut vous répondre… », et le journal note l'erreur sans le contenu de la question.
- **Limites** : la 16e question en 10 minutes est refusée avec un message clair.
- **Encodage** : un texte mal encodé est nettoyé (`mb_scrub`, `JSON_INVALID_UTF8_SUBSTITUTE`), au lieu de faire échouer la requête.
- **Mobile (375 px)** : bouton rond, panneau plein écran.

### À faire avant la mise en service

1. **Recharger le compte DeepSeek.** Sans crédit, aucune réponse réelle n'est possible.
2. **Valider l'appel réel.** Une fois le compte rechargé, vérifier une vraie réponse, puis une recherche en ligne et une recherche approfondie. Les paramètres `tools` (`deepseek_web_search`, `deepseek_read_url`), `thinking` et `reasoning_effort` suivent la documentation, mais n'ont pas pu être validés à cause du solde nul. En cas d'erreur 400, ajuster `DeepSeekClient::stream()`.
3. **Remplacer la clé.** La clé provisoire est à remplacer dans `.env` par la clé définitive.
4. **Serveur.** Créer `.env` sur le VPS (`/home/jomas/taysir/.env`, droits 600, propriétaire `jomas`) avec la clé, puis déployer.

## Portrait de l’assistante — 23 novembre 2026

Créer un portrait de conseillère sénégalaise fictive au casque, puis l’intégrer au bouton flottant, à l’en-tête et aux réponses. Prévoir une image WebP transparente, retirer la rotation et vérifier l’ouverture et la fermeture du panneau sur ordinateur et mobile.

Le portrait généré est intégré dans le composant partagé avec le fichier `public/assets/images/assistant/conseillere-taysir.webp` (192 × 192 px, 7 Ko). Le bouton rond sur mobile conserve le nom accessible « Assistant Taysir ». Sur ordinateur, ce libellé apparaît à côté du portrait.

L’image se charge dans le bouton et dans le panneau à 555 px et 1280 px. Le panneau s’ouvre au clic ou au clavier et le focus revient au bouton après la fermeture.

## Bulle d’accueil — 26 novembre 2026

Agrandir légèrement le portrait du bouton. Afficher « Comment puis-je vous aider ? » directement au chargement de chaque page qui inclut l’assistant, puis retirer la bulle à la première ouverture du panneau. Vérifier la navigation, l’actualisation et l’interaction sur mobile.

Le portrait passe de 3,25 à 3,75 rem. La bulle apparaît au-dessus du bouton et permet aussi d’ouvrir la conversation. Elle est produite par le gabarit partagé à chaque chargement, puis masquée à l’ouverture du panneau pour la page en cours. Le fondu respecte la préférence de mouvement réduit.

Le rendu HTTP de la bulle et du portrait a été confirmé sur l’accueil et sur deux chargements successifs de la page Contact. Les vérifications de syntaxe PHP et JavaScript passent. Le navigateur de test n’a pas pu atteindre le serveur local pour la vérification visuelle sur mobile.

## Contour du bouton — 26 novembre 2026

Ajouter un contour rouge translucide animé autour du bouton et agrandir le portrait sur téléphone. Prévoir une pulsation douce qui s’arrête avec la préférence de mouvement réduit.

Le contour rouge à 40 % d’opacité pulse toutes les 2,4 secondes. Sur mobile, le portrait mesure 72 px. La vérification dans le navigateur à 501 px confirme la taille et l’animation appliquées. La préférence de mouvement réduit désactive cette pulsation.

## Lancer le site en local

## Orientation et recherche autonome

Renforcer l’analyse du profil et des échanges précédents, élargir les extraits publics consultés, corriger la lecture des événements web DeepSeek et traiter les flux interrompus. Vérifier la recherche locale, les événements simulés et la disponibilité réelle de l’API.

L’analyse DeepSeek est activée par défaut. Le contexte passe à 12 extraits et le budget de génération à 6 000 jetons. Les consignes demandent une première réponse utile, au maximum deux questions de précision, une comparaison de parcours et des liens vers les démarches. Les publications conservent leur date pour identifier les archives. Les textes d’admission copiés entre établissements doivent être vérifiés.

Le lecteur traite désormais les événements `server_tool_event` et les appels imbriqués dans `server_tool_calls[].calls`, conformément à la documentation officielle DeepSeek. Il détecte les erreurs transmises dans un flux HTTP 200, les réponses vides et les coupures. Une réponse limitée en longueur est signalée au visiteur.

Vérifications réalisées avec MySQL local, 130 pages et 788 passages indexés, recherche UCAD et informatique cohérente. Un serveur simulé a validé les événements de recherche sans doublons, les citations, les erreurs de génération, les réponses vides et les flux interrompus.

L’appel réel du 26 novembre répond encore HTTP 402 `Insufficient Balance`. La qualité des recommandations et la recherche web réelle doivent être validées après rechargement du compte. Le mode simulé vérifie uniquement le fonctionnement de l’interface.

Référence utilisée — [outils web et protocole Chat Completions DeepSeek](https://api-docs.deepseek.com/guides/web_tools/).

## Lancement local

Double-cliquer sur `bin/serveur-local.bat` (WAMP doit tourner pour MySQL), puis ouvrir http://127.0.0.1:8080. Fermer la fenêtre arrête le serveur. Un seul serveur à la fois sur le port 8080.
