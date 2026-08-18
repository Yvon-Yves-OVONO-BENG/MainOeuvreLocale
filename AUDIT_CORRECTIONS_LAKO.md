# Correctifs appliqués — Rapport Lako

Ce paquet repart du ZIP original et applique les recommandations techniques du rapport d'audit.

## Corrigé dans le code

- Aucun e-mail n'est utilisé comme nom public de repli : `Profil sans nom` est utilisé sur les listes, annonces, pages de réputation et avis publics.
- La recherche publique ne recherche plus les comptes par adresse e-mail.
- `/review/user/{...}` utilise le slug opaque de l'utilisateur, exige `ROLE_USER`, est limité à 30 requêtes/minute par session/IP et renvoie des réponses `no-store`.
- Le portfolio public utilise le slug opaque de l'utilisateur au lieu d'un ID séquentiel.
- La création d'avis utilise également le slug opaque.
- L'endpoint de révélation des coordonnées utilise le slug opaque et conserve les contrôles d'abonnement/authentification. Les coordonnées ne sont plus embarquées en clair dans les boutons publics concernés.
- Les boutons Pro/Premium ont désormais une vraie URL de secours vers `/abonnement/choisir/{slug}` ; le JavaScript ne dépend plus d'une URL codée en dur à la racine.
- Les balises `<img src="">` actives ont été supprimées ; les images dynamiques reçoivent leur `src` uniquement quand nécessaire.
- Le faux numéro `+237 6XX XXX XXX` n'est plus affiché publiquement.
- Le drapeau n'utilise plus l'avatar comme image de secours ; une icône neutre est affichée si aucun drapeau n'existe.
- Les nouvelles références d'offres sont aléatoires (`JOB-AAAA-XXXXXXXXXX`) et ne révèlent plus le compteur réel.
- Les pages rendues pendant une session authentifiée reçoivent `Cache-Control: private, no-store...`, ce qui empêche le retour navigateur d'afficher une ancienne page privée après déconnexion.
- Les endpoints de présence dupliqués sont désormais réservés aux utilisateurs connectés. Les API de listes de réputation exigent également une authentification et ne sérialisent plus directement les entités `User` (aucun email/téléphone/mot de passe dans leur payload).
- Le bouton « Contactez-nous » d'une offre mène vers la vraie page de contact au lieu d'une modale inexistante.
- Ajout de lazy-loading/décodage asynchrone sur les images de listes ; logos prioritaires ; `UploadOptimizer` est maintenant aussi utilisé pour les nouvelles photos de professions afin de réduire les images lourdes en WebP quand c'est pertinent.
- Validation du champ photo de profession côté client + serveur, avec message visible, formats autorisés et limite 2 Mo.
- CSS critique des logos chargé dans le `<head>` pour supprimer leur chevauchement au premier rendu.

## À faire après mise en production

1. Dans Google Search Console, demander la réindexation/suppression des anciennes URL qui ont pu contenir des e-mails dans les snippets.
2. Purger le cache/CDN éventuel après déploiement.
3. Tester le parcours d'abonnement avec un compte réel jusqu'à la confirmation serveur du prestataire de paiement.
4. Vérifier les logs serveur après déploiement (HTTP 401/403/429, erreurs d'images, erreurs de paiement).
5. Si un numéro de support officiel est décidé, l'ajouter ensuite dans la page Contact et les en-têtes de facture ; aucun numéro factice n'est inventé dans ce correctif.
6. Les anciennes références `JOB-AAAA-0000` déjà stockées en base ne sont pas réécrites automatiquement : seules les nouvelles références sont désormais aléatoires. Une migration peut être faite séparément si l'historique doit aussi être anonymisé.
