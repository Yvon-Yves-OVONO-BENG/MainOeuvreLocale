# Compléments du cahier des charges

Cette livraison ne modifie aucun fichier du module de paiement.

1. Exécuter `src/MIGRATION_CDC_COMPLETION_20260824.sql`.
2. Vider le cache Symfony en production.
3. Ouvrir `/admin/advertising` avec un compte administrateur pour créer et
   activer les campagnes publicitaires.
4. Les évaluations privées se trouvent sur la page des candidatures reçues.
5. Les autorisations documentaires sont gérées depuis `/documents/access`.

## Chiffrement documentaire

Le composant `SensitiveDocumentCipher` est ajouté sans transformer les anciens
fichiers, afin de ne pas les rendre illisibles. Avant une migration chiffrée,
définir une clé de 32 octets encodée en Base64 dans
`MOL_DOCUMENT_ENCRYPTION_KEY`, sauvegarder les documents, puis planifier leur
migration contrôlée. L’accès consenti et audité fonctionne indépendamment.
