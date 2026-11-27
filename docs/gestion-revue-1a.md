# Revue de la maquette de gestion 1A

La revue compare les écrans et les interactions au plan Taysir, puis relève les écarts avant validation de l’étape 1A. Le serveur local a servi aux essais avec les données fictives de la maquette.

## Vérifications

- Les huit postes internes sont présents dans le sélecteur. La secrétaire, l’agent de traitement et l’agent de prospection disposent d’actions distinctes. Les titres utilisent Prata et le texte courant Poppins.
- Dans « Ma journée », la secrétaire confirme un rendez-vous. Le compteur passe de quatre à trois et « Annuler » rétablit la liste. L’agent de traitement arrive directement sur les pièces et peut simuler une validation. Le filtre « Non attribués » permet de sélectionner les six prospects visibles. Après attribution simulée, la liste filtrée est vide.
- « Ma journée » ne déborde pas horizontalement à 1440, 768 et 375 px. Les parcours clients études et voyage s’ouvrent. À 375 × 667 px, les deux boutons de dépôt du parcours études sont visibles dès l’ouverture. Le portrait flottant recouvre visuellement une partie de la carte de paiement.
- Les transferts entre postes persistent uniquement dans la page courante. Les accès réels, la persistance, la redirection selon le rôle et le test T7 appartiennent à l’étape 1B.

## Ajustements à demander avant validation

1. Adapter les raccourcis mobiles au poste. La navigation rapide de l’agent de prospection affiche « Dossiers » et un raccourci Agenda provisoire. Y donner la priorité à « Prospects ». Masquer ou identifier clairement les destinations encore en chantier.
2. Prévoir des vues de dossier adaptées aux missions. Les sept onglets sont aujourd’hui affichés sans distinction de rôle. Montrer séparément les éléments utiles à la secrétaire, à la comptabilité, au conseil, à l’orientation et au traitement. Retirer le remplacement d’une référence inexistante par la première fiche de la liste.
3. Éviter le chevauchement de l’assistant et des actions client sur les écrans courts. Vérifier aussi le contraste des libellés blancs sur le rouge d’accent et utiliser le rouge d’action existant pour les boutons concernés.
4. Compléter la recette avec un refus de pièce et son motif, un transfert relu depuis le poste destinataire et un retour depuis la liste des dossiers. Dans le prototype, le refus ne met pas à jour le compteur de pièces comme le fait la validation. La simulation d’un transfert devrait indiquer clairement jusqu’où l’état est conservé.

En 1B, les listes, compteurs, résultats de recherche, fiches et opérations sur les pièces doivent tous appliquer la même politique côté serveur. Le filtrage de recherche de la maquette distingue principalement le type de résultat et le rôle. La recette de 1B doit couvrir les affectations et agences, les anciennes routes et l’accès légitime du client à son dossier.

## Prompt à transmettre à Claude pour la finition de la 1A

Je souhaite une nouvelle passe de design sur la maquette avant de valider la 1A. Les écrans me paraissent trop sobres. Donne à l’espace de gestion une identité plus colorée, élégante et dynamique, cohérente avec le site Taysir. Garde le logo, la typographie Prata et Poppins et le rouge de marque pour les actions principales. Élargis la palette de l’espace de gestion avec des couleurs secondaires qui aident à lire le travail du jour.

- **Cartes d’indicateurs.** Donne aux petites cartes une vraie présence visuelle avec des fonds teintés, des icônes colorées et des chiffres qui se lisent au premier regard. Essaie un bleu clair pour les rendez-vous, un lilas pour l’orientation, un vert doux pour les validations et un ambre pour les échéances proches. Utilise le rouge pour les urgences et le rouge d’action Taysir pour les boutons principaux. Associe chaque statut à un libellé explicite.
- **Navigation et icônes.** Donne aux groupes métier des icônes distinctes, accompagnées d’une couleur cohérente dans les menus, les cartes et les fiches. Dessine un état actif lisible, des compteurs clairement rattachés à leur rubrique et des raccourcis mobiles adaptés au poste. Par exemple, l’agent de prospection doit retrouver « Prospects » dans ses accès rapides.
- **Hiérarchie et mise en page.** Fais varier la composition selon les priorités du poste. Pour la secrétaire, mets les rendez-vous à confirmer et l’accueil du jour au premier plan. Pour l’agent de traitement, privilégie les pièces à vérifier et les échéances. Pour le comptable, mets en avant les règlements et les échéances financières. Donne à la fiche dossier une progression lisible et rends évidente la prochaine action du client dans les parcours études et voyage.
- **Interactions et finitions.** Utilise des transitions courtes pour ouvrir les panneaux, changer de filtre et confirmer une action. Prévois un focus visible, des états vides et d’erreur, des contrastes lisibles, ainsi qu’un affichage adapté au mouvement réduit. Sur téléphone, vérifie que les actions principales restent faciles à atteindre et que l’assistant flottant ne recouvre aucun contenu utile.

Applique cette direction à « Ma journée », à la navigation, aux listes et fiches de dossiers, à la prospection et aux deux parcours clients. Traite aussi les quatre ajustements d’ergonomie de la revue initiale. Présente-moi ensuite la maquette navigable et des captures en 1440, 768 et 375 px, avec au moins trois postes aux missions différentes et les parcours clients études et voyage. Consigne les couleurs retenues, les composants revus et les scénarios retestés dans `docs/gestion-phase-1.md`. Attends ma validation de la 1A avant de démarrer la 1B.
