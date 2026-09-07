<?php

namespace App\Repository;

use App\Entity\Categorie;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Categorie>
 *
 * @method Categorie|null find($id, $lockMode = null, $lockVersion = null)
 * @method Categorie|null findOneBy(array $criteria, array $orderBy = null)
 * @method Categorie[]    findAll()
 * @method Categorie[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CategorieRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Categorie::class);
    }

    // src/Repository/CategorieRepository.php

    public function findAllWithJobCounts(bool $onlyAvailableJobs = true): array
    {
        $qb = $this->createQueryBuilder('c')
            ->select('c AS categorie')
            ->addSelect('COUNT(DISTINCT j.id) AS total')
            ->leftJoin('c.professions', 'p')
            ->andWhere('c.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('c.id')
            ->orderBy('c.nom', 'ASC');

        if ($onlyAvailableJobs) {
            $qb
                ->leftJoin('p.jobs', 'j', 'WITH', 'j.dateExpirationAt >= :now')
                ->setParameter('now', new \DateTimeImmutable());
        } else {
            $qb->leftJoin('p.jobs', 'j');
        }

        return $qb->getQuery()->getResult();
    }
    


    /**
     * Retourne: [ [0 => Categorie, 'jobsCount' => '12'], ... ]
     */
    public function findWithJobsCount(int $limit = 12, bool $onlyActive = true, bool $onlyNotExpired = true): array
    {
        $qb = $this->createQueryBuilder('c')
            ->innerJoin('c.professions', 'p')
            ->innerJoin('p.jobs', 'j')
            ->addSelect('COUNT(DISTINCT j.id) AS jobsCount')
            ->groupBy('c.id')
            ->orderBy('jobsCount', 'DESC')
            ->setMaxResults($limit);

        if ($onlyActive) {
            $qb->andWhere('c.isActive = :active')
               ->setParameter('active', true);
        }

        if ($onlyNotExpired) {
            $qb->andWhere('j.dateExpirationAt > :now')
               ->setParameter('now', new \DateTimeImmutable());
        }

        return $qb->getQuery()->getResult();
    }
    
    public function findBySearch(string $search = ''): array
    {
        $qb = $this->createQueryBuilder('c');
        
        if ($search) {
            $qb->andWhere('c.nom LIKE :search OR c.description LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }
        
        $categories = $qb->orderBy('c.nom', 'ASC')
            ->addOrderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult();

        // Dans l'administration, la catégorie générique « Autres » doit
        // toujours rester après toutes les catégories spécifiques.
        usort($categories, static function (Categorie $left, Categorie $right): int {
            $normalize = static function (string $name): string {
                $name = mb_strtolower(trim($name));
                return strtr($name, [
                    'à'=>'a', 'â'=>'a', 'ä'=>'a', 'é'=>'e', 'è'=>'e', 'ê'=>'e', 'ë'=>'e',
                    'î'=>'i', 'ï'=>'i', 'ô'=>'o', 'ö'=>'o', 'ù'=>'u', 'û'=>'u', 'ü'=>'u',
                ]);
            };

            $otherNames = ['autre', 'autres', 'other', 'others'];
            $leftName = $normalize((string) $left->getNom());
            $rightName = $normalize((string) $right->getNom());
            $leftIsOther = in_array($leftName, $otherNames, true);
            $rightIsOther = in_array($rightName, $otherNames, true);

            if ($leftIsOther !== $rightIsOther) {
                return $leftIsOther ? 1 : -1;
            }

            $byName = strnatcasecmp($leftName, $rightName);
            return $byName !== 0 ? $byName : ((int) $left->getId() <=> (int) $right->getId());
        });

        return $categories;
    }

    public function deleteMultiple(array $slugs): void
    {
        $this->createQueryBuilder('c')
             ->delete()
             ->where('c.slug IN (:slugs)')
             ->setParameter('slugs', $slugs)
             ->getQuery()
             ->execute();
    }

    //    /**
    //     * @return Categorie[] Returns an array of Categorie objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('c.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Categorie
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

    /** @return Categorie[] */
    public function findUniqueNonEmptyOrdered(bool $onlyActive = false, ?int $includeId = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->andWhere("TRIM(c.nom) <> ''")
            ->orderBy('c.nom', 'ASC')
            ->addOrderBy('c.id', 'ASC');

        if ($onlyActive) {
            $qb->andWhere('c.isActive = :active')->setParameter('active', true);
        }

        $seen = [];
        $clean = [];
        foreach ($qb->getQuery()->getResult() as $categorie) {
            $key = mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $categorie->getNom()) ?? ''));
            if ($key === '') {
                continue;
            }

            if (!isset($seen[$key])) {
                $seen[$key] = count($clean);
                $clean[] = $categorie;
                continue;
            }

            if ($includeId !== null && (int) $categorie->getId() === $includeId) {
                $clean[$seen[$key]] = $categorie;
            }
        }

        // UX mobile : la catégorie générique « Autre(s) » reste toujours en dernier.
        // Les autres catégories conservent leur ordre alphabétique.
        $specific = [];
        $others = [];
        foreach ($clean as $categorie) {
            $name = mb_strtolower(trim((string) $categorie->getNom()));
            $name = strtr($name, ['à'=>'a','â'=>'a','ä'=>'a','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','î'=>'i','ï'=>'i','ô'=>'o','ö'=>'o','ù'=>'u','û'=>'u','ü'=>'u']);
            if (in_array($name, ['autre', 'autres', 'other', 'others'], true)) {
                $others[] = $categorie;
            } else {
                $specific[] = $categorie;
            }
        }

        return array_merge($specific, $others);
    }

    public function findDuplicateByName(string $name, ?int $excludeId = null): ?Categorie
    {
        $qb = $this->createQueryBuilder('c')
            ->andWhere('LOWER(TRIM(c.nom)) = :name')
            ->setParameter('name', mb_strtolower(trim($name)));
        if ($excludeId !== null) {
            $qb->andWhere('c.id <> :excludeId')->setParameter('excludeId', $excludeId);
        }
        return $qb->setMaxResults(1)->getQuery()->getOneOrNullResult();
    }
}
