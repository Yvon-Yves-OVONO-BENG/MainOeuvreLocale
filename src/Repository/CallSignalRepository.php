<?php

namespace App\Repository;

use App\Entity\CallSession;
use App\Entity\CallSignal;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class CallSignalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, CallSignal::class); }

    /** @return CallSignal[] */
    public function findIncomingAfter(CallSession $call, User $me, int $afterId): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.callSession = :call')
            ->andWhere('s.sender != :me')
            ->andWhere('s.id > :after')
            ->setParameter('call', $call)
            ->setParameter('me', $me)
            ->setParameter('after', max(0, $afterId))
            ->orderBy('s.id', 'ASC')
            ->setMaxResults(100)
            ->getQuery()->getResult();
    }
}
