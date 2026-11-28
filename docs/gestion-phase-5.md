# Phase 5 — Finance interne

## Plan local

Un devis par dossier, un échéancier, les règlements déjà reçus et leurs reçus PDF. La devise de départ est le franc CFA (XOF). L’administrateur et le comptable peuvent saisir un règlement. L’administrateur peut enregistrer une annulation motivée, qui conserve le reçu d’origine. La référence est attribuée dans la transaction du règlement, avec une séquence par agence et par année. Le titulaire du dossier peut consulter ses paiements et télécharger ses reçus.

Les remboursements réels, les autres devises, la facturation légale et le paiement en ligne relèvent d’une décision de l’agence. Le PDF est un reçu interne de saisie d’un règlement. La recette locale couvre la numérotation, le rejeu, l’annulation et les droits sur les reçus.

## Livré le 28 novembre 2026

- Depuis la fiche d’un dossier, le comptable et l’administrateur ouvrent **Devis et règlements**. La page affiche le montant du devis, le total réglé et le solde. Les actions se placent sous le récapitulatif sur les écrans étroits.
- Un seul devis par dossier, figé dès le premier règlement. L’échéancier accepte une ligne par date et son total reste dans la limite du devis. Un règlement reçu est enregistré avec sa date et son mode. Le formulaire bloque les dates de réception futures selon le jour UTC.
- Un verrou par dossier protège le calcul du solde. Une double soumission conserve un seul règlement. La référence, le règlement et l’entrée d’audit sont écrits dans la même transaction. L’agence est celle du compte qui saisit le règlement et l’année est calculée dans son fuseau horaire.
- Seul l’administrateur enregistre une annulation motivée, même sur un dossier clos. Le règlement reste dans l’historique et sort du calcul du payé. Son PDF porte la mention « Annulé ». Les nouvelles saisies passent par un dossier ouvert.
- Le titulaire du dossier voit les montants et télécharge ses reçus. Les accès tuteur couvrent les démarches prévues pour le dossier. Le volet financier reste réservé au titulaire, à l’administration et à la comptabilité.

## Recette locale

La migration additive `021_finance.sql` a été appliquée sur WAMP. Une seconde exécution a confirmé que la base était à jour. Le script de retour arrière est réservé à un module dont les tables financières sont vides.

Le robot de recette a réussi **154 contrôles sur MySQL et 154 sur MariaDB**, dont huit pour la finance. Ils couvrent les droits par poste, le plafond de l’échéancier, le rejet d’un formulaire invalide et d’un surpaiement, la double soumission, la séquence de Dakar, le PDF privé et l’annulation tracée avec recalcul du solde. Les vues ont été testées par HTTP. La vérification visuelle sur appareils reste à faire.
