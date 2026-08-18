<?php

namespace App\Service;

final class BriefTemplateCatalog
{
    /**
     * Retourne tous les modèles de brief disponibles.
     * Je vais enrichir cette liste sans toucher au controller.
     */
    public function all(): array
    {
        return [
            [
                'slug' => 'site-vitrine',
                'icon' => '🌐',
                'title' => 'Site vitrine / présentation',
                'category' => 'Web',
                'badge' => 'Rapide',
                'summary' => 'Présenter une activité, un service, un portfolio ou une entreprise.',
                'delay_days' => 10,
                'budget_min' => 100000,
                'budget_max' => 350000,
                'experience' => 'Junior / Confirmé',
                'job_title' => 'Création d’un site vitrine professionnel',
                'description' => <<<TXT
Je recherche un talent pour concevoir un site vitrine moderne et responsive.

Objectif :
- Présenter mon activité / entreprise
- Mettre en avant mes services
- Permettre aux clients de me contacter facilement

Fonctionnalités attendues :
- Page d’accueil moderne
- Pages services / à propos / contact
- Formulaire de contact
- Version mobile responsive
- Optimisation de base SEO
- Intégration WhatsApp (optionnel)

Merci d’indiquer :
- votre délai de réalisation
- vos références / projets similaires
- ce qui est inclus (hébergement, nom de domaine, maintenance, etc.)
TXT,
                'mission' => <<<TXT
- Concevoir l’interface du site vitrine
- Développer les pages principales
- Rendre le site responsive (mobile/tablette/desktop)
- Mettre en place le formulaire de contact
- Livrer un site propre, testable et prêt à publier
TXT,
                'profil' => <<<TXT
- Expérience en développement web (Symfony, Laravel, WordPress, ou stack équivalente)
- Sens du design / UI propre
- Bonne communication
- Respect des délais
TXT,
            ],

            [
                'slug' => 'api-symfony',
                'icon' => '🧩',
                'title' => 'API backend (Symfony / Laravel)',
                'category' => 'API',
                'badge' => 'Technique',
                'summary' => 'Développement d’API REST pour application web/mobile.',
                'delay_days' => 14,
                'budget_min' => 250000,
                'budget_max' => 800000,
                'experience' => 'Confirmé',
                'job_title' => 'Développement d’une API backend sécurisée',
                'description' => <<<TXT
Je recherche un développeur backend pour créer une API (REST) pour mon application.

Besoins :
- Authentification / autorisation
- CRUD sur les ressources principales
- Validation des données
- Réponses JSON propres
- Documentation API (Swagger/Postman si possible)

Technos souhaitées :
- Symfony (priorité) ou Laravel
- Base de données MySQL / PostgreSQL

Merci de préciser :
- ton stack
- ton approche sécurité
- ton délai estimé
- ton expérience sur des API similaires
TXT,
                'mission' => <<<TXT
- Concevoir l’architecture API
- Développer les endpoints principaux
- Implémenter auth + validation
- Tester les routes
- Documenter les endpoints
TXT,
                'profil' => <<<TXT
- Très bonne maîtrise PHP et framework backend (Symfony/Laravel)
- Expérience API REST
- Bonne maîtrise SQL et Doctrine/Eloquent
- Capacité à documenter le travail
TXT,
            ],

            [
                'slug' => 'app-mobile',
                'icon' => '📱',
                'title' => 'Application mobile',
                'category' => 'Mobile',
                'badge' => 'Produit',
                'summary' => 'Prototype ou MVP mobile Android/iOS (Flutter, React Native, etc.).',
                'delay_days' => 21,
                'budget_min' => 400000,
                'budget_max' => 1500000,
                'experience' => 'Confirmé',
                'job_title' => 'Développement d’une application mobile (MVP)',
                'description' => <<<TXT
Je cherche un développeur mobile pour réaliser un MVP d’application mobile.

Objectif :
- Une première version fonctionnelle pour test utilisateur

Fonctionnalités prévues (à affiner) :
- Connexion / inscription
- Profil utilisateur
- Liste d’éléments / recherche
- Détail d’un élément
- Notifications (optionnel)

Merci de proposer :
- techno (Flutter / React Native / natif)
- planning
- budget
- exemples d’applications déjà réalisées
TXT,
                'mission' => <<<TXT
- Cadrage technique du MVP
- Développement des écrans clés
- Connexion à l’API (si disponible)
- Tests de base et correctifs
- Livraison d’une version de démonstration
TXT,
                'profil' => <<<TXT
- Expérience mobile (Flutter/React Native/Android/iOS)
- Sens de l’UX mobile
- Bonne rigueur sur la qualité du code
- Capacité à livrer un MVP rapidement
TXT,
            ],

            [
                'slug' => 'ui-ux-design',
                'icon' => '🎨',
                'title' => 'UI/UX Design',
                'category' => 'Design',
                'badge' => 'Créatif',
                'summary' => 'Wireframes, maquettes, parcours utilisateur, design system léger.',
                'delay_days' => 7,
                'budget_min' => 120000,
                'budget_max' => 500000,
                'experience' => 'Junior / Confirmé',
                'job_title' => 'Conception UI/UX pour application web/mobile',
                'description' => <<<TXT
Je recherche un designer UI/UX pour améliorer l’expérience utilisateur de mon produit.

Attendus :
- Analyse rapide du besoin
- Wireframes
- Maquettes UI modernes (desktop + mobile)
- Parcours utilisateur simplifié
- Cohérence visuelle (couleurs, composants, boutons, formulaires)

Merci de partager :
- portfolio
- outils utilisés (Figma, etc.)
- délai et budget
TXT,
                'mission' => <<<TXT
- Proposer une structure UX claire
- Réaliser wireframes et maquettes
- Définir composants UI réutilisables
- Livrer les maquettes Figma prêtes pour intégration
TXT,
                'profil' => <<<TXT
- Portfolio UI/UX solide
- Maîtrise Figma
- Sens de la hiérarchie visuelle
- Capacité à collaborer avec développeur
TXT,
            ],

            [
                'slug' => 'maintenance-bugfix',
                'icon' => '🛠️',
                'title' => 'Maintenance / corrections',
                'category' => 'Support',
                'badge' => 'Urgent',
                'summary' => 'Corriger bugs, stabiliser une application existante, améliorer performance.',
                'delay_days' => 5,
                'budget_min' => 50000,
                'budget_max' => 300000,
                'experience' => 'Confirmé',
                'job_title' => 'Correction de bugs et maintenance applicative',
                'description' => <<<TXT
Je cherche un développeur pour corriger des bugs sur une application existante.

Objectif :
- Corriger les erreurs bloquantes
- Stabiliser les fonctionnalités existantes
- Éventuellement améliorer les performances

Merci d’indiquer :
- ta disponibilité immédiate
- ton expérience en debugging
- ton mode d’intervention (audit + correctifs)
TXT,
                'mission' => <<<TXT
- Analyser les bugs signalés
- Identifier les causes
- Corriger et tester
- Faire un compte rendu des modifications
TXT,
                'profil' => <<<TXT
- Très bon niveau en debugging
- Lecture rapide de code existant
- Rigueur de test
- Communication claire sur les correctifs
TXT,
            ],

            [
                'slug' => 'community-manager',
                'icon' => '📣',
                'title' => 'Community management / contenu',
                'category' => 'Communication',
                'badge' => 'Marketing',
                'summary' => 'Gestion réseaux sociaux, calendrier éditorial, visuels et publications.',
                'delay_days' => 15,
                'budget_min' => 80000,
                'budget_max' => 400000,
                'experience' => 'Junior / Confirmé',
                'job_title' => 'Community manager pour gestion de contenus et réseaux sociaux',
                'description' => <<<TXT
Je recherche un community manager pour m’aider à gérer mes réseaux sociaux.

Besoins :
- planning de publications
- création / adaptation de contenus
- suivi des interactions
- reporting simple

Merci de préciser :
- tes plateformes de maîtrise (Facebook, Instagram, TikTok, LinkedIn…)
- ton style
- tes résultats / références
TXT,
                'mission' => <<<TXT
- Élaborer un mini calendrier éditorial
- Créer / proposer des contenus
- Publier et suivre les interactions
- Produire un reporting simple
TXT,
                'profil' => <<<TXT
- Bonne capacité rédactionnelle
- Créativité visuelle / contenu
- Sens marketing
- Organisation et régularité
TXT,
            ],
        ];
    }

    public function findBySlug(string $slug): ?array
    {
        foreach ($this->all() as $template) {
            if (($template['slug'] ?? null) === $slug) {
                return $template;
            }
        }

        return null;
    }
}