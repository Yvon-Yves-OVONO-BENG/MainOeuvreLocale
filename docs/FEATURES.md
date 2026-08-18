# Fonctionnalités livrées

## Temps réel ou quasi temps réel

- Mercure si `MERCURE_PUBLIC_URL` est défini.
- Fallback polling automatique toutes les 9 secondes sans Mercure.

## Conversations privées

- Liste, ouverture, envoi, lecture, pièces jointes, vocaux, réactions, réponses.
- Actions : supprimer, archiver, sourdine, marquer non lu, signaler.

## Groupes

- Liste des groupes de l’utilisateur.
- Messages de groupe.
- Envoi texte/fichier.
- Suppression/réaction si tes endpoints existants sont actifs.
- Respect des membres et admins via `ChatGroupVoter`.

## Pièces jointes

- Images avec preview.
- Vidéos avec lecteur.
- Audio/vocaux avec lecteur.
- Fichiers avec carte de téléchargement.

## Messages lus / non lus

- `chat_mark_read`
- `chat_mark_delivered`
- `chat_unread_count`
- badge rouge global sur le bouton flottant.

## Statut en ligne

- `presence_heartbeat`
- `presence_offline`
- `presence_user_status`
- Réutilise les champs `User::lastSeenAt`, `User::lastDisconnectedAt`, `User::getPresenceStatus()` de ton projet.

## Typing indicator

- Envoi `typing: true/false`.
- Affichage des trois points animés en temps réel via Mercure.

## Notifications

- Toast interne premium.
- Badge non lu.
- Compatible Notification API du navigateur si permission accordée.

## Sécurité

- Voters `ConversationVoter` et `ChatGroupVoter`.
- Guard service `ChatAccessGuard`.
- Les endpoints vérifient systématiquement la participation.

## Rôles

Le resolver affiche les rôles :

- Admin
- Entreprise
- Talent
- Utilisateur

Il se base sur `ROLE_ADMIN`, `ROLE_SUPER_ADMIN`, `ROLE_COMPANY`, `ROLE_ENTREPRISE`, `ROLE_PROFESSIONAL`, `ROLE_TALENT`.
