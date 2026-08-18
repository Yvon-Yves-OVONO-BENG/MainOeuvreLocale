<?php

namespace App\Repository;

use App\Entity\ProfessionalProfile;
use App\Entity\Review;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProfessionalProfile>
 */
class ProfessionalProfileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, protected EntityManagerInterface $em)
    {
        parent::__construct($registry, ProfessionalProfile::class);
    }

    public function markCvVerified(int $professionalProfileId, bool $verified, int $moderatorId): void
    {
        $this->em->createQuery('
            UPDATE App\Entity\ProfessionalProfile pro
            SET pro.isVerified = :v,
                pro.verifiedAt = :dt,
                pro.verifiedBy = :mod,
                pro.updatedAt = :dt
            WHERE pro.id = :id
        ')
            ->setParameter('v', $verified)
            ->setParameter('dt', new \DateTime())
            ->setParameter('mod', $moderatorId)
            ->setParameter('id', $professionalProfileId)
            ->execute();
    }

    public function getCvStats(): array
    {
        $row = $this->createQueryBuilder('pro')
            ->select("
                SUM(CASE WHEN pro.isVerified = true THEN 1 ELSE 0 END) as verified,
                SUM(CASE WHEN pro.isVerified = false AND pro.verifiedAt IS NULL THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN pro.isVerified = false AND pro.verifiedAt IS NOT NULL THEN 1 ELSE 0 END) as rejected
            ")
            ->getQuery()
            ->getOneOrNullResult();

        return [
            'pending' => (int) ($row['pending'] ?? 0),
            'verified' => (int) ($row['verified'] ?? 0),
            'rejected' => (int) ($row['rejected'] ?? 0),
        ];
    }

    public function findStatusLabelByUserId(int $userId): ?string
    {
        return $this->createQueryBuilder('pp')
            ->select('sp.status')
            ->leftJoin('pp.statusProfile', 'sp')
            ->andWhere('pp.user = :uid')
            ->setParameter('uid', $userId)
            ->getQuery()
            ->getOneOrNullResult(\Doctrine\ORM\Query::HYDRATE_SINGLE_SCALAR);
    }

    /**
     * Recherche SQL MySQL/MariaDB par boîte géographique, puis formule de Haversine.
     * La profession principale et les compétences secondaires sont prises en compte.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findNearbyTalents(
        float $latitude,
        float $longitude,
        float $radiusKm,
        ?int $professionId = null,
        string $q = '',
        string $city = '',
        int $limit = 100,
    ): array {
        $limit = max(1, min(200, $limit));

        // Préfiltre rectangulaire : évite de calculer la distance sur toute la table.
        $latitudeDelta = $radiusKm / 111.045;
        $cosLatitude = max(0.01, abs(cos(deg2rad($latitude))));
        $longitudeDelta = $radiusKm / (111.045 * $cosLatitude);

        $sql = <<<'SQL'
            SELECT
                pro.id,
                u.id AS user_id,
                pro.latitude,
                pro.longitude,
                pro.location_updated_at,
                pro.experience_years,
                personal.full_name,
                personal.slug,
                personal.city,
                personal.photo,
                profession.id AS profession_id,
                profession.profession AS profession_name,
                country.country AS country_name,
                (
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
                ) AS distance_km
            FROM professional_profile pro
            INNER JOIN `user` u ON u.id = pro.user_id
            LEFT JOIN personal_profile personal ON personal.user_id = u.id
            LEFT JOIN profession profession ON profession.id = pro.profession_id
            LEFT JOIN country country ON country.id = u.country_id
            WHERE pro.geolocation_enabled = 1
              AND pro.latitude IS NOT NULL
              AND pro.longitude IS NOT NULL
              AND pro.latitude BETWEEN :minLatitude AND :maxLatitude
              AND pro.longitude BETWEEN :minLongitude AND :maxLongitude
              AND pro.is_verified = 1
              AND u.is_active = 1
              AND personal.full_name IS NOT NULL
              AND TRIM(personal.full_name) <> ''
              AND LOWER(personal.full_name) <> LOWER(u.email)
              AND personal.photo IS NOT NULL
              AND TRIM(personal.photo) <> ''
              AND LOWER(personal.photo) NOT IN ('avatar.png', 'default-avatar.png', 'default.png')
        SQL;

        $params = [
            'latitude' => (string) $latitude,
            'longitude' => (string) $longitude,
            'radius' => (string) $radiusKm,
            'minLatitude' => (string) ($latitude - $latitudeDelta),
            'maxLatitude' => (string) ($latitude + $latitudeDelta),
            'minLongitude' => (string) ($longitude - $longitudeDelta),
            'maxLongitude' => (string) ($longitude + $longitudeDelta),
        ];

        $types = [
            'latitude' => ParameterType::STRING,
            'longitude' => ParameterType::STRING,
            'radius' => ParameterType::STRING,
            'minLatitude' => ParameterType::STRING,
            'maxLatitude' => ParameterType::STRING,
            'minLongitude' => ParameterType::STRING,
            'maxLongitude' => ParameterType::STRING,
        ];

        if ($professionId !== null) {
            $sql .= <<<'SQL'

              AND (
                    pro.profession_id = :professionId
                    OR EXISTS (
                        SELECT 1
                        FROM professional_profile_profession_skill skill_link
                        WHERE skill_link.professional_profile_id = pro.id
                          AND skill_link.profession_id = :professionId
                    )
              )
            SQL;
            $params['professionId'] = $professionId;
            $types['professionId'] = ParameterType::INTEGER;
        }

        $q = trim($q);
        if ($q !== '') {
            $sql .= <<<'SQL'

              AND (
                    personal.full_name LIKE :searchTerm
                    OR profession.profession LIKE :searchTerm
                    OR EXISTS (
                        SELECT 1
                        FROM professional_profile_profession_skill search_skill_link
                        INNER JOIN profession search_skill
                            ON search_skill.id = search_skill_link.profession_id
                        WHERE search_skill_link.professional_profile_id = pro.id
                          AND search_skill.profession LIKE :searchTerm
                    )
              )
            SQL;
            $params['searchTerm'] = '%' . $q . '%';
            $types['searchTerm'] = ParameterType::STRING;
        }

        $city = trim($city);
        if ($city !== '') {
            $sql .= <<<'SQL'

              AND (
                    personal.city LIKE :cityTerm
                    OR personal.adress LIKE :cityTerm
                    OR country.country LIKE :cityTerm
              )
            SQL;
            $params['cityTerm'] = '%' . $city . '%';
            $types['cityTerm'] = ParameterType::STRING;
        }

        $sql .= sprintf(
            ' HAVING distance_km <= :radius ORDER BY distance_km ASC LIMIT %d',
            $limit
        );

        return $this->getEntityManager()
            ->getConnection()
            ->executeQuery($sql, $params, $types)
            ->fetchAllAssociative();
    }

    /**
     * Paginer les talents dont le profil possède une identité et une photo publiques réelles.
     *
     * @return array{items: array<int, ProfessionalProfile>, total: int, page: int, limit: int, pages: int}
     */
    public function paginateTalents(?User $me, string $q, string $sort, int $page = 1, int $limit = 10): array
    {
        $page = max(1, $page);
        $limit = max(1, min(10, $limit));
        $offset = ($page - 1) * $limit;

        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.user', 'u')->addSelect('u')
            ->innerJoin('u.personalProfile', 'pp')->addSelect('pp')
            ->andWhere('pp.id IS NOT NULL')
            // Un profil public doit avoir une vraie identité et une vraie photo.
            ->andWhere('pp.fullName IS NOT NULL')
            ->andWhere("TRIM(pp.fullName) NOT IN ('', '-', '—')")
            ->andWhere("LOWER(TRIM(pp.fullName)) <> 'profil sans nom'")
            ->andWhere("pp.fullName NOT LIKE '%@%'")
            ->andWhere('LOWER(pp.fullName) <> LOWER(u.email)')
            ->andWhere('pp.photo IS NOT NULL')
            ->andWhere("TRIM(pp.photo) <> ''")
            ->andWhere("LOWER(pp.photo) NOT IN ('avatar.png', 'default-avatar.png', 'default.png')")
            ->andWhere("LOWER(pp.photo) NOT LIKE '%/avatar.png'")
            ->andWhere("LOWER(pp.photo) NOT LIKE '%/default-avatar.png'")
            ->andWhere("LOWER(pp.photo) NOT LIKE '%/default.png'")
            // Le ProfessionalProfile du talent doit réellement être renseigné.
            ->andWhere('p.profession IS NOT NULL')
            ->andWhere("TRIM(p.bio) <> ''")
            ->andWhere("TRIM(p.experience) <> ''")
            ->andWhere("TRIM(p.cv) <> ''")

            // Moyenne des avis publiés du talent
            ->addSelect('(
                SELECT COALESCE(AVG(r2.globalScore), 0)
                FROM App\Entity\Review r2
                WHERE r2.target = u
                AND r2.published = true
            ) AS HIDDEN avgRating');

        if ($me) {
            $qb->andWhere('p.user != :me')
                ->setParameter('me', $me);
        }

        $q = trim($q);
        if ($q !== '') {
            $qb->andWhere('pp.fullName LIKE :q')
            ->setParameter('q', '%' . $q . '%');
        }

        // Les mieux notés d'abord
        $qb->addOrderBy('avgRating', 'DESC');

        // Puis le tri demandé
        match ($sort) {
            'salary_desc' => $qb->addOrderBy('p.salaryMin', 'DESC'),
            'salary_asc'  => $qb->addOrderBy('p.salaryMin', 'ASC'),
            'exp_desc'    => $qb->addOrderBy('p.experienceYears', 'DESC'),
            default       => $qb->addOrderBy('p.createdAt', 'DESC'),
        };

        $qb->setFirstResult($offset)
            ->setMaxResults($limit);

        $paginator = new Paginator($qb, true);
        $total = count($paginator);

        return [
            'items' => iterator_to_array($paginator),
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => (int) max(1, ceil($total / $limit)),
        ];
    }

    public function countUnverifiedProfiles(): int
    {
        return (int) $this->createQueryBuilder('pp')
            ->select('COUNT(pp.id)')
            ->andWhere('pp.isVerified = :v')
            ->setParameter('v', false)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return ProfessionalProfile[] */
    public function findPendingVerification(int $limit = 10): array
    {
        return $this->createQueryBuilder('pp')
            ->andWhere('pp.isVerified = :v')
            ->setParameter('v', false)
            ->orderBy('pp.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
