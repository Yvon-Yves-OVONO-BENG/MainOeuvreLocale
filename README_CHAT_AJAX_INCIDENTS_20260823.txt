MAIN D'ŒUVRE LOCALE — CHAT AJAX + INCIDENTS TECHNIQUES
Correctif du 23/08/2026
======================================================

OBJECTIF
--------
Ce correctif modernise uniquement la messagerie de Main d’Œuvre Locale et
ajoute la gestion des incidents techniques. Les autres modules du projet ne
sont pas volontairement refondus.

1) CHAT PRIVÉ — AJAX SANS MERCURE
---------------------------------
- Envoi des messages par AJAX.
- Synchronisation automatique des conversations/messages par polling AJAX.
- Lecture/réception synchronisées (envoyé, reçu, lu).
- Indicateur « écrit... » synchronisé par AJAX.
- Recherche, réponses, réactions, suppression et actions de conversation
  conservées.
- Aucun EventSource/Mercure n’est utilisé par le chat actif.
- Mercure n’a PAS été supprimé globalement du projet, car d’autres modules
  peuvent encore l’utiliser.

2) PHOTOS, FICHIERS ET VOCAUX
-----------------------------
- Envoi de photos et de fichiers jusqu’à 25 Mo.
- Compression/optimisation existante conservée via UploadOptimizer.
- Blocage des extensions exécutables dangereuses (.php, .exe, .bat, etc.).
- Capture caméra navigateur avec aperçu, fermeture et changement avant/arrière.
- Repli vers le sélecteur/caméra natif si getUserMedia n’est pas disponible.
- Enregistrement et envoi de messages vocaux via MediaRecorder.
- Le panneau d’emojis du composeur reste ouvert pour sélectionner plusieurs
  emojis avant l’envoi.

3) APPELS AUDIO / VIDÉO
-----------------------
- Interface d’appel moderne existante conservée et branchée sur WebRTC.
- Signalisation offer/answer/ICE enregistrée en base et échangée par AJAX.
- Appel entrant, accepter, refuser, raccrocher et état de l’appel par polling.
- Audio, vidéo, micro, caméra on/off, changement de caméra, plein écran et PiP.
- HTTPS est obligatoire pour la caméra et le microphone sur les navigateurs.
- STUN public configuré. Pour une fiabilité maximale entre réseaux mobiles/NAT
  stricts, un serveur TURN peut être ajouté ultérieurement sans revenir à
  Mercure.

4) PARAMÈTRES DU CHAT
---------------------
- Nouvelle vraie page utilisateur : /chat/parametres
- Notifications du chat activables/désactivables.
- 10 sons de messages + mode silencieux, enregistrés en base.
- Réglages de blocage existants accessibles depuis la page.
- Informations de compatibilité appels WebRTC.
- L’ancien écran d’administration du chatbot est conservé sous /admin/chat.

5) INCIDENTS TECHNIQUES / PAGES D’ERREUR
----------------------------------------
- Les exceptions HTTP/Symfony sont interceptées avant la page rouge Symfony.
- Une page Main d’Œuvre Locale moderne et responsive est affichée à l’utilisateur.
- Une référence unique MOL-YYYYMMDD-HHMMSS-XXXXXXXX est affichée.
- Pour les requêtes AJAX/API, une réponse JSON propre est renvoyée avec la
  même référence.
- L’incident est enregistré en base : statut HTTP, classe d’exception, message,
  route, URI, méthode, fichier/ligne, trace, contexte sécurisé, IP, navigateur,
  référent, utilisateur, dates et nombre d’occurrences.
- Les incidents identiques sur 30 minutes sont regroupés et leur compteur est
  incrémenté afin de limiter la croissance de la table.

6) ADMINISTRATION DES INCIDENTS
-------------------------------
- Nouveau lien « Incidents techniques » dans le panel gauche administrateur.
- Liste avec recherche, référence, statut, sévérité, HTTP, requête, compte/IP,
  nombre d’occurrences et date.
- Page « Détails » avec trace et contexte technique.
- Action « Résoudre ».
- Suppression individuelle.
- Purge complète de la table avec confirmation.

INSTALLATION EN PRODUCTION
--------------------------
IMPORTANT : exécuter les étapes dans cet ordre.

1. Sauvegarder la base de données et les fichiers actuellement en production.

2. Remplacer les dossiers src/ et templates/ par ceux de cette archive
   (ou déployer uniquement les fichiers modifiés si vous utilisez Git).

3. Dans phpMyAdmin, importer UNE SEULE FOIS le fichier :
   src/MIGRATION_CHAT_AJAX_INCIDENTS_20260823.sql

   Cette migration utilise CREATE TABLE IF NOT EXISTS et crée :
   - incident
   - call_signal
   - chat_typing_state
   - message_sound_preference
   - notification_preference

4. Nettoyer le cache Symfony :
   php bin/console cache:clear --env=prod

5. Vérifier que les dossiers suivants sont accessibles en écriture par PHP :
   public/uploads/chat/files
   public/uploads/chat/voices
   public/uploads/chat/groups

6. Tester impérativement en HTTPS avec deux comptes différents :
   - texte envoyé/reçu/lu sans rechargement de page ;
   - photo et document ;
   - vocal ;
   - appel audio ;
   - appel vidéo + caméra avant/arrière ;
   - paramètres du chat et sons ;
   - une URL inexistante pour vérifier la page d’incident ;
   - panneau Admin > Incidents techniques.

VALIDATIONS EFFECTUÉES SUR LE CODE LIVRÉ
----------------------------------------
- 581 fichiers PHP de src/ validés avec php -l : aucune erreur de syntaxe.
- JavaScript principal du chat, paramètres et sons vérifiés avec node --check
  après neutralisation des expressions Twig : aucune erreur de syntaxe JS.
- Recherche statique : aucun EventSource / appel Mercure actif dans les
  contrôleurs/services/templates du chat ciblés.

LIMITATION DU ZIP REÇU
----------------------
L’archive fournie contenait src/ et templates/ mais pas vendor/, public/ complet,
.env ni bin/console. Il n’a donc pas été possible de démarrer le Kernel Symfony
ou d’exécuter un test HTTP intégral dans cet environnement. Les validations
ci-dessus sont des validations statiques et syntaxiques. Après déploiement,
exécuter la checklist de production indiquée plus haut.
