# Corrections KJD — Main d’Œuvre Locale

Date : 5 août 2026

## Périmètre respecté

- Le module de chat, ses templates, ses contrôleurs, ses services et ses scripts n’ont pas été modifiés.
- Contrôle d’intégrité effectué sur **78 fichiers liés au chat / messages / conversations / appels** : empreintes identiques à l’archive d’origine.
- Les nouveaux blocs CSS et JavaScript des interfaces corrigées ont été déplacés vers `public/css` et `public/js`.

## Correctifs issus du rapport KJD

### 1. Métiers, postes et compétences composés uniquement de chiffres

- Ajout d’une validation commune côté serveur et côté navigateur.
- Les lettres, espaces, apostrophes, points, virgules, `+`, `-`, `/` et parenthèses sont acceptés.
- Une saisie composée uniquement de chiffres, telle que `123456`, est refusée avec un message explicite.
- La règle est appliquée aux recherches, aux offres, aux annonces, aux catégories et aux professions administrées.

Fichiers principaux :

- `src/Service/Search/SearchInputSanitizer.php`
- `public/js/search/smart-keyword-search.js`
- `src/Form/JobType.php`
- `src/Form/AnnonceType.php`

### 2. Recherche trop stricte et auto-suggestion

- Recherche insensible à la casse et aux accents.
- Suggestions triées dans l’ordre demandé :
  1. libellés qui commencent par le texte saisi ;
  2. libellés qui contiennent le texte saisi.
- Exemple : `IN` propose d’abord `Informatique`, `Infographie`, puis les résultats tels que `Cuisine` ou `Design`.
- Les listes de professions et catégories vides ou dupliquées sont filtrées avant affichage.

Fichiers principaux :

- `src/Service/Search/SearchSuggestionService.php`
- `src/Controller/Web/Search/SearchSuggestionController.php`
- `public/js/search/smart-keyword-search.js`

### 3. Annulation d’un signalement

- Route conservée en `POST` uniquement.
- Vérification CSRF et contrôle du propriétaire du signalement.
- Suppression de la réponse JSON brute visible dans le navigateur.
- Redirection vers la liste avec le message : **« Merci, votre signalement a été annulé. »**

Fichiers principaux :

- `src/Controller/Web/Report/AnnulerReportController.php`
- `templates/report/my_reports.html.twig`
- `public/js/report/my-reports.js`

### 4. Champ Pays incorrectement invalide dans le profil

- Utilisation d’une vraie sélection d’entités `Country` avec `NotNull`.
- Conservation du pays déjà lié au profil, même s’il provient d’un ancien doublon historique.
- Suppression du faux état rouge sur `Cameroun`.
- Initialisation unique de Tom Select et suppression correcte de l’erreur après sélection.

Fichiers principaux :

- `src/Form/AccountProfileEditType.php`
- `src/Controller/Web/Profile/ProfileController.php`
- `public/js/profile/edit.js`

### 5. Champs Mission et Profil recherché restant rouges

- Validation dynamique recalculée à chaque saisie.
- Retrait immédiat de la bordure rouge dès que le champ devient valide.
- Message d’erreur visible sous chaque champ obligatoire.
- Bouton de soumission activé uniquement lorsque toutes les données réellement requises sont valides.

Fichiers principaux :

- `templates/job/new.html.twig`
- `public/js/job/job-form.js`
- `public/css/job/job-form.css`

### 6. Sécurité du changement de mot de passe

- Lecture correcte des champs `mapped=false`.
- Vérification obligatoire du mot de passe actuel avec le hasher Symfony.
- Aucun changement lorsque le mot de passe actuel est incorrect.
- Message d’erreur explicite affiché à l’utilisateur.
- Protection CSRF activée avec un identifiant dédié.
- Journalisation du rejet et de la réussite.

Fichiers principaux :

- `src/Controller/Web/Settings/SettingsController.php`
- `src/Form/PasswordChangeType.php`

### 7. Doublons dans les notifications et les sélections

- Suppression des labels dupliqués dans le formulaire de notifications.
- Nettoyage des listes de pays, catégories et professions : valeurs vides exclues et doublons neutralisés.
- Blocage de la création d’une catégorie ou profession déjà existante dans l’administration.

Fichiers principaux :

- `src/Form/NotificationSettingsType.php`
- `src/Repository/CountryRepository.php`
- `src/Repository/CategorieRepository.php`
- `src/Repository/ProfessionRepository.php`

### 8. Téléchargement Android

- Création d’une route de téléchargement dédiée : `/telechargements/android`.
- Envoi de l’APK avec le bon type MIME, le nom de fichier et l’en-tête `nosniff`.
- Message propre lorsque l’APK n’est pas encore présent.
- Le bouton iPhone est affiché comme indisponible plutôt que comme un faux lien.

**Action obligatoire avant mise en production :** déposer l’APK Android signé à cet emplacement exact :

```text
public/downloads/main-doeuvre-locale.apk
```

L’archive reçue ne contenait aucun fichier APK ; le contrôleur et le bouton sont prêts, mais le binaire signé doit être ajouté séparément.

### 9. Erreur de carte « Unexpected token '<' … is not valid JSON »

- Ajout du `slug` manquant dans les données d’annonces géolocalisées.
- Réponses des endpoints cartographiques toujours produites en JSON contrôlé.
- Gestion des warnings, erreurs serveur et réponses HTML sans afficher leur contenu brut dans la carte.
- Protection CSRF des recherches cartographiques.
- Message utilisateur unique et lisible en cas d’indisponibilité.

Fichiers principaux :

- `src/Service/AnnonceGeolocationService.php`
- `src/Controller/Web/Annonce/AnnonceNearbyMapController.php`
- `src/Controller/Web/Job/JobNearbyMapController.php`
- `public/js/annonce/public-section.js`
- `public/js/job/nearby-search-map.js`

### 10. Lenteur de l’image d’arrière-plan au premier affichage

- Préchargement prioritaire de la première image du hero.
- Chargement différé des images suivantes après l’affichage initial.
- Images de fond statiques déplacées dans les feuilles CSS.

Fichiers principaux :

- `templates/accueil/index.html.twig`
- `public/css/home/home-kjd.css`
- `public/js/home/home-kjd.js`

### 11. Abonnement aux alertes offres depuis le footer

- Une adresse email valide peut désormais s’abonner même sans compte utilisateur.
- Lorsqu’un compte possède une profession, les alertes sont ciblées sur cette profession.
- Les abonnés généraux reçoivent les nouvelles offres sans être bloqués par une profession absente.
- Les réactivations et abonnements déjà actifs sont gérés proprement.

Fichiers principaux :

- `src/Controller/Web/NewsletterController.php`
- `src/Service/Newsletter/NewsletterService.php`
- `src/Repository/NewsletterSubscriberRepository.php`

## Séparation Twig / CSS / JavaScript

Les interfaces modifiées ne contiennent plus de blocs `<style>` ni de JavaScript inline. Leurs ressources ont été déplacées dans :

- `public/css/annonce`
- `public/css/home`
- `public/css/job`
- `public/css/profile`
- `public/css/report`
- `public/css/search`
- `public/css/settings`
- `public/js/annonce`
- `public/js/home`
- `public/js/job`
- `public/js/profile`
- `public/js/report`
- `public/js/search`

Le fichier `templates/base.html.twig` n’a reçu que les deux références globales de l’auto-suggestion et la correction du texte d’abonnement. Son code de chat intégré n’a pas été modifié.

## Vérifications réalisées

- Syntaxe PHP : **564 fichiers validés avec `php -l`**.
- Syntaxe JavaScript : tous les nouveaux fichiers validés avec `node --check`.
- Équilibrage des blocs Twig sur les 11 templates modifiés.
- Équilibrage des accolades CSS.
- Vérification de l’existence de tous les nouveaux assets versionnés.
- Test du normaliseur et du rejet des mots-clés numériques.
- Comparaison cryptographique des fichiers liés au chat : **78 fichiers inchangés**.

## Limite de validation

L’archive fournie contient uniquement `src`, `templates` et une partie des ressources. Elle ne contient pas `composer.json`, `vendor`, `config`, `bin/console`, les variables d’environnement ni la base de données. Les contrôles statiques ont donc été réalisés, mais un test fonctionnel complet avec la base et le serveur Symfony doit être exécuté après fusion dans le projet complet.
