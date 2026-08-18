<?php

namespace App\Repository;

use App\Entity\Country;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Country>
 *
 * @method Country|null find($id, $lockMode = null, $lockVersion = null)
 * @method Country|null findOneBy(array $criteria, array $orderBy = null)
 * @method Country[]    findAll()
 * @method Country[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CountryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Country::class);
    }

    //    /**
    //     * @return Country[] Returns an array of Country objects
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

    //    public function findOneBySomeField($value): ?Country
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

    /** @return Country[] */
    public function findUniqueNonEmptyOrdered(?int $includeId = null): array
    {
        $rows = $this->createQueryBuilder('c')
            ->andWhere("TRIM(c.country) <> ''")
            ->orderBy('c.country', 'ASC')
            ->addOrderBy('c.id', 'ASC')
            ->getQuery()->getResult();

        $seen = [];
        $clean = [];
        foreach ($rows as $country) {
            $key = mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $country->getCountry()) ?? ''));
            if ($key === '') {
                continue;
            }

            if (!isset($seen[$key])) {
                $seen[$key] = count($clean);
                $clean[] = $country;
                continue;
            }

            // Si le profil pointe déjà vers un doublon historique, conserver
            // cet objet précis dans les choix évite l'erreur de transformation
            // « valeur non valide » tout en n'affichant qu'un seul pays.
            if ($includeId !== null && (int) $country->getId() === $includeId) {
                $clean[$seen[$key]] = $country;
            }
        }
        return $clean;
    }
}
