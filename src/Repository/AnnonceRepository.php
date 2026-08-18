<?php

namespace App\Repository;

use App\Entity\Annonce;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Annonce>
 */
class AnnonceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Annonce::class);
    }

    /** @return Annonce[] */
    public function findForUser(User $user): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.categorie', 'c')->addSelect('c')
            ->leftJoin('a.profession', 'p')->addSelect('p')
            ->andWhere('a.user = :user')
            ->setParameter('user', $user)
            ->orderBy('a.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return Annonce[] */
    public function findForModeration(): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.categorie', 'c')->addSelect('c')
            ->leftJoin('a.profession', 'p')->addSelect('p')
            ->leftJoin('a.user', 'u')->addSelect('u')
            ->leftJoin('u.personalProfile', 'pp')->addSelect('pp')
            ->leftJoin('a.publiePar', 'publisher')->addSelect('publisher')
            ->orderBy('a.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param array{q?: string, city?: string} $filters
     * @return array{items: Annonce[], total: int, page: int, pages: int, limit: int}
     */
    public function searchPublic(array $filters, int $page = 1, int $limit = 12): array
    {
        $page = max(1, $page);
        $limit = max(1, min(24, $limit));

        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.categorie', 'c')->addSelect('c')
            ->leftJoin('a.profession', 'p')->addSelect('p')
            ->leftJoin('a.user', 'u')->addSelect('u')
            ->leftJoin('u.personalProfile', 'pp')->addSelect('pp')
            ->andWhere('a.afficherMaintenant = :requested')
            ->andWhere('a.publier = :published')
            ->setParameter('requested', true)
            ->setParameter('published', true);

        $query = trim(mb_substr((string) ($filters['q'] ?? ''), 0, 160));
        if ($query !== '') {
            $terms = preg_split('/\s+/u', mb_strtolower($query), -1, PREG_SPLIT_NO_EMPTY) ?: [];

            foreach (array_slice(array_unique($terms), 0, 6) as $index => $term) {
                $parameter = 'query_' . $index;
                $qb->andWhere($qb->expr()->orX(
                    'LOWER(a.titre) LIKE :' . $parameter,
                    'LOWER(a.description) LIKE :' . $parameter,
                    'LOWER(a.ville) LIKE :' . $parameter,
                    'LOWER(c.nom) LIKE :' . $parameter,
                    'LOWER(p.profession) LIKE :' . $parameter,
                    'LOWER(pp.fullName) LIKE :' . $parameter
                ))->setParameter($parameter, '%' . $term . '%');
            }
        }

        $city = trim(mb_substr((string) ($filters['city'] ?? ''), 0, 120));
        if ($city !== '') {
            $qb->andWhere('LOWER(a.ville) LIKE :city')
                ->setParameter('city', '%' . mb_strtolower($city) . '%');
        }

        $countQb = clone $qb;
        $total = (int) $countQb
            ->select('COUNT(DISTINCT a.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $pages = max(1, (int) ceil($total / $limit));
        $page = min($page, $pages);

        // Ajouté après la requête de comptage afin que les paramètres propres
        // au boost ne soient pas transmis à un COUNT qui ne les utilise pas.
        $qb->addSelect('(
            SELECT COALESCE(MAX(activeBoost.niveauPriorite), 0)
            FROM App\Entity\BoosteAnnonce activeBoost
            WHERE activeBoost.annonce = a
              AND activeBoost.statut = :activeBoostStatus
              AND activeBoost.dateDebut <= :boostNow
              AND activeBoost.dateFin > :boostNow
        ) AS HIDDEN activeBoostPriority')
            ->setParameter('activeBoostStatus', 'actif')
            ->setParameter('boostNow', new \DateTimeImmutable());

        $items = $qb
            ->orderBy('activeBoostPriority', 'DESC')
            ->addOrderBy('a.dateCreation', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'limit' => $limit,
        ];
    }

    /**
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
                a.id,
                a.slug,
                a.titre,
                a.description,
                a.ville,
                a.salaire_journalier,
                a.date_creation,
                profession.profession AS profession_name,
                personal.full_name,
                pro.latitude,
                pro.longitude,
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
            FROM annonce a
            INNER JOIN `user` u ON u.id = a.user_id
            INNER JOIN professional_profile pro ON pro.user_id = u.id
            LEFT JOIN personal_profile personal ON personal.user_id = u.id
            LEFT JOIN profession profession ON profession.id = a.profession_id
            WHERE a.afficher_maintenant = 1
              AND a.publier = 1
              AND u.is_active = 1
              AND pro.geolocation_enabled = 1
              AND pro.latitude IS NOT NULL
              AND pro.longitude IS NOT NULL
              AND pro.latitude BETWEEN :minLatitude AND :maxLatitude
              AND pro.longitude BETWEEN :minLongitude AND :maxLongitude
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
        $types = array_fill_keys(array_keys($params), ParameterType::STRING);

        $q = trim($q);
        if ($q !== '') {
            $sql .= <<<'SQL'

              AND (
                    a.titre LIKE :searchTerm
                    OR a.description LIKE :searchTerm
                    OR a.ville LIKE :searchTerm
                    OR profession.profession LIKE :searchTerm
                    OR personal.full_name LIKE :searchTerm
              )
            SQL;
            $params['searchTerm'] = '%' . $q . '%';
            $types['searchTerm'] = ParameterType::STRING;
        }

        $city = trim($city);
        if ($city !== '') {
            $sql .= <<<'SQL'

              AND (a.ville LIKE :cityTerm OR personal.city LIKE :cityTerm)
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

    /** @return list<string> */
    public function findDistinctCitiesForSuggestions(int $limit = 300): array
    {
        $rows = $this->createQueryBuilder('annonceSuggestion')
            ->select('DISTINCT annonceSuggestion.ville AS value')
            ->andWhere('annonceSuggestion.ville IS NOT NULL')
            ->andWhere("TRIM(annonceSuggestion.ville) <> ''")
            ->orderBy('annonceSuggestion.ville', 'ASC')
            ->setMaxResults(max(1, min(1000, $limit)))
            ->getQuery()
            ->getArrayResult();

        return array_values(array_filter(array_map(
            static fn (array $row): string => trim((string) ($row['value'] ?? '')),
            $rows
        )));
    }

}
