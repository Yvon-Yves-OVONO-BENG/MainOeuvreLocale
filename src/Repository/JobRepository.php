<?php

namespace App\Repository;

use App\Entity\Job;
use App\Entity\PersonalProfile;
use App\Entity\TypeJob;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Job>
 */
class JobRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Job::class);
    }
    
    // src/Repository/JobOfferRepository.php
    public function countByProfession(int $professionId): int
    {
        return $this->createQueryBuilder('j')
            ->select('COUNT(j.id)')
            ->where('j.profession = :professionId')
            ->setParameter('professionId', $professionId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Charge les offres de modération avec les relations utilisées par Twig.
     *
     * Les LEFT JOIN sont importants ici : une ancienne offre peut encore
     * référencer un utilisateur ou une profession supprimés. Doctrine hydrate
     * alors la relation à null au lieu de créer un proxy introuvable qui ferait
     * échouer tout le rendu de la page.
     *
     * @return Job[]
     */
    public function findAllForModeration(): array
    {
        return $this->createQueryBuilder('j')
            ->leftJoin('j.profession', 'moderationProfession')
            ->addSelect('moderationProfession')
            ->leftJoin('j.createdBy', 'moderationAuthor')
            ->addSelect('moderationAuthor')
            ->orderBy('j.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }


    /** Charge une offre à modifier/modérer même si une ancienne relation a été supprimée. */
    public function findOneForModeration(string $slug): ?Job
    {
        return $this->createQueryBuilder('j')
            ->leftJoin('j.profession', 'p')->addSelect('p')
            ->leftJoin('j.createdBy', 'u')->addSelect('u')
            ->leftJoin('j.typeJob', 't')->addSelect('t')
            ->leftJoin('j.status', 's')->addSelect('s')
            ->andWhere('j.slug = :slug')->setParameter('slug', $slug)
            ->getQuery()->getOneOrNullResult();
    }

    public function searchPaginated(array $filters, int $page = 1, int $limit = 10): Paginator
    {
        $qb = $this->createQueryBuilder('j')
            ->leftJoin('j.profession', 'p')
            ->addSelect('p')
            ->leftJoin('p.categorie', 'cat')
            ->addSelect('cat')
            ->leftJoin('j.typeJob', 'tj')
            ->addSelect('tj')
            ->leftJoin('j.createdBy', 'u')
            ->addSelect('u')
            ->andWhere('j.dateExpirationAt >= :now')
            ->setParameter('now', new \DateTimeImmutable());
    
        // ✅ Filtre obligatoire pour n'afficher que les offres approuvées ET publiées
        if (!empty($filters['is_approved'])) {
            $qb
                ->andWhere('j.moderationStatus = :approvedStatus')
                ->andWhere('j.status = :publishedStatus')
                ->setParameter('approvedStatus', 'approved')
                ->setParameter('publishedStatus', $this->getEntityManager()->getRepository(\App\Entity\StatusJob::class)->findPublished()?->getId() ?? 0);
        }
    
        if (!empty($filters['q'])) {
            $qb
                ->andWhere('LOWER(j.title) LIKE :q OR LOWER(j.description) LIKE :q OR LOWER(p.profession) LIKE :q OR LOWER(cat.nom) LIKE :q')
                ->setParameter('q', '%' . mb_strtolower($filters['q']) . '%');
        }
    
        if (!empty($filters['city'])) {
            $qb
                ->andWhere('LOWER(j.city) LIKE :city')
                ->setParameter('city', '%' . mb_strtolower($filters['city']) . '%');
        }
        
        if (!empty($filters['professions'])) {
            $qb
                ->andWhere('p.id IN (:professions)')
                ->setParameter('professions', $filters['professions']);
        }
    
        // Nouveau vrai filtre catégories
        if (!empty($filters['categories'])) {
            $qb
                ->andWhere('cat.id IN (:categories)')
                ->setParameter('categories', $filters['categories']);
        }
    
        if (!empty($filters['job_types'])) {
            $qb
                ->andWhere('tj.id IN (:jobTypes)')
                ->setParameter('jobTypes', $filters['job_types']);
        }
    
        if (!empty($filters['posted_by'])) {
            $qb
                ->andWhere('u.id IN (:postedBy)')
                ->setParameter('postedBy', $filters['posted_by']);
        }
    
        if (!empty($filters['salary_min'])) {
            $qb
                ->andWhere('j.salaireMax >= :salaryMin')
                ->setParameter('salaryMin', (int) $filters['salary_min']);
        }
    
        if (!empty($filters['salary_max'])) {
            $qb
                ->andWhere('j.salaireMin <= :salaryMax')
                ->setParameter('salaryMax', (int) $filters['salary_max']);
        }
    
        match ($filters['sort'] ?? 'newest') {
            'salary_desc' => $qb->orderBy('j.salaireMax', 'DESC'),
            'salary_asc'  => $qb->orderBy('j.salaireMin', 'ASC'),
            'relevant'    => $qb->orderBy('j.createdAt', 'DESC'),
            default       => $qb->orderBy('j.createdAt', 'DESC'),
        };
    
        $qb
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);
    
        return new Paginator($qb);
    }

    /**
     * Retourne les offres publiques proches d'une position, sans exposer
     * l'adresse exacte du recruteur.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findNearbyPublic(
        float $latitude,
        float $longitude,
        float $radiusKm,
        string $q = '',
        string $city = '',
        int $limit = 100,
    ): array {
        $limit = max(1, min(200, $limit));
        $latitudeDelta = $radiusKm / 111.045;
        $cosLatitude = max(0.01, abs(cos(deg2rad($latitude))));
        $longitudeDelta = $radiusKm / (111.045 * $cosLatitude);

        $sql = <<<'SQL'
            SELECT
                j.id,
                j.title,
                j.description,
                j.city,
                j.salaire_min,
                j.salaire_max,
                j.slug,
                profession.profession AS profession_name,
                personal.full_name,
                CASE WHEN pro.geolocation_enabled = 1 THEN pro.latitude ELSE NULL END AS latitude,
                CASE WHEN pro.geolocation_enabled = 1 THEN pro.longitude ELSE NULL END AS longitude,
                CASE
                    WHEN pro.geolocation_enabled = 1
                     AND pro.latitude IS NOT NULL
                     AND pro.longitude IS NOT NULL
                    THEN (
                        6371 * 2 * ASIN(
                            SQRT(
                                LEAST(
                                    1,
                                    POWER(SIN(RADIANS((pro.latitude - :latitude) / 2)), 2)
                                    + COS(RADIANS(:latitude))
                                    * COS(RADIANS(pro.latitude))
                                    * POWER(SIN(RADIANS((pro.longitude - :longitude) / 2)), 2)
                                )
                            )
                        )
                    )
                    ELSE NULL
                END AS distance_km
            FROM job j
            INNER JOIN `user` u ON u.id = j.created_by_id
            LEFT JOIN professional_profile pro ON pro.user_id = u.id
            LEFT JOIN personal_profile personal ON personal.user_id = u.id
            LEFT JOIN profession profession ON profession.id = j.profession_id
            WHERE j.moderation_status = :approvedStatus
              AND j.status_id = :publishedStatus
              AND j.date_expiration_at >= CURRENT_TIMESTAMP()
              AND u.is_active = 1
              AND (
                    pro.id IS NULL
                    OR pro.geolocation_enabled = 0
                    OR pro.latitude IS NULL
                    OR pro.longitude IS NULL
                    OR (
                        pro.latitude BETWEEN :minLatitude AND :maxLatitude
                        AND pro.longitude BETWEEN :minLongitude AND :maxLongitude
                    )
              )
        SQL;

        $params = [
            'latitude' => (string) $latitude,
            'longitude' => (string) $longitude,
            'radius' => (string) $radiusKm,
            'approvedStatus' => 'approved',
            'publishedStatus' => $this->getEntityManager()->getRepository(\App\Entity\StatusJob::class)->findPublished()?->getId() ?? 0,
            'minLatitude' => (string) ($latitude - $latitudeDelta),
            'maxLatitude' => (string) ($latitude + $latitudeDelta),
            'minLongitude' => (string) ($longitude - $longitudeDelta),
            'maxLongitude' => (string) ($longitude + $longitudeDelta),
        ];
        $types = [
            'latitude' => ParameterType::STRING,
            'longitude' => ParameterType::STRING,
            'radius' => ParameterType::STRING,
            'approvedStatus' => ParameterType::STRING,
            'publishedStatus' => ParameterType::INTEGER,
            'minLatitude' => ParameterType::STRING,
            'maxLatitude' => ParameterType::STRING,
            'minLongitude' => ParameterType::STRING,
            'maxLongitude' => ParameterType::STRING,
        ];

        $q = trim($q);
        if ($q !== '') {
            $sql .= <<<'SQL'

              AND (
                    LOWER(j.title) LIKE :searchTerm
                    OR LOWER(j.description) LIKE :searchTerm
                    OR LOWER(j.city) LIKE :searchTerm
                    OR LOWER(profession.profession) LIKE :searchTerm
                    OR LOWER(personal.full_name) LIKE :searchTerm
              )
            SQL;
            $params['searchTerm'] = '%' . mb_strtolower($q) . '%';
            $types['searchTerm'] = ParameterType::STRING;
        }

        $city = trim($city);
        if ($city !== '') {
            $sql .= <<<'SQL'

              AND (LOWER(j.city) LIKE :cityTerm OR LOWER(personal.city) LIKE :cityTerm)
            SQL;
            $params['cityTerm'] = '%' . mb_strtolower($city) . '%';
            $types['cityTerm'] = ParameterType::STRING;
        }

        $sql .= sprintf(
            ' HAVING distance_km IS NULL OR distance_km <= :radius ORDER BY distance_km IS NULL ASC, distance_km ASC LIMIT %d',
            $limit
        );

        return $this->getEntityManager()
            ->getConnection()
            ->executeQuery($sql, $params, $types)
            ->fetchAllAssociative();
    }

    public function countActiveByCity(string $city): int
    {
        return (int) $this->createQueryBuilder('j')
            ->select('COUNT(j.id)')
            ->andWhere('LOWER(TRIM(j.city)) = LOWER(TRIM(:city))')
            ->andWhere('(j.dateExpirationAt IS NULL OR j.dateExpirationAt >= :now)')
            ->setParameter('city', $city)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return Job[]
     */
    public function findActiveByCityPaginated(string $city, int $page = 1, int $limit = 12): array
    {
        $offset = max(0, ($page - 1) * $limit);

        return $this->createQueryBuilder('j')
            ->leftJoin('j.createdBy', 'u')->addSelect('u')
            ->leftJoin('u.personalProfile', 'pp')->addSelect('pp')
            ->leftJoin('j.profession', 'profession')->addSelect('profession')
            ->leftJoin('j.typeJob', 'typeJob')->addSelect('typeJob')
            ->andWhere('LOWER(TRIM(j.city)) = LOWER(TRIM(:city))')
            ->andWhere('(j.dateExpirationAt IS NULL OR j.dateExpirationAt >= :now)')
            ->setParameter('city', $city)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('j.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Job[]
     */
    public function findActiveByCity(string $city): array
    {
        return $this->createQueryBuilder('j')
            ->leftJoin('j.createdBy', 'u')->addSelect('u')
            ->leftJoin('u.personalProfile', 'pp')->addSelect('pp')
            ->leftJoin('j.profession', 'profession')->addSelect('profession')
            ->leftJoin('j.typeJob', 'typeJob')->addSelect('typeJob')
            ->andWhere('LOWER(TRIM(j.city)) = LOWER(TRIM(:city))')
            ->andWhere('(j.dateExpirationAt IS NULL OR j.dateExpirationAt >= :now)')
            ->setParameter('city', $city)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('j.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findJobsWithApplicationsForAuthor(\App\Entity\User $author): array
    {
        return $this->createQueryBuilder('j')
            ->leftJoin('j.applications', 'a')->addSelect('a')
            ->leftJoin('a.user', 'u')->addSelect('u')
            ->leftJoin('u.personalProfile', 'pp')->addSelect('pp')
            ->andWhere('j.createdBy = :author')
            ->setParameter('author', $author)
            ->orderBy('j.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countActiveJobs(): int
    {
        return (int) $this->createQueryBuilder('j')
            ->select('COUNT(j.id)')
            ->andWhere('j.dateExpirationAt > :now')
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findActiveJobs(int $limit = 10): array
    {
        return $this->createQueryBuilder('j')
            ->andWhere('j.dateExpirationAt > :now')
            ->setParameter('now', new \DateTime())
            ->orderBy('j.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findAdminJobsPaginated(
        string $status = '',
        string $q = '',
        int $page = 1,
        int $perPage = 10
    ): array {
        $page = max(1, $page);
        $perPage = max(1, min($perPage, 100));

        return $this->createAdminJobsQueryBuilder($status, $q)
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();
    }

    public function countAdminJobs(
        string $status = '',
        string $q = ''
    ): int {
        return (int) $this->createAdminJobsQueryBuilder($status, $q)
            ->select('COUNT(DISTINCT j.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function createAdminJobsQueryBuilder(string $status = '', string $q = '')
    {
        $qb = $this->createQueryBuilder('j')
            ->leftJoin('j.createdBy', 'creator')
            ->addSelect('creator')
            ->orderBy('j.createdAt', 'DESC');

        if ($status === 'active') {
            $qb
                ->andWhere('j.dateExpirationAt IS NOT NULL')
                ->andWhere('j.dateExpirationAt > :now')
                ->setParameter('now', new \DateTime());
        }

        $q = trim($q);

        if ($q !== '') {
            $qb
                ->andWhere(
                    $qb->expr()->orX(
                        'LOWER(j.title) LIKE :q',
                        'LOWER(j.reference) LIKE :q',
                        'LOWER(creator.email) LIKE :q'
                    )
                )
                ->setParameter('q', '%' . mb_strtolower($q) . '%');
        }

        return $qb;
    }

    /**
     * ✅ 3 dernières offres de la même ville
     */
    public function findLatestByCity(?string $city, int $limit = 3): array
    {
        if (!$city) {
            return [];
        }

        return $this->createQueryBuilder('j')
            ->andWhere('j.city = :city')
            ->setParameter('city', $city)
            ->orderBy('j.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte le nombre de projets publiés par un utilisateur.
     * @param User $user
     * @return int
     */
    public function countProjectsPublishedBy(User $user): int
    {
        return (int) $this->createQueryBuilder('j')
            ->select('COUNT(j.id)')
            ->andWhere('j.createdBy = :u')
            ->setParameter('u', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findPublishedJobsWithApplicantCounts(int $publisherId, int $limit = 50): array
    {
        return $this->createQueryBuilder('j')
            ->select('j as job')
            ->addSelect('COUNT(a.id) as applicantsTotal')
            ->addSelect('SUM(CASE WHEN a.viewedAt IS NULL THEN 1 ELSE 0 END) as applicantsRemaining')
            ->leftJoin('App\Entity\Application', 'a', 'WITH', 'a.job = j')
            ->andWhere('j.createdBy = :pid') // <-- adapte ici
            ->setParameter('pid', $publisherId)
            ->groupBy('j.id')
            ->orderBy('j.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countActiveJobsByUser(User $user): int
    {
        return (int) $this->createQueryBuilder('j')
            ->select('COUNT(j.id)')
            ->where('j.createdBy = :user')
            ->andWhere('j.dateExpirationAt >= :now')
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getSingleScalarResult();
    }

    /*
    * Récupère les projets actifs d’un utilisateur.
    * @param User $user
    * @param int $limit
    * @return array
    */
    public function findActiveProjectsByUser(User $user, int $limit): array
    {
        return $this->createQueryBuilder('j')
            ->andWhere('j.createdBy = :u')
            ->setParameter('u', $user)
            ->orderBy('j.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte le nombre d'offres publiées par une entreprise (profil).
     * Ici : une offre appartient à l'entreprise si createdBy = user du profil.
     * @param PersonalProfile $company
     * @return int
     */
    public function countPublishedByCompanyProfile(PersonalProfile $company): int
    {
        $user = $company->getUser();

        return (int) $this->createQueryBuilder('j')
            ->select('COUNT(j.id)')
            ->andWhere('j.createdBy = :u')
            ->setParameter('u', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Compte le nombre d'offres actives par une entreprise (profil).
     * Supporte plusieurs schémas: isActive ou status='ACTIVE'.
     * @param PersonalProfile $company
     * @return int
     */
    public function countActiveByCompanyProfile(PersonalProfile $company): int
    {
        $user = $company->getUser();
        $meta = $this->getEntityManager()->getClassMetadata(Job::class);

        $qb = $this->createQueryBuilder('j')
            ->select('COUNT(j.id)')
            ->andWhere('j.createdBy = :u')
            ->setParameter('u', $user);

        if ($meta->hasField('isActive')) {
            $qb->andWhere('j.isActive = :a')->setParameter('a', true);
        } elseif ($meta->hasField('status')) {
            // Ici status est une relation ManyToOne vers StatusJob chez toi.
            // Donc il faut filtrer sur un champ du StatusJob .
            // Si ton StatusJob a "statusJob":
            $qb->leftJoin('j.status', 'st')
            ->andWhere('st.statusJob = :st')
            ->setParameter('st', 'ACTIVE');
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Récupère les offres actives d'une entreprise (profil).
     * @param PersonalProfile $company
     * @param int $limit
     * @return array
     */
    public function findActiveForCompanyProfile(PersonalProfile $company, int $limit): array
    {
        $user = $company->getUser();
        $meta = $this->getEntityManager()->getClassMetadata(Job::class);

        $qb = $this->createQueryBuilder('j')
            ->andWhere('j.createdBy = :u')
            ->setParameter('u', $user)
            ->orderBy('j.id', 'DESC')
            ->setMaxResults($limit);

        if ($meta->hasField('isActive')) {
            $qb->andWhere('j.isActive = :a')->setParameter('a', true);
        } elseif ($meta->hasField('status')) {
            $qb->leftJoin('j.status', 'st')
            ->andWhere('st.statusJob = :st')
            ->setParameter('st', 'ACTIVE');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Compte le nombre d'offres créées par jour sur les N derniers jours (profil entreprise).
     * ⚠️ Compatible partout : pas de DATE(), pas de YEAR(), pas de FUNCTION().
     *
     * Retour:
     * [
     *   ['d' => '2026-02-01', 'c' => 3],
     *   ['d' => '2026-02-02', 'c' => 7],
     * ]
     *
     * @param PersonalProfile $company
     * @param int $days
     * @return array
     */
    public function countCreatedLastDaysForCompanyProfile(PersonalProfile $company, int $days): array
    {
        $user = $company->getUser();
        $from = new \DateTimeImmutable("-{$days} days");

        // 1) On récupère juste les dates de création (sans fonctions SQL)
        $rows = $this->createQueryBuilder('j')
            ->select('j.createdAt')
            ->andWhere('j.createdBy = :u')
            ->andWhere('j.createdAt >= :dt')
            ->setParameter('u', $user)
            ->setParameter('dt', $from)
            ->orderBy('j.createdAt', 'ASC')
            ->getQuery()
            ->getArrayResult();

        // 2) On regroupe par jour en PHP
        $counts = [];
        foreach ($rows as $r) {
            // selon hydratation, createdAt peut être string ou DateTime
            $dt = $r['createdAt'] instanceof \DateTimeInterface
                ? $r['createdAt']
                : new \DateTimeImmutable((string) $r['createdAt']);

            $dayKey = $dt->format('Y-m-d');
            $counts[$dayKey] = ($counts[$dayKey] ?? 0) + 1;
        }

        // 3) Optionnel: remplir les jours "0" pour un graphique propre
        $out = [];
        $cursor = $from->setTime(0, 0);
        $today  = (new \DateTimeImmutable('now'))->setTime(0, 0);

        while ($cursor <= $today) {
            $k = $cursor->format('Y-m-d');
            $out[] = ['d' => $k, 'c' => $counts[$k] ?? 0];
            $cursor = $cursor->modify('+1 day');
        }

        return $out;
    }

    

    /**
     * Retourne les offres "signalées" (flagged) selon le modèle Job :
     * - si champ isReported existe -> isReported = true (retourne des Job[])
     * - sinon -> [] (car ton entité Report ne cible pas les jobs)
     *
     * @return Job[]
     */
    public function findFlaggedJobs(EntityManagerInterface $em, int $limit = 10): array
    {
        $meta = $em->getClassMetadata(Job::class);

        if ($meta->hasField('isReported')) {
            return $this->createQueryBuilder('j')
                ->andWhere('j.isReported = :r')
                ->setParameter('r', true)
                ->orderBy('j.id', 'DESC')
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult();
        }

        // Ton Report actuel n'a pas targetJob -> donc on ne peut pas déduire les jobs signalés via Report.
        return [];
    }

    /**
     * Compte toutes les offres.
     */
    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('j')
            ->select('COUNT(j.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Compte les offres selon le libellé de statut (StatusJob.statusJob).
     *
     * Exemple d'appel :
     *   $jobRepo->countByStatusLabel('RECRUITED');
     * ou
     *   $jobRepo->countByStatusLabel('Recruté');
     *
     * ⚠️ Le texte doit correspondre à ce que tu stockes réellement dans status_job.status_job.
     */
    public function countByStatusLabel(string $statusLabel): int
    {
        return (int) $this->createQueryBuilder('j')
            ->select('COUNT(j.id)')
            ->innerJoin('j.status', 's')
            ->andWhere('s.statusJob = :label')
            ->setParameter('label', $statusLabel)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Retourne: [categoryId => [ 'category' => Category, 'jobs' => Job[] ]]
     * @return array<int, array{category: mixed, jobs: array<int, Job>}>
     */
    public function topJobsByCategory(int $perCategory = 2): array
    {
        // Stratégie simple (portable) :
        // 1) récupérer les catégories distinctes
        // 2) pour chaque catégorie, récupérer top N jobs
        // (OK pour sidebar; si énorme volume, on optimisera)

        $em = $this->getEntityManager();

        $cats = $em->createQueryBuilder()
            ->select('c')
            ->from('App\Entity\Categorie', 'c')
            ->orderBy('c.nom', 'ASC')
            ->getQuery()->getResult();

        $out = [];
        foreach ($cats as $c) {
            $jobs = $this->createQueryBuilder('j')
                ->andWhere('j.category = :c')
                ->setParameter('c', $c)
                ->orderBy('j.createdAt', 'DESC')
                ->setMaxResults($perCategory)
                ->getQuery()->getResult();

            if ($jobs) {
                $out[(int) $c->getId()] = ['category' => $c, 'jobs' => $jobs];
            }
        }

        return $out;
    }


    /**
     * Retourne les N offres les plus récentes pour chaque profession.
     *
     * Objectif UX :
     * - Alimenter la sidebar "Offres par profession"
     * - Afficher quelques jobs sans charger toute la base
     *
     * Stratégie :
     * 1. Récupérer toutes les professions
     * 2. Pour chaque profession → récupérer les derniers jobs
     * 3. Ignorer les professions sans offres
     *
     * @param int $perProfession Nombre maximum d'offres par profession
     * @return array<int, array{profession: \App\Entity\Profession, jobs: array<int, \App\Entity\Job>}>
     */
    public function topJobsByProfession(int $perProfession = 2): array
    {
        $em = $this->getEntityManager();

        // 1️⃣ Charger toutes les professions
        $professions = $em->createQueryBuilder()
            ->select('p')
            ->from('App\Entity\Profession', 'p')
            ->orderBy('p.id', 'DESC')
            ->getQuery()
            ->getResult();

        $out = [];

        foreach ($professions as $p) {

            // 2️⃣ Charger les jobs récents de la profession
            $jobs = $this->createQueryBuilder('j')
                ->leftJoin('j.profession', 'pp')->addSelect('pp')
                ->andWhere('j.profession = :p')
                ->setParameter('p', $p)
                ->orderBy('j.createdAt', 'DESC')
                ->setMaxResults($perProfession)
                ->getQuery()
                ->getResult();

            // 3️⃣ Ne garder que les professions ayant des offres
            if ($jobs) {
                $out[(int) $p->getId()] = [
                    'profession' => $p,
                    'jobs' => $jobs,
                ];
            }
        }

        return $out;
    }

    
    /**
     * Retourne la liste DISTINCTE des utilisateurs ayant publié au moins une offre.
     *
     * Objectif :
     * - Alimenter les filtres "Posté par" dans la sidebar
     * - Éviter les doublons (un user peut avoir plusieurs jobs)
     * - Trier proprement pour un rendu UX propre
     *
     * Logique Doctrine :
     * - On part de l'entité Job (alias j)
     * - On joint la relation vers l'utilisateur créateur (createdBy)
     * - DISTINCT empêche les répétitions
     */
    public function findPublishersForFilters(): array
    {
        return $this->createQueryBuilder('j')
            ->select(
                'u.id AS userId',
                'COALESCE(p.fullName, u.email) AS fullName',
                'COUNT(j.id) AS totalJobs'
            )
            ->innerJoin('j.createdBy', 'u')
            ->leftJoin('u.personalProfile', 'p')
            ->where('u.roles LIKE :rp OR u.roles LIKE :rc')
            ->setParameter('rp', '%"ROLE_PARTICULIER"%')
            ->setParameter('rc', '%"ROLE_COMPANY"%')
            ->groupBy('u.id, p.fullName, u.email')
            ->orderBy('totalJobs', 'DESC')
            ->addOrderBy('fullName', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    
    /**
     * Je récupère les jobs similaires au job courant.
     * 
     * La similarité est basée prioritairement sur la profession,
     * et secondairement sur la ville si nécessaire.
     * 
     * Le job courant est explicitement exclu des résultats
     * afin d’éviter les doublons dans l’affichage.
     * 
     * Les résultats sont triés par date de création décroissante
     * pour afficher les offres les plus récentes en premier.
     */
    public function findRelatedJobs(Job $job, int $limit = 8): array
    {
        return $this->createQueryBuilder('j')
            ->leftJoin('j.profession', 'p')->addSelect('p')
            ->andWhere('j != :current')
            ->setParameter('current', $job)
            ->andWhere('(j.profession = :profession OR j.city = :city)')
            ->setParameter('profession', $job->getProfession())
            ->setParameter('city', $job->getCity())
            ->orderBy('j.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    

    // ✅ Sidebar calendrier : missions du particulier + candidatures + candidat
    public function findOwnedJobsWithApplicationsForCalendar(User $owner): array
    {
        $qb = $this->createQueryBuilder('j')
            ->leftJoin('j.applications', 'a')->addSelect('a')
            // ⚠️ ADAPTE ICI si ton Application n'a pas "user" comme champ candidat
            // ex: ->leftJoin('a.candidate', 'cand') ou ->leftJoin('a.applicant', 'cand')
            ->leftJoin('a.user', 'cand')->addSelect('cand')
            ->leftJoin('j.status', 's')->addSelect('s')
            ->andWhere('j.createdBy = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('j.createdAt', 'DESC')
            ->addOrderBy('a.createdAt', 'DESC');

        return $qb->getQuery()->getResult();
    }

    public function findMyJobsWithApplicationsForCalendar(User $me, int $limit = 50): array
    {
        return $this->createQueryBuilder('j')
            ->leftJoin('j.applications', 'a')->addSelect('a')
            ->leftJoin('a.user', 'au')->addSelect('au') // adapte si autre propriété sur Application
            ->andWhere('j.createdBy = :me')
            ->setParameter('me', $me)
            ->orderBy('j.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }


    /**
     * @return array<string, Job[]>  ex: ["CDI" => [Job, ...], "CDD" => [...]]
     */
    public function findLatestByTypeJobFromDb(int $limitPerType = 5, bool $onlyNotExpired = true): array
    {
        $now = new \DateTimeImmutable();

        // 1) On récupère les types depuis la BD
        /** @var TypeJob[] $types */
        $types = $this->getEntityManager()
            ->getRepository(TypeJob::class)
            ->createQueryBuilder('t')
            ->orderBy('t.typeJob', 'ASC')
            ->getQuery()
            ->getResult();

        // 2) Pour chaque type, on prend les 5 derniers jobs
        $out = [];
        foreach ($types as $type) {
            $qb = $this->createQueryBuilder('j')
                ->innerJoin('j.typeJob', 't')->addSelect('t')
                ->andWhere('t = :type')
                ->setParameter('type', $type)
                ->orderBy('j.createdAt', 'DESC')
                ->setMaxResults($limitPerType);

            if ($onlyNotExpired) {
                $qb->andWhere('j.dateExpirationAt > :now')
                   ->setParameter('now', $now);
            }

            $out[$type->getTypeJob() ?? ('TYPE_'.$type->getId())] = $qb->getQuery()->getResult();
        }

        return $out;
    }


    /**
     * Liste minimaliste pour dropdown (id + title).
     */
    public function findCompanyJobsForSelect(User $company): array
    {
        return $this->createQueryBuilder('j')
            ->select('j.id, j.title')
            ->andWhere('j.createdBy = :u')
            ->setParameter('u', $company)
            ->orderBy('j.createdAt', 'DESC')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * “Actives” = non expirées (tu peux ajouter un filtre status si tu veux).
     */
    public function countActiveJob(User $company, \DateTimeImmutable $now): int
    {
        return (int) $this->createQueryBuilder('j')
            ->select('COUNT(j.id)')
            ->andWhere('j.createdBy = :u')
            ->andWhere('j.dateExpirationAt >= :now')
            ->setParameter('u', $company)
            ->setParameter('now', $now)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Tableau performance par job (views/apps/favs + last activity + appsViewed).
     * ⚠️ Important: COUNT(DISTINCT ...) pour éviter la multiplication due aux joins.
     */
    public function getCompanyJobsPerformance(
        User $company,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
        ?Job $selectedJob = null
    ): array {
        $qb = $this->createQueryBuilder('j')
            ->select('j.id AS id, j.title AS title, j.city AS city, j.dateExpirationAt AS expiresAt')
            ->addSelect('COUNT(DISTINCT v.id) AS views')
            ->addSelect('COUNT(DISTINCT a.id) AS apps')
            ->addSelect('COUNT(DISTINCT f.id) AS favs')
            ->addSelect('COUNT(DISTINCT av.id) AS appsViewed')
            ->addSelect('MAX(a.createdAt) AS lastAppAt')
            ->addSelect('MAX(v.viewedAt) AS lastViewAt')
            ->leftJoin('j.views', 'v', 'WITH', 'v.viewedAt BETWEEN :from AND :to')
            ->leftJoin('j.applications', 'a', 'WITH', 'a.createdAt BETWEEN :from AND :to')
            ->leftJoin('j.favoris', 'f', 'WITH', 'f.createdAt BETWEEN :from AND :to')
            ->leftJoin('j.applications', 'av', 'WITH', 'av.viewedAt IS NOT NULL AND av.createdAt BETWEEN :from AND :to')
            ->andWhere('j.createdBy = :u')
            ->setParameter('u', $company)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->groupBy('j.id')
            ->orderBy('apps', 'DESC');

        if ($selectedJob) {
            $qb->andWhere('j = :job')->setParameter('job', $selectedJob);
        }

        return $qb->getQuery()->getArrayResult();
    }



    /** Jobs qui expirent aujourd’hui */
    public function findExpiringToday(User $me, \DateTimeInterface $todayStart, \DateTimeInterface $todayEnd): array
    {
        return $this->createQueryBuilder('j')
            ->leftJoin('j.status', 's')->addSelect('s')
            ->andWhere('j.createdBy = :me')
            ->andWhere('j.dateExpirationAt IS NOT NULL')
            ->andWhere('j.dateExpirationAt BETWEEN :todayStart AND :todayEnd')
            ->setParameter('me', $me)
            ->setParameter('todayStart', $todayStart)
            ->setParameter('todayEnd', $todayEnd)
            ->orderBy('j.dateExpirationAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** Jobs à venir (jusqu’à weekEnd) */
    public function findUpcoming(User $me, \DateTimeInterface $todayEnd, \DateTimeInterface $weekEnd, int $limit = 20): array
    {
        return $this->createQueryBuilder('j')
            ->leftJoin('j.status', 's')->addSelect('s')
            ->andWhere('j.createdBy = :me')
            ->andWhere('j.dateExpirationAt IS NOT NULL')
            ->andWhere('j.dateExpirationAt > :todayEnd')
            ->andWhere('j.dateExpirationAt <= :weekEnd')
            ->setParameter('me', $me)
            ->setParameter('todayEnd', $todayEnd)
            ->setParameter('weekEnd', $weekEnd)
            ->orderBy('j.dateExpirationAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** Jobs en retard (expirés) */
    public function findOverdue(User $me, \DateTimeInterface $todayStart, int $limit = 20): array
    {
        return $this->createQueryBuilder('j')
            ->leftJoin('j.status', 's')->addSelect('s')
            ->andWhere('j.createdBy = :me')
            ->andWhere('j.dateExpirationAt IS NOT NULL')
            ->andWhere('j.dateExpirationAt < :todayStart')
            ->setParameter('me', $me)
            ->setParameter('todayStart', $todayStart)
            ->orderBy('j.dateExpirationAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }


    public function findSuspectJobs(string $q, int $limit, int $offset): array
    {
        $qb = $this->createQueryBuilder('j')
            ->leftJoin('j.createdBy', 'u')->addSelect('u')
            ->leftJoin('u.personalProfile', 'pp')->addSelect('pp')
            ->orderBy('j.createdAt', 'DESC');

        // ✅ Heuristiques “suspect”
        $qb->andWhere("
            LOWER(j.title) LIKE :k
            OR LOWER(j.description) LIKE :k
            OR LOWER(j.description) LIKE :k2
            OR LOWER(j.description) LIKE :k3
            OR LOWER(j.description) LIKE :k4
        ")
        ->setParameter('k',  '%arnaque%')
        ->setParameter('k2', '%frais%')
        ->setParameter('k3', '%whatsapp%')
        ->setParameter('k4', '%gagne%');

        if ($q !== '') {
            $qb->andWhere("j.title LIKE :q OR j.description LIKE :q OR u.email LIKE :q")
            ->setParameter('q', '%'.$q.'%');
        }

        $items = $qb->setFirstResult($offset)->setMaxResults($limit)->getQuery()->getResult();
        $total = (int) (clone $qb)->select('COUNT(DISTINCT j.id)')->getQuery()->getSingleScalarResult();

        return ['items' => $items, 'total' => $total];
    }


     public function countJobsCreatedBy(int $userId, int $days = 30): int
    {
        $from = (new \DateTimeImmutable())->modify("-{$days} days")->setTime(0,0,0);

        return (int) $this->createQueryBuilder('j')
            ->select('COUNT(j.id)')
            ->andWhere('j.createdBy = :u')->setParameter('u', $userId)
            ->andWhere('j.createdAt >= :from')->setParameter('from', $from)
            ->getQuery()->getSingleScalarResult();
    }

    public function lastJobsCreatedBy(int $userId, int $limit = 5): array
    {
        return $this->createQueryBuilder('j')
            ->andWhere('j.createdBy = :u')->setParameter('u', $userId)
            ->orderBy('j.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()->getResult();
    }


    public function countAllJobs(): int
    {
        return (int) $this->createQueryBuilder('j')
            ->select('COUNT(j.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countExpiredJobs(): int
    {
        return (int) $this->createQueryBuilder('j')
            ->select('COUNT(j.id)')
            ->andWhere('j.dateExpirationAt <= :now')
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countNewJobsSince(\DateTimeInterface $since): int
    {
        return (int) $this->createQueryBuilder('j')
            ->select('COUNT(j.id)')
            ->andWhere('j.createdAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getJobsChartSince(\DateTimeInterface $since): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
            SELECT DATE(created_at) AS d, COUNT(id) AS c
            FROM job
            WHERE created_at >= :since
            GROUP BY DATE(created_at)
            ORDER BY d ASC
        ';

        return $conn->executeQuery(
            $sql,
            ['since' => $since->format('Y-m-d H:i:s')]
        )->fetchAllAssociative();
    }

    public function getTopCities(int $limit = 8): array
    {
        return $this->createQueryBuilder('j')
            ->select('j.city AS city, COUNT(j.id) AS total')
            ->andWhere('j.city IS NOT NULL')
            ->andWhere('j.city != :empty')
            ->setParameter('empty', '')
            ->groupBy('j.city')
            ->orderBy('total', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
    }

    public function findRecentJobs(int $limit = 8): array
    {
        return $this->createQueryBuilder('j')
            ->leftJoin('j.createdBy', 'u')->addSelect('u')
            ->leftJoin('j.profession', 'p')->addSelect('p')
            ->orderBy('j.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function getTopCreators(int $limit = 8): array
    {
        return $this->createQueryBuilder('j')
            ->select('u.email AS email, COUNT(j.id) AS total')
            ->leftJoin('j.createdBy', 'u')
            ->andWhere('u.id IS NOT NULL')
            ->groupBy('u.id, u.email')
            ->orderBy('total', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
    }

    public function getApplicationStats(int $limit = 8): array
    {
        return $this->createQueryBuilder('j')
            ->select('j.id AS id, j.title AS title, COUNT(a.id) AS applications')
            ->leftJoin('j.applications', 'a')
            ->groupBy('j.id, j.title')
            ->orderBy('applications', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
    }

    public function findLatestActiveJobs(int $limit = 100): array
    {
        return $this->createQueryBuilder('j')
            ->leftJoin('j.createdBy', 'u')->addSelect('u')
            ->leftJoin('u.personalProfile', 'pp')->addSelect('pp')
            ->leftJoin('j.status', 's')->addSelect('s')
            ->leftJoin('j.typeJob', 'tj')->addSelect('tj')
            ->leftJoin('j.profession', 'p')->addSelect('p')
            ->andWhere('j.dateExpirationAt >= :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('j.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }


    public function countActiveJobsFiltered(?string $q = null): int
    {
        $qb = $this->createQueryBuilder('j')
            ->select('COUNT(DISTINCT j.id)')
            ->andWhere('j.dateExpirationAt >= :now')
            ->setParameter('now', new \DateTimeImmutable());

        if ($q !== null && trim($q) !== '') {
            $qb
                ->andWhere('
                    LOWER(j.title) LIKE :q
                    OR LOWER(j.description) LIKE :q
                    OR LOWER(j.city) LIKE :q
                    OR LOWER(j.reference) LIKE :q
                ')
                ->setParameter('q', '%' . mb_strtolower(trim($q)) . '%');
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function findLatestActiveJobsPaginated(
        int $page = 1,
        int $perPage = 10,
        ?string $q = null
    ): array {
        $offset = max(0, ($page - 1) * $perPage);

        $qb = $this->createQueryBuilder('j')
            ->leftJoin('j.createdBy', 'u')->addSelect('u')
            ->leftJoin('u.personalProfile', 'pp')->addSelect('pp')
            ->leftJoin('j.status', 's')->addSelect('s')
            ->leftJoin('j.typeJob', 'tj')->addSelect('tj')
            ->leftJoin('j.profession', 'p')->addSelect('p')
            ->andWhere('j.dateExpirationAt >= :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('j.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($perPage);

        if ($q !== null && trim($q) !== '') {
            $qb
                ->andWhere('
                    LOWER(j.title) LIKE :q
                    OR LOWER(j.description) LIKE :q
                    OR LOWER(j.city) LIKE :q
                    OR LOWER(j.reference) LIKE :q
                ')
                ->setParameter('q', '%' . mb_strtolower(trim($q)) . '%');
        }

        return $qb->getQuery()->getResult();
    }

    public function searchAdmin(string $q): array
    {
        return $this->createQueryBuilder('j')
            ->select('j.id, j.title, j.location')
            ->where('j.title LIKE :q OR j.location LIKE :q')
            ->setParameter('q', "%$q%")
            ->setMaxResults(5)
            ->getQuery()
            ->getArrayResult();
    }

    public function findLatestJobs()
    {
        return $this->createQueryBuilder('j')
            ->orderBy('j.createdAt','DESC')
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();
    }


    public function countJobsCreatedBetween(
        \DateTimeInterface $start,
        \DateTimeInterface $end
    ): int {
        return (int) $this->createQueryBuilder('j')
            ->select('COUNT(j.id)')
            ->andWhere('j.createdAt >= :start')
            ->andWhere('j.createdAt < :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getCurrentMonthJobsStats(): array
    {
        $now = new \DateTimeImmutable();

        $startCurrentMonth = $now->modify('first day of this month')->setTime(0, 0, 0);
        $startNextMonth = $startCurrentMonth->modify('+1 month');
        $startPreviousMonth = $startCurrentMonth->modify('-1 month');

        $currentMonthCount = $this->countJobsCreatedBetween(
            $startCurrentMonth,
            $startNextMonth
        );

        $previousMonthCount = $this->countJobsCreatedBetween(
            $startPreviousMonth,
            $startCurrentMonth
        );

        if ($previousMonthCount > 0) {
            $percentage = round((($currentMonthCount - $previousMonthCount) / $previousMonthCount) * 100, 1);
        } else {
            $percentage = $currentMonthCount > 0 ? 100.0 : 0.0;
        }

        return [
            'currentMonthCount' => $currentMonthCount,
            'previousMonthCount' => $previousMonthCount,
            'percentage' => $percentage,
        ];
    }


    public function countNotExpiredJobs(): int
    {
        return (int) $this->createQueryBuilder('j')
            ->select('COUNT(j.id)')
            ->andWhere('j.dateExpirationAt >= :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<string> */
    public function findDistinctCitiesForSuggestions(int $limit = 300): array
    {
        $rows = $this->createQueryBuilder('jobSuggestion')
            ->select('DISTINCT jobSuggestion.city AS value')
            ->andWhere('jobSuggestion.city IS NOT NULL')
            ->andWhere("TRIM(jobSuggestion.city) <> ''")
            ->orderBy('jobSuggestion.city', 'ASC')
            ->setMaxResults(max(1, min(1000, $limit)))
            ->getQuery()
            ->getArrayResult();

        return array_values(array_filter(array_map(
            static fn (array $row): string => trim((string) ($row['value'] ?? '')),
            $rows
        )));
    }

}
