<?php

namespace App\Repository;

use App\Entity\NewsletterSubscriber;
use App\Entity\Profession;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class NewsletterSubscriberRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NewsletterSubscriber::class);
    }

    /**
     * @return NewsletterSubscriber[]
     */
    public function findActiveSubscribers(): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('n.subscribedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne les abonnés de la profession publiée ainsi que les inscriptions
     * générales (profession NULL), par exemple les visiteurs du footer.
     *
     * @return NewsletterSubscriber[]
     */
    public function findActiveSubscribersForProfession(Profession $profession): array
    {
        return $this->createQueryBuilder('n')
            ->leftJoin('n.profession', 'p')->addSelect('p')
            ->leftJoin('n.user', 'u')->addSelect('u')
            ->andWhere('n.isActive = :active')
            ->andWhere('(n.profession = :profession OR n.profession IS NULL)')
            ->setParameter('active', true)
            ->setParameter('profession', $profession)
            ->orderBy('n.subscribedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByEmail(string $email): ?NewsletterSubscriber
    {
        return $this->findOneBy([
            'email' => mb_strtolower(trim($email)),
        ]);
    }

    public function findOneByToken(string $token): ?NewsletterSubscriber
    {
        return $this->findOneBy([
            'unsubscribeToken' => $token,
        ]);
    }

    /**
     * @return NewsletterSubscriber[]
     */
    public function findAllForAdmin(): array
    {
        return $this->createQueryBuilder('n')
            ->leftJoin('n.user', 'u')->addSelect('u')
            ->leftJoin('u.personalProfile', 'personalProfile')->addSelect('personalProfile')
            ->leftJoin('n.profession', 'p')->addSelect('p')
            ->orderBy('n.subscribedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
