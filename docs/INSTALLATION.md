# Installation du chat premium SaaS UI/UX

## 1. Copier les fichiers

Copie les dossiers du pack dans ton projet Symfony :

```bash
cp -R src/* /chemin/vers/ton-projet/src/
cp -R templates/* /chemin/vers/ton-projet/templates/
cp -R public/* /chemin/vers/ton-projet/public/
```

Le pack ajoute surtout des fichiers nouveaux. Il ne remplace pas tes contrôleurs existants de base : il ajoute les endpoints manquants pour suppression, archive, recherche, présence alias, compteur non lu et sécurité.

## 2. Inclure l’UI dans `base.html.twig`

Juste avant `</body>` :

```twig
{% include 'chat/_include.html.twig' %}
```

Vérifie que Bootstrap Icons est chargé, car l’interface utilise `bi bi-*`.

## 3. Vérifier les routes

```bash
php bin/console debug:router | grep -E "chat_|presence_"
```

## 4. Vérifier le code PHP

```bash
find src -name '*.php' -print0 | xargs -0 -n1 php -l
```

## 5. Mercure temps réel

Le chat fonctionne en quasi temps réel sans Mercure grâce au polling. Pour le vrai temps réel, configure :

```env
MERCURE_URL="http://localhost:3000/.well-known/mercure"
MERCURE_PUBLIC_URL="http://localhost:3000/.well-known/mercure"
MERCURE_JWT_SECRET="change-me"
```

Le JS écoute plusieurs topics pour rester compatible avec ton code actuel :

```txt
/conversations/{id}/messages
/conversations/{id}/status
/conversations/{id}/typing
chat/conversation/{id}
chat/conversation/{id}/typing
/groups/{id}/messages
chat/group/{id}
/chat/user/{id}/incoming-call
/chat/call/{id}
```

## 6. Uploads

Tes contrôleurs actuels stockent les fichiers ici :

```txt
public/uploads/chat/files
public/uploads/chat/voices
public/uploads/chat/groups
```

Crée-les si nécessaire :

```bash
mkdir -p public/uploads/chat/files public/uploads/chat/voices public/uploads/chat/groups
chmod -R 775 public/uploads/chat
```

## 7. Ce que le pack apporte

- UI premium responsive
- panneau conversations privées/groupes/recherche
- messages texte, fichiers, vocaux, images, vidéos
- réactions emoji
- réponse à un message
- suppression message
- archive/sourdine/signalement conversation
- recherche globale et par conversation
- présence en ligne
- typing indicator
- notifications UI et compteur non lus
- Mercure + fallback polling
- appels audio/vidéo WebRTC compatibles avec ton `CallController`
- voters de sécurité pour conversations et groupes
