# Endpoints du chat premium

## Conversations privées

| Route name | Méthode | URL | Rôle |
|---|---:|---|---|
| `chat_conversations` | GET | `/chat/conversations` | Liste des conversations privées |
| `chat_with_user` | POST | `/chat/conversation/with/{userId}` | Ouvrir/créer une conversation privée |
| `chat_messages` | GET | `/chat/conversation/{id}/messages` | Messages d’une conversation |
| `chat_send` | POST | `/chat/conversation/{id}/send` | Envoyer un message texte |
| `chat_conversation_send_file` | POST | `/chat/conversation/{id}/send-file` | Envoyer une pièce jointe |
| `chat_conversation_send_voice` | POST | `/chat/conversation/{id}/send-voice` | Envoyer un vocal |
| `chat_mark_read` | POST | `/chat/conversation/{id}/read` | Marquer comme lu |
| `chat_mark_delivered` | POST | `/chat/conversation/{id}/delivered` | Marquer comme livré |
| `chat_typing` | POST | `/chat/conversations/{id}/typing` | Typing indicator |
| `message_reaction_toggle` | POST | `/message-reaction/{id}/toggle` | Réaction emoji |
| `chat_message_delete` | POST/DELETE | `/chat/message/{id}/delete` | Supprimer un message |
| `chat_search_conversation_messages` | GET | `/chat/conversation/{id}/search?q=` | Recherche dans une conversation |
| `chat_search_messages` | GET | `/chat/search/messages?q=` | Recherche globale |
| `chat_unread_count` | GET | `/chat/meta/unread` | Compteur global non lus |

## Actions conversation

| Route name | Méthode | URL |
|---|---:|---|
| `chat_delete_conversation` | POST/DELETE | `/chat/conversation/{id}/delete` |
| `chat_mark_unread` | POST | `/chat/conversation/{id}/mark-unread` |
| `chat_mute` | POST | `/chat/conversation/{id}/mute` |
| `chat_archive` | POST | `/chat/conversation/{id}/archive` |
| `chat_report` | POST | `/chat/conversation/{id}/report` |

## Groupes

Ces endpoints existent déjà dans ton projet et sont consommés par le JS :

| Route name | Méthode | URL |
|---|---:|---|
| `chat_group_mine` | GET | `/chat/groups/mine` |
| `chat_group_discover` | GET | `/chat/groups/discover` |
| `chat_group_messages` | GET | `/chat/groups/{id}/messages` |
| `chat_group_send` | POST | `/chat/groups/{id}/send` |
| `chat_group_send_file` | POST | `/chat/groups/{id}/send-file` |
| `chat_group_delete_message` | POST | `/chat/groups/messages/{id}/delete` |
| `chat_group_toggle_reaction` | POST | `/chat/groups/messages/{id}/reaction/toggle` |
| `chat_group_search_messages` | GET | `/chat/groups/{id}/search?q=` |

## Présence

| Route name | Méthode | URL |
|---|---:|---|
| `presence_heartbeat` | POST | `/presence/heartbeat` |
| `presence_offline` | POST | `/presence/offline` |
| `presence_user_status` | GET | `/presence/user/{id}` |

## Appels

| Route name | Méthode | URL |
|---|---:|---|
| `chat_call_start` | POST | `/chat/conversation/{id}/call/start` |
| `chat_call_accept` | POST | `/chat/call/{id}/accept` |
| `chat_call_decline` | POST | `/chat/call/{id}/decline` |
| `chat_call_signal` | POST | `/chat/call/{id}/signal` |
| `chat_call_end` | POST | `/chat/call/{id}/end` |
