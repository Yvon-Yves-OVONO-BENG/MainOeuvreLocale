<?php

namespace App\Repository;

use App\Entity\Message;
use App\Entity\MessageReaction;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MessageReactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MessageReaction::class);
    }

    public function save(MessageReaction $reaction, bool $flush = false): void
    {
        $this->getEntityManager()->persist($reaction);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(MessageReaction $reaction, bool $flush = false): void
    {
        $this->getEntityManager()->remove($reaction);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneByMessageUserAndEmoji(Message $message, User $user, string $emoji): ?MessageReaction
    {
        return $this->createQueryBuilder('mr')
            ->andWhere('mr.message = :message')
            ->andWhere('mr.user = :user')
            ->andWhere('mr.emoji = :emoji')
            ->setParameter('message', $message)
            ->setParameter('user', $user)
            ->setParameter('emoji', $emoji)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByMessage(Message $message): array
    {
        return $this->createQueryBuilder('mr')
            ->leftJoin('mr.user', 'u')->addSelect('u')
            ->andWhere('mr.message = :message')
            ->setParameter('message', $message)
            ->orderBy('mr.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countByMessageAndEmoji(Message $message, string $emoji): int
    {
        return (int) $this->createQueryBuilder('mr')
            ->select('COUNT(mr.id)')
            ->andWhere('mr.message = :message')
            ->andWhere('mr.emoji = :emoji')
            ->setParameter('message', $message)
            ->setParameter('emoji', $emoji)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getGroupedCountsForMessage(Message $message): array
    {
        return $this->createQueryBuilder('mr')
            ->select('mr.emoji AS emoji, COUNT(mr.id) AS total')
            ->andWhere('mr.message = :message')
            ->setParameter('message', $message)
            ->groupBy('mr.emoji')
            ->orderBy('total', 'DESC')
            ->addOrderBy('mr.emoji', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    public function deleteByMessageUserAndEmoji(Message $message, User $user, string $emoji): int
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->delete(MessageReaction::class, 'mr')
            ->andWhere('mr.message = :message')
            ->andWhere('mr.user = :user')
            ->andWhere('mr.emoji = :emoji')
            ->setParameter('message', $message)
            ->setParameter('user', $user)
            ->setParameter('emoji', $emoji)
            ->getQuery()
            ->execute();
    }
}