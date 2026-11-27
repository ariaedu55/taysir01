# Révisions à demander avant la phase 1

Je valide la direction générale de la refonte Taysir. Elle conserve un seul projet PHP MVC et un seul domaine, avec la connexion publique commune, l’espace client `/mon-espace/`, l’espace équipe `/gestion/` et la charte du site. Révise le cadrage avec les points suivants et soumets-moi la nouvelle version avant de lancer la phase 1.

## 1. Partir du back-office Taysir existant

Réutilise la connexion, les invitations d’équipe, la réinitialisation du mot de passe, la désactivation des comptes et les modules de demandes, rendez-vous, messages et e-mails. Ajoute une carte de transition pour chaque module et une seule règle de redirection après connexion. Le client qui se connecte depuis une demande de visa ou d’admission doit pouvoir revenir à ce formulaire si son accès est autorisé.

Inventorie les anciennes adresses `/admin/`, avec leur méthode HTTP, avant de les déplacer. Sur les pages GET, utilise des redirections temporaires pendant la transition et ne fixe les adresses définitives qu’après validation. Pour les formulaires POST, mets à jour les cibles et, si nécessaire, conserve une route de compatibilité qui invoque le même traitement avec les mêmes droits et contrôles CSRF.

## 2. Définir les droits sur chaque dossier

Garde les sept rôles comme base et livre une matrice des actions autorisées par rôle, agence et affectation. Précise qui peut voir un dossier non attribué, accéder à une autre agence, transférer un dossier, télécharger une pièce, voir une note interne, exporter et encaisser. Une agence doit délimiter l’accès côté serveur. Les listes, les compteurs, la recherche, les téléchargements et les exports suivent ce même périmètre. Une adresse ouverte directement doit appliquer les mêmes contrôles que le menu.

Clarifie si une personne peut couvrir plusieurs agences ou missions. Commence avec les profils standards et réserve les exceptions nominatives à des cas validés et audités. Prévois un test dans lequel un conseiller essaie de consulter ou télécharger une pièce d’un dossier affecté à un collègue.

## 3. Séparer identité, connexion et parcours

Prévois une fiche par personne, un compte de connexion lorsque nécessaire et plusieurs projets ou dossiers rattachés à la fiche. Un élève peut postuler dans plusieurs pays en parallèle, recevoir des réponses différentes et revenir pour une autre rentrée. Un prospect ou un visiteur peut exister avant son inscription. Précise comment un parent ou un tuteur accède aux dossiers d’un élève après vérification du lien et de ses droits.

Propose les rapprochements de doublons avec validation humaine. Deux personnes peuvent partager un téléphone ou une adresse e-mail de contact. Préserve les références, pièces, notes et historiques lors d’une fusion. Établis dès le cadrage la correspondance entre les demandes actuelles de Taysir et les futurs dossiers pour éviter deux suivis concurrents. La base actuelle supprime les demandes, puis leurs pièces et événements en base, lorsqu’un compte client est supprimé. Définis les règles de désactivation, de conservation et d’effacement avant de modifier ce comportement ou d’exposer une fonction de suppression.

## 4. Livrer d’abord un parcours métier complet

Prends comme scénario prioritaire la chaîne suivante. Une personne réserve un rendez-vous, reçoit un compte rendu, ouvre un dossier d’admission, dépose une pièce, voit son état et reçoit un rappel. L’équipe suit les actions du jour et peut reprendre le dossier depuis la fiche personne. Commence avec l’e-mail et la file d’envoi existante. Introduis la recherche globale quand elle permet déjà d’ouvrir des résultats utiles et autorisés. Réserve la signature en ligne et les intégrations de paiement aux phases qui disposent des décisions et des moyens de test nécessaires.

Pour l’agenda, précise les fuseaux horaires, le statut d’une demande par rapport à une réservation confirmée et la prévention des doubles réservations. Versionne les modèles de procédure, indique leurs sources officielles et leur date de vérification. Configure la validité des documents selon la destination et la démarche.

## 5. Préciser le périmètre financier et les données privées

Distingue l’enregistrement d’un paiement déjà reçu de l’encaissement en ligne intégré. Pour la première version de finance, confirme devis, échéances, règlement saisi et reçu. Clarifie les responsables, les corrections, les remboursements, la devise et les règles de numérotation avant de livrer ce module. Décide ensuite des prestataires et des modalités de signature avec l’agence.

Prévois les règles d’accès aux pièces, la visibilité des notes, le journal des accès et modifications sensibles, la conservation et la protection des sauvegardes dès le socle. Fais valider avec l’agence le cadre applicable aux dossiers de mineurs, aux transferts internationaux et à l’effacement des données, y compris l’application éventuelle du RGPD. Garde l’assistant public et son index limités au contenu public de la plateforme. [Loi sénégalaise sur les données personnelles](https://www.archives.sn/docs/codes/loi-protection-donnees-a-caractere-personnel) · [RGPD, notamment art. 3, 6, 17 et 44](https://eur-lex.europa.eu/eli/reg/2016/679/oj/fra)

## 6. Préparer la reprise dès le cadrage

Documente les correspondances GAOV vers Taysir, les données à reprendre, les comptes réels à réinviter, les comptes de test à écarter et les pièces à vérifier. Définis un import relançable avec rapport de rapprochement et écarts à traiter. Répète la reprise avant la bascule sur une copie protégée avec des données fictives ou anonymisées pour les tests courants. À la mise en service, prévois une sauvegarde restaurable, un gel des écritures GAOV, l’import final et le retour arrière si la recette échoue. Traite séparément la révocation de l’accès Git ancien signalé dans l’inventaire.

## 7. Valider des parcours et leurs effets

Conserve un document avant et après chaque phase et mon accord explicite avant la suivante. Définis les résultats attendus par scénario, notamment la redirection 303 après soumission et le refus d’un dossier hors périmètre. Vérifie les effets en base, les droits sur les éléments individuels, les téléchargements, l’absence de doublons après deux soumissions, les retours après connexion et les parcours historiques. Vérifie les écrans principaux au clavier et sur ordinateur, tablette et téléphone, avec des comptes de chaque rôle et des données représentatives. Fais approuver le tableau de bord, la navigation et une liste type en phase 1 avant de décliner la mise en page.

Pour lancer la phase 1, mes décisions prioritaires portent sur les rôles réellement exercés, les chevauchements de missions et les accès entre agences. Les décisions sur les paiements, le SMS et les modèles de documents seront prises avant leurs phases respectives.
