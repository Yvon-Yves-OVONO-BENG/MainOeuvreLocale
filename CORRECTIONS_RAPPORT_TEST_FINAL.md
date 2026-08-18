# Correctifs du rapport de test final — 15 août 2026

## Correctif complémentaire — modification du profil

- L’identifiant de catégorie soumis par le navigateur est désormais converti explicitement en entier avant la création du formulaire. L’erreur `categorie_id ... expected null or int, string given` est supprimée.
- Le pays utilise une liste native stable sur mobile. Le drapeau sélectionné est affiché dans un cadre de `28 × 20 px` et ne peut plus recouvrir le formulaire.
- Les composants Select2/Tom Select éventuellement initialisés sur ce champ sont neutralisés uniquement pour le pays afin d’éviter le retour intempestif vers un autre pays.

## Périmètre traité

| # | Anomalie du rapport | Correction livrée |
|---:|---|---|
| 1 | Photo de profil difficile à choisir ou remplacer sur Pixel 3 | Sélecteur mobile clair, aperçu immédiat, nom et poids du fichier, annulation de la sélection et conservation de la photo actuelle. |
| 2 | Le pays sélectionné revient sur « Cuba » | Suppression des chargements JavaScript dupliqués et stabilisation de la valeur réellement rendue/sélectionnée. |
| 3 | Enregistrement du profil retardé de 3 à 5 minutes | Compression navigateur ramenée à deux passes avec budget maximal de 8 secondes, optimisation serveur allégée et retour visuel immédiat. |
| 4 | « Voir le profil complet » provoque une erreur serveur | Génération de l’URL de contact corrigée avec un paramètre conforme à la contrainte de route. |
| 5 | Pagination des annonces lente et retour en haut de l’accueil | Chargement AJAX de la seule section demandée, requête serveur courte, conservation du contexte et repositionnement sur la section. |
| 6 | Bouton « Profil » de la liste des talents en erreur | Même correction de route du profil public, à l’origine de l’écran 500. |
| 7 | Boutons avis, évaluation et profil des compagnies en erreur | Routage vers la page de réputation adaptée aux profils compagnie, avec ancres avis/notation. |
| 8 | Même anomalie pour les particuliers | Routage vers la page de réputation adaptée aux profils particulier, avec ancres avis/notation. |
| 9 | Textes superposés dans le choix du paiement sur mobile | En-tête, retour, récapitulatif et cartes de moyens de paiement rendus adaptatifs aux petits écrans. |
| 10 | La durée choisie est perdue à la validation | Transmission explicite de la durée, contrôle d’appartenance au plan, protection CSRF et calcul de la vraie échéance de l’abonnement. |
| 11 | Images et pages lentes | Images secondaires différées, cartes en lazy-loading, cache-busting aléatoire supprimé et bibliothèques front chargées une seule fois. |

## Sécurisation du paiement

Le service d’origine simulait systématiquement un paiement réussi. Ce comportement n’est pas acceptable en production.

- La carte bancaire utilise désormais Stripe Elements : les données de carte ne transitent pas par le serveur applicatif.
- Le serveur crée puis vérifie le PaymentIntent, son statut, son montant, sa devise, l’utilisateur, le plan et la durée avant toute activation.
- Une même référence Stripe ne peut pas créer plusieurs abonnements.
- Orange Money et MTN Mobile Money sont marqués « Bientôt disponible » et refusés côté serveur tant qu’un connecteur opérateur réel et ses callbacks signés ne sont pas configurés.

## Déploiement recommandé

1. Sauvegarder la version actuellement déployée et la base de données.
2. Déployer les dossiers `src/` et `templates/` de cette archive en conservant la configuration et les fichiers publics du projet complet.
3. Vérifier que la configuration Stripe déjà utilisée par le module Boost est active : clé publique, clé secrète et service `StripeClient`.
4. Reconstruire l’autoload et le cache Symfony dans l’environnement de production.
5. Tester d’abord en mode Stripe test, puis effectuer un paiement réel de faible montant après validation métier.
6. Contrôler sur Pixel 3 ou viewport équivalent : profil, pays, photo, pagination annonces, trois répertoires et abonnement.

Commandes usuelles à adapter au mode de déploiement :

```bash
composer install --no-dev --optimize-autoloader
APP_ENV=prod APP_DEBUG=0 php bin/console cache:clear
APP_ENV=prod APP_DEBUG=0 php bin/console cache:warmup
```

## Contrôles de non-régression prioritaires

- Un changement de photo ne modifie plus le slug public du profil.
- Un identifiant de durée appartenant à un autre plan est refusé.
- Un POST de paiement sans jeton CSRF ou sans confirmation Stripe ne crée aucun abonnement.
- Les liens compagnies/particuliers n’utilisent plus le contrôleur réservé au profil talent.
- En cas d’échec AJAX de pagination, la navigation HTML classique reste disponible.

Aucune migration de base de données n’est requise par ces correctifs.
