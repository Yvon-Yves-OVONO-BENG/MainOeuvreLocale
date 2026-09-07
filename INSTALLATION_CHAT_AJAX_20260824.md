# Chat AJAX — Main d’Œuvre Locale

Cette livraison utilise uniquement des requêtes AJAX pour la messagerie, les
accusés de réception, l’indicateur de saisie et la signalisation WebRTC des
appels audio/vidéo. Elle ne nécessite aucun serveur temps réel séparé.

## Mise en production

1. Remplacer les dossiers `src/` et `templates/` concernés en conservant leur
   arborescence.
2. Exécuter une seule fois `src/MIGRATION_CHAT_AJAX_INCIDENTS_20260823.sql` sur
   la base de données de production. Le script est relançable.
3. Créer si nécessaire les dossiers suivants et autoriser PHP à y écrire :
   `public/uploads/chat/files` et `public/uploads/chat/voices`.
4. Vider le cache Symfony : `php bin/console cache:clear --env=prod`.

## Vérifications rapides

- ouvrir la même conversation dans deux navigateurs ou deux téléphones ;
- envoyer un texte, une image, un document et un message vocal ;
- vérifier les états envoyé, livré et lu ;
- lancer un appel audio puis vidéo et autoriser caméra/micro ;
- vérifier le fonctionnement en HTTPS, obligatoire pour WebRTC sur mobile.

Les fichiers CSS du projet n’ont pas été modifiés.
