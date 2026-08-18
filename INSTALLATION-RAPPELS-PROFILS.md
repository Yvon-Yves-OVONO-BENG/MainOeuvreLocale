# Relances automatiques des profils incomplets

Le module filtre les profils publics incomplets et relance les comptes concernés par chat interne et par e-mail.

## Comportement par défaut

- première relance : 24 heures après l'inscription ;
- nouvelle relance : au maximum une fois tous les 7 jours ;
- taille d'un lot : 200 comptes ;
- aucun profil complet n'est relancé ;
- aucune migration n'est nécessaire : la notification existante mémorise la dernière relance.

## Vérification sans envoi

```bash
php bin/console app:profiles:send-completion-reminders --dry-run
```

## Exécution manuelle

```bash
php bin/console app:profiles:send-completion-reminders --after-hours=24 --cooldown-days=7 --limit=200
```

## Automatisation CRON conseillée

Exécuter la commande chaque heure. Remplacer `/chemin/du/projet` par le chemin réel du projet déployé.

```cron
0 * * * * cd /chemin/du/projet && /usr/bin/php bin/console app:profiles:send-completion-reminders --after-hours=24 --cooldown-days=7 --limit=200 >> var/log/profile-reminders.log 2>&1
```

## Prérequis

1. `MAILER_DSN` doit être correctement configuré.
2. Au moins un compte actif `ROLE_ADMIN` ou `ROLE_SUPER_ADMIN` doit exister pour servir d'expéditeur dans le chat.
3. L'URL principale doit être configurée pour que le lien d'édition envoyé depuis la console soit absolu :

```yaml
# config/packages/framework.yaml
framework:
    router:
        default_uri: 'https://maindoeuvrelocale.com'
```

Les délais restent modifiables via les options de la commande sans changer le code.
