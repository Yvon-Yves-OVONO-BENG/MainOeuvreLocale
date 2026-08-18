# Installation des URL hashées

Après avoir remplacé les fichiers, exécutez à la racine du projet :

```bash
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console cache:clear
```

La migration :

- ajoute `annonce.slug` ;
- remplit les annonces déjà enregistrées ;
- transforme une seule fois les anciens slugs de jobs, catégories et professions en hash SHA-256 ;
- ajoute une contrainte d'unicité sur les quatre champs `slug` concernés.

Les identifiants numériques restent en base comme clés primaires Doctrine, mais ne sont plus utilisés dans les URL des offres, annonces, catégories et professions.
