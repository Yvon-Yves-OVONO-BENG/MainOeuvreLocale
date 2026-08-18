<?php

namespace App\DataFixtures;

use App\Entity\Categorie;
use App\Entity\Job;
use App\Entity\Profession;
use App\Entity\StatusJob;
use App\Entity\TypeJob;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class LocalSmallJobsFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $now = new \DateTime();

        /*
         * Utilisateurs créateurs des annonces
         */
        $createdByIds = [12, 13, 22, 23];

        $users = [];
        foreach ($createdByIds as $id) {
            $user = $manager->getRepository(User::class)->find($id);

            if ($user) {
                $users[] = $user;
            }
        }

        if (empty($users)) {
            throw new \RuntimeException('Aucun utilisateur trouvé avec les IDs : 12, 13, 22, 23');
        }

        /*
         * Ces valeurs sont optionnelles.
         * Si tu as déjà des StatusJob et TypeJob en base,
         * la fixture prendra simplement le premier élément trouvé.
         */
        $statusJob = $this->findFirst($manager, StatusJob::class);
        $typeJob = $this->findFirst($manager, TypeJob::class);

        $data = [
            [
                'nom' => 'Transport & Livraison',
                'description' => 'Chauffeurs, livreurs, coursiers et petits services de déplacement.',
                'icon' => 'fa-solid fa-car',
                'professions' => [
                    'Chauffeur personnel',
                    'Chauffeur occasionnel',
                    'Livreur moto',
                    'Coursier',
                    'Aide déménagement',
                ],
            ],
            [
                'nom' => 'Maison & Entretien',
                'description' => 'Services de ménage, repassage, lavage, entretien et aide à domicile.',
                'icon' => 'fa-solid fa-broom',
                'professions' => [
                    'Ménagère',
                    'Blanchisseur',
                    'Repassage à domicile',
                    'Aide ménagère',
                    'Nettoyeur après travaux',
                ],
            ],
            [
                'nom' => 'Travaux & Dépannage',
                'description' => 'Petits travaux, réparation, installation et dépannage à domicile.',
                'icon' => 'fa-solid fa-screwdriver-wrench',
                'professions' => [
                    'Électricien',
                    'Plombier',
                    'Peintre bâtiment',
                    'Carreleur',
                    'Menuisier',
                    'Réparateur climatisation',
                    'Serrurier',
                ],
            ],
            [
                'nom' => 'Événementiel',
                'description' => 'Services pour mariages, anniversaires, cérémonies et événements privés.',
                'icon' => 'fa-solid fa-champagne-glasses',
                'professions' => [
                    'DJ',
                    'Décoratrice',
                    'Photographe',
                    'Serveur événementiel',
                    'Animateur',
                    'Maître de cérémonie',
                ],
            ],
            [
                'nom' => 'Cuisine & Restauration',
                'description' => 'Petits services de cuisine, traiteur, service et préparation de repas.',
                'icon' => 'fa-solid fa-utensils',
                'professions' => [
                    'Cuisinier à domicile',
                    'Traiteur',
                    'Serveuse',
                    'Aide cuisine',
                    'Grilleur',
                ],
            ],
            [
                'nom' => 'Beauté & Bien-être',
                'description' => 'Coiffure, maquillage, soins simples et services beauté à domicile.',
                'icon' => 'fa-solid fa-scissors',
                'professions' => [
                    'Coiffeuse',
                    'Barbier',
                    'Maquilleuse',
                    'Manucure',
                    'Masseuse',
                ],
            ],
            [
                'nom' => 'Jardinage & Extérieur',
                'description' => 'Entretien de cour, jardinage, débroussaillage et petits travaux extérieurs.',
                'icon' => 'fa-solid fa-seedling',
                'professions' => [
                    'Jardinier',
                    'Nettoyeur de cour',
                    'Débroussailleur',
                    'Pisciniste',
                ],
            ],
            [
                'nom' => 'Sécurité & Assistance',
                'description' => 'Gardiennage, surveillance, assistance personnelle et petits services de confiance.',
                'icon' => 'fa-solid fa-shield-halved',
                'professions' => [
                    'Gardien',
                    'Agent de sécurité',
                    'Nounou',
                    'Aide aux personnes âgées',
                    'Veilleur de nuit',
                ],
            ],
            [
                'nom' => 'Services numériques simples',
                'description' => 'Aide informatique, saisie, réparation téléphone et services digitaux accessibles.',
                'icon' => 'fa-solid fa-laptop',
                'professions' => [
                    'Réparateur téléphone',
                    'Réparateur ordinateur',
                    'Saisie de documents',
                    'Assistant bureautique',
                    'Photocopieur',
                ],
            ],
            [
                'nom' => 'Commerce & Courses',
                'description' => 'Petits services de vente, courses, assistance boutique et achats quotidiens.',
                'icon' => 'fa-solid fa-basket-shopping',
                'professions' => [
                    'Vendeur temporaire',
                    'Aide boutique',
                    'Faire les courses',
                    'Distributeur de flyers',
                    'Caissière temporaire',
                ],
            ],
        ];

        $professionObjects = [];

        foreach ($data as $categoryData) {
            $categorie = new Categorie();
            $categorie->setNom($categoryData['nom']);
            $categorie->setDescription($categoryData['description']);
            $categorie->setSlug(\App\Util\HashedSlugGenerator::generate());
            $categorie->setIcon($categoryData['icon']);
            $categorie->setIsActive(true);
            $categorie->setCreatedAt(clone $now);

            $manager->persist($categorie);

            foreach ($categoryData['professions'] as $professionName) {
                $profession = new Profession();
                $profession->setProfession($professionName);
                $profession->setSlug(\App\Util\HashedSlugGenerator::generate());
                $profession->setCategorie($categorie);

                $manager->persist($profession);

                $professionObjects[$professionName] = $profession;
            }
        }

        $jobs = [
            [
                'profession' => 'Chauffeur personnel',
                'title' => 'Besoin d’un chauffeur pour déplacements en ville',
                'city' => 'Douala',
                'min' => 10000,
                'max' => 25000,
            ],
            [
                'profession' => 'Chauffeur occasionnel',
                'title' => 'Chauffeur disponible pour une journée',
                'city' => 'Yaoundé',
                'min' => 15000,
                'max' => 30000,
            ],
            [
                'profession' => 'Livreur moto',
                'title' => 'Livraison de colis en moto',
                'city' => 'Douala',
                'min' => 5000,
                'max' => 12000,
            ],
            [
                'profession' => 'Coursier',
                'title' => 'Recherche coursier pour documents urgents',
                'city' => 'Bafoussam',
                'min' => 5000,
                'max' => 10000,
            ],
            [
                'profession' => 'Ménagère',
                'title' => 'Besoin d’une ménagère pour entretien maison',
                'city' => 'Douala',
                'min' => 8000,
                'max' => 20000,
            ],
            [
                'profession' => 'Blanchisseur',
                'title' => 'Recherche blanchisseur pour lavage et repassage',
                'city' => 'Yaoundé',
                'min' => 7000,
                'max' => 18000,
            ],
            [
                'profession' => 'Repassage à domicile',
                'title' => 'Repassage de vêtements à domicile',
                'city' => 'Douala',
                'min' => 5000,
                'max' => 15000,
            ],
            [
                'profession' => 'Nettoyeur après travaux',
                'title' => 'Nettoyage complet après petits travaux',
                'city' => 'Kribi',
                'min' => 15000,
                'max' => 40000,
            ],
            [
                'profession' => 'Électricien',
                'title' => 'Besoin d’un électricien pour dépannage maison',
                'city' => 'Douala',
                'min' => 10000,
                'max' => 35000,
            ],
            [
                'profession' => 'Plombier',
                'title' => 'Réparation fuite d’eau à domicile',
                'city' => 'Yaoundé',
                'min' => 10000,
                'max' => 30000,
            ],
            [
                'profession' => 'Peintre bâtiment',
                'title' => 'Peinture d’une chambre et d’un salon',
                'city' => 'Douala',
                'min' => 25000,
                'max' => 80000,
            ],
            [
                'profession' => 'Carreleur',
                'title' => 'Pose de carreaux pour petite terrasse',
                'city' => 'Bafoussam',
                'min' => 30000,
                'max' => 100000,
            ],
            [
                'profession' => 'Menuisier',
                'title' => 'Réparation de porte et fabrication petite étagère',
                'city' => 'Douala',
                'min' => 15000,
                'max' => 60000,
            ],
            [
                'profession' => 'Réparateur climatisation',
                'title' => 'Entretien et dépannage climatiseur',
                'city' => 'Yaoundé',
                'min' => 15000,
                'max' => 45000,
            ],
            [
                'profession' => 'DJ',
                'title' => 'Recherche DJ pour anniversaire',
                'city' => 'Douala',
                'min' => 30000,
                'max' => 120000,
            ],
            [
                'profession' => 'Décoratrice',
                'title' => 'Décoration simple pour cérémonie familiale',
                'city' => 'Yaoundé',
                'min' => 25000,
                'max' => 100000,
            ],
            [
                'profession' => 'Photographe',
                'title' => 'Photographe pour événement privé',
                'city' => 'Douala',
                'min' => 20000,
                'max' => 75000,
            ],
            [
                'profession' => 'Serveur événementiel',
                'title' => 'Serveurs recherchés pour réception',
                'city' => 'Kribi',
                'min' => 8000,
                'max' => 20000,
            ],
            [
                'profession' => 'Cuisinier à domicile',
                'title' => 'Cuisinier pour préparation repas familial',
                'city' => 'Douala',
                'min' => 15000,
                'max' => 50000,
            ],
            [
                'profession' => 'Traiteur',
                'title' => 'Petit service traiteur pour réunion',
                'city' => 'Yaoundé',
                'min' => 30000,
                'max' => 150000,
            ],
            [
                'profession' => 'Aide cuisine',
                'title' => 'Aide cuisine pour une journée',
                'city' => 'Douala',
                'min' => 7000,
                'max' => 15000,
            ],
            [
                'profession' => 'Grilleur',
                'title' => 'Recherche grilleur pour soirée barbecue',
                'city' => 'Bafoussam',
                'min' => 12000,
                'max' => 35000,
            ],
            [
                'profession' => 'Coiffeuse',
                'title' => 'Coiffeuse à domicile pour tresses',
                'city' => 'Douala',
                'min' => 10000,
                'max' => 35000,
            ],
            [
                'profession' => 'Barbier',
                'title' => 'Barbier demandé pour service à domicile',
                'city' => 'Yaoundé',
                'min' => 5000,
                'max' => 15000,
            ],
            [
                'profession' => 'Maquilleuse',
                'title' => 'Maquilleuse pour cérémonie',
                'city' => 'Douala',
                'min' => 15000,
                'max' => 60000,
            ],
            [
                'profession' => 'Manucure',
                'title' => 'Service manucure à domicile',
                'city' => 'Yaoundé',
                'min' => 7000,
                'max' => 20000,
            ],
            [
                'profession' => 'Jardinier',
                'title' => 'Entretien jardin et arrosage',
                'city' => 'Douala',
                'min' => 10000,
                'max' => 30000,
            ],
            [
                'profession' => 'Nettoyeur de cour',
                'title' => 'Nettoyage cour et devanture maison',
                'city' => 'Bafoussam',
                'min' => 8000,
                'max' => 25000,
            ],
            [
                'profession' => 'Débroussailleur',
                'title' => 'Débroussaillage terrain résidentiel',
                'city' => 'Kribi',
                'min' => 15000,
                'max' => 50000,
            ],
            [
                'profession' => 'Gardien',
                'title' => 'Recherche gardien pour maison',
                'city' => 'Douala',
                'min' => 40000,
                'max' => 80000,
            ],
            [
                'profession' => 'Agent de sécurité',
                'title' => 'Agent de sécurité pour événement',
                'city' => 'Yaoundé',
                'min' => 10000,
                'max' => 25000,
            ],
            [
                'profession' => 'Nounou',
                'title' => 'Nounou pour garde d’enfant en journée',
                'city' => 'Douala',
                'min' => 30000,
                'max' => 70000,
            ],
            [
                'profession' => 'Aide aux personnes âgées',
                'title' => 'Assistance quotidienne pour personne âgée',
                'city' => 'Yaoundé',
                'min' => 30000,
                'max' => 90000,
            ],
            [
                'profession' => 'Réparateur téléphone',
                'title' => 'Réparation écran téléphone',
                'city' => 'Douala',
                'min' => 10000,
                'max' => 50000,
            ],
            [
                'profession' => 'Réparateur ordinateur',
                'title' => 'Nettoyage et dépannage ordinateur',
                'city' => 'Yaoundé',
                'min' => 15000,
                'max' => 60000,
            ],
            [
                'profession' => 'Saisie de documents',
                'title' => 'Saisie rapide de documents Word',
                'city' => 'Douala',
                'min' => 5000,
                'max' => 20000,
            ],
            [
                'profession' => 'Vendeur temporaire',
                'title' => 'Vendeur temporaire en boutique',
                'city' => 'Douala',
                'min' => 7000,
                'max' => 15000,
            ],
            [
                'profession' => 'Aide boutique',
                'title' => 'Aide boutique pour rangement et vente',
                'city' => 'Yaoundé',
                'min' => 7000,
                'max' => 18000,
            ],
            [
                'profession' => 'Faire les courses',
                'title' => 'Besoin de quelqu’un pour faire les courses',
                'city' => 'Douala',
                'min' => 5000,
                'max' => 12000,
            ],
            [
                'profession' => 'Distributeur de flyers',
                'title' => 'Distribution de flyers en ville',
                'city' => 'Bafoussam',
                'min' => 5000,
                'max' => 15000,
            ],
        ];

        $experiences = [
            'Débutant accepté',
            'Moins de 6 mois',
            '6 mois minimum',
            '1 an minimum',
            'Expérience souhaitée',
        ];

        foreach ($jobs as $index => $jobData) {
            if (!isset($professionObjects[$jobData['profession']])) {
                continue;
            }

            $title = $jobData['title'];
            $city = $jobData['city'];

            $job = new Job();
            $job->setTitle($title);
            $job->setDescription(
                "Nous recherchons une personne sérieuse et disponible pour ce service local : {$title}. " .
                "Le travail est ponctuel, simple et adapté aux petits métiers du quotidien."
            );
            $job->setCity($city);
            $job->setCreatedAt((clone $now)->modify('-' . rand(0, 12) . ' days'));
            $job->setCreatedBy($users[array_rand($users)]);
            $job->setProfession($professionObjects[$jobData['profession']]);
            $job->setSalaireMin($jobData['min']);
            $job->setSalaireMax($jobData['max']);
            $job->setExperience($experiences[array_rand($experiences)]);
            $job->setDateExpirationAt((clone $now)->modify('+' . rand(15, 45) . ' days'));
            $job->setMission(
                "Effectuer le service demandé avec ponctualité, respect des consignes et professionnalisme. " .
                "Le prestataire devra être disponible, propre, poli et capable de bien communiquer avec le client."
            );
            $job->setProfil(
                "Personne motivée, fiable, respectueuse et ayant une expérience pratique dans ce type de petit métier. " .
                "Une bonne présentation et le sens du service sont appréciés."
            );
            // Les URL des offres ne doivent jamais exposer le titre ni l'identifiant Doctrine.
            $job->setSlug(\App\Util\HashedSlugGenerator::generate());
            $job->setReference('MOL-' . date('Ymd') . '-' . str_pad((string) ($index + 1), 5, '0', STR_PAD_LEFT));

            if ($statusJob) {
                $job->setStatus($statusJob);
            }

            if ($typeJob) {
                $job->setTypeJob($typeJob);
            }

            $manager->persist($job);
        }

        $manager->flush();
    }

    private function findFirst(ObjectManager $manager, string $class): ?object
    {
        $items = $manager->getRepository($class)->findAll();

        return $items[0] ?? null;
    }

    private function slugify(string $text): string
    {
        $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        $text = trim($text, '-');

        return $text ?: 'n-a';
    }
}
