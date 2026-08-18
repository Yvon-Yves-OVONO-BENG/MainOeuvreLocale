# Ajouts du 21 juillet 2026

## 1. Mot de passe oublié

- L'expéditeur utilise désormais `ne-repondez-pas@maindoeuvrelocale.com`, comme les courriels d'activation.
- L'adresse saisie est recherchée sans tenir compte des majuscules.
- Un corps texte est fourni en plus du HTML.
- Les erreurs d'envoi sont journalisées et le jeton non envoyé est immédiatement invalidé.

La variable de production `MAILER_DSN` doit contenir les identifiants SMTP valides de la boîte du domaine `maindoeuvrelocale.com`.

## 2. Boost des annonces

- Nouvelle entité et table `BoosteAnnonce` / `booste_annonce`.
- Page `/annonces/booster` avec sélection de l'annonce.
- Offres : Essentiel (200 FCFA / 1 semaine), Plus (500 FCFA / 3 semaines) et Premium (1 000 FCFA / 8 semaines).
- Une demande répétée met à jour le boost en attente au lieu de créer des doublons.
- Les boosts dont le statut est `actif` et dont les dates sont valides passent automatiquement avant les annonces non boostées.

La sélection crée une demande `en_attente_paiement`. Le service qui confirme réellement le paiement doit appeler `BoosteAnnonce::activate()` puis enregistrer l'entité.

## 3. Colombe

- Suppression du bouton HTML dupliqué qui empêchait le script de cibler Colombe correctement.
- À la première visite connectée, présentation centrée avec un message agrandi, puis retour animé en bas à droite.
- Message affiché verticalement au-dessus de Colombe toutes les 30 secondes pendant 12 secondes.
- Message toujours masqué lorsque le panneau de discussion est ouvert.

## 4. Cartes

- La carte mobile des talents essaie automatiquement la position réseau puis le GPS et réutilise une position récente en secours.
- Rayon des talents harmonisé de 10 à 50 km, par pas de 10 km.
- Nouveau formulaire cartographique des annonces sur l'accueil avec mots-clés, ville et rayon.
- Nouvelle API `/api/annonces/proximite` ; seules les annonces publiées dont l'auteur a activé sa géolocalisation sont affichées.
- Les coordonnées publiques restent arrondies afin de ne pas révéler une adresse exacte.

## 5. Formulaires avec rayon

- Le libellé visible « Rayon » au-dessus du curseur a été retiré.
- Le libellé accessible reste présent pour les lecteurs d’écran.
- Les champs texte, le curseur et le bouton ont maintenant une hauteur uniforme de 50 px.

## 6. Compression des téléversements

- Les images JPEG, PNG et WebP de plus de 850 Ko sont redimensionnées et converties en WebP dans le navigateur avant l’envoi.
- Une seconde optimisation côté Symfony utilise Imagick/ImageMagick, avec GD en solution de repli.
- Les PDF sont allégés côté serveur avec Ghostscript lorsqu’il est disponible.
- Les profils, CNI, CV, portfolios, médias, professions et pièces jointes du tchat utilisent le service central `UploadOptimizer`.
- Aucune clé TinyPNG ni aucun service externe n’est nécessaire.

Pour le repli côté serveur, activer de préférence l’extension PHP `imagick` ou `gd`. Ghostscript (`gs`) est facultatif et concerne uniquement les PDF. La compression navigateur fonctionne même si ces composants ne sont pas présents.

## Migration et vérification

Préférer une migration Doctrine générée depuis l'entité :

```bash
php bin/console make:migration
php bin/console doctrine:migrations:migrate
php bin/console cache:clear
php bin/console doctrine:schema:validate
```

Le fichier `src/MIGRATION_REQUIRED.sql` contient aussi le SQL indicatif de création de `booste_annonce`.

## Correctifs du 18 août 2026

- Après activation par e-mail, le message confirme l'activation et demande explicitement de se connecter.
- Colombe / la messagerie n'est plus chargée sur l'écran de choix initial du type de profil.
- Pour `ROLE_COMPANY`, téléphone du contact, site web et adresse sont facultatifs.
- Le numéro d'immatriculation reste obligatoire et alimente automatiquement la file KYC entreprise existante pour vérification administrative.
- Les contrôles de rayon commencent à `0 km` et les validations serveur correspondantes acceptent cette valeur.
- Les formulaires Profil, Annonce et Offre utilisent une sauvegarde automatique locale des brouillons (hors mots de passe, fichiers, CSRF et champs cachés), avec restauration au retour.
- Un bouton `Retour` fixe, discret et uniforme est affiché sur les pages de contenu hors accueil/authentification/onboarding initial.

