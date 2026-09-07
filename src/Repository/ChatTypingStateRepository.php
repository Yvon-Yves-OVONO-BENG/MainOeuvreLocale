<?php

namespace App\Repository;

use App\Entity\ChatTypingState;
use App\Entity\Conversation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class ChatTypingStateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, ChatTypingState::class); }

    public function otherIsTyping(Conversation $conversation, User $me): bool
    {
        $state = $this->createQueryBuilder('s')
            ->andWhere('s.conversation = :conversation')
            ->andWhere('s.user != :me')
            ->andWhere('s.typing = true')
            ->andWhere('s.updatedAt >= :fresh')
            ->setParameter('conversation', $conversation)
            ->setParameter('me', $me)
            ->setParameter('fresh', (new \DateTimeImmutable())->modify('-3 seconds'))
            ->orderBy('s.updatedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()->getOneOrNullResult();

        return $state instanceof ChatTypingState;
    }
}
