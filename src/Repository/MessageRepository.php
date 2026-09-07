<?php
// src/Repository/MessageRepository.php

namespace App\Repository;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\PersonalProfile;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    public function findBetweenUsers(int $aId, int $bId, int $limit = 500): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('(m.sender = :a AND m.receiver = :b) OR (m.sender = :b AND m.receiver = :a)')
            ->setParameter('a', $aId)
            ->setParameter('b', $bId)
            ->orderBy('m.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findConversationBetweenUsers(User $userA, User $userB): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.conversation', 'c')->addSelect('c')
            ->leftJoin('m.sender', 's')->addSelect('s')
            ->andWhere(
                '(c.participantA = :userA AND c.participantB = :userB) 
                 OR 
                 (c.participantA = :userB AND c.participantB = :userA)'
            )
            ->andWhere('m.deletedAt IS NULL')
            ->setParameter('userA', $userA)
            ->setParameter('userB', $userB)
            ->orderBy('m.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
    
    /**
     * ✅ Pagination des messages d'une conversation (style Messenger).
     *
     * Objectif:
     * - Charger les derniers messages
     * - Puis (scroll vers le haut) charger les messages plus anciens via $beforeId
     *
     * Détails:
     * - On récupère en DESC (plus rapide pour les "derniers")
     * - On renvoie en ASC pour un affichage naturel (ancien -> récent)
     * - On ignore les messages soft-deleted (deletedAt IS NULL)
     */
    public function paginateConversationMessages(
        Conversation $conversation,
        int $limit = 30,
        ?int $beforeId = null
    ): array {
        $qb = $this->createQueryBuilder('m')
            ->andWhere('m.conversation = :c')
            ->setParameter('c', $conversation)
            ->andWhere('m.deletedAt IS NULL')
            ->orderBy('m.id', 'DESC')
            ->setMaxResults($limit);

        if ($beforeId !== null) {
            $qb->andWhere('m.id < :beforeId')
               ->setParameter('beforeId', $beforeId);
        }

        $rows = $qb->getQuery()->getResult();

        // On renvoie en ASC (ancien -> récent)
        return array_reverse($rows);
    }

    /**
     * ✅ Compter le total global des messages non lus (toutes conversations) pour $me.
     *
     * Non lu = message envoyé par l'autre + readAt IS NULL + conversation où je suis participant.
     *
     * ⚠️ Cette méthode sert pour ton badge rouge global.
     */
    public function countUnreadForUser(User $me): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->join('m.conversation', 'c')
            ->andWhere('m.sender != :me')
            ->andWhere('m.readAt IS NULL')
            ->andWhere('m.deletedAt IS NULL')
            ->andWhere('c.participantA = :me OR c.participantB = :me')
            ->setParameter('me', $me)
            ->getQuery()
            ->getSingleScalarResult();
    }


    /**
    * Compte le nombre de messages liés aux conversations d’un utilisateur (participant A ou B).
    * @param User $user
    * @return int
    */
    public function countActiveMessagesForClient(User $user): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->leftJoin('m.conversation', 'c')
            ->andWhere('c.participantA = :u OR c.participantB = :u')
            ->setParameter('u', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    
    /**
     * Récupère les messages récents liés aux conversations d'une entreprise (profil).
     * @param PersonalProfile $company
     * @param int $limit
     * @return array
     */
    public function findRecentForCompanyProfile(PersonalProfile $company, int $limit): array
    {
        $user = $company->getUser();

        return $this->createQueryBuilder('m')
            ->leftJoin('m.conversation', 'cv')->addSelect('cv')
            ->andWhere('(cv.participantA = :u OR cv.participantB = :u)')
            ->setParameter('u', $user)
            ->orderBy('m.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }


    /**
     * ✅ Retourne un map [conversationId => unreadCount]
     * pour éviter le N+1 dans le controller.
     */
    public function getUnreadCountsByConversationIdsForUser(array $conversationIds, User $me): array
    {
        if ($conversationIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('m')
            ->select('IDENTITY(m.conversation) AS conversationId, COUNT(m.id) AS unreadCount')
            ->andWhere('IDENTITY(m.conversation) IN (:ids)')
            ->andWhere('m.sender != :me')
            ->andWhere('m.readAt IS NULL')
            ->andWhere('m.deletedAt IS NULL')
            ->setParameter('ids', $conversationIds)
            ->setParameter('me', $me)
            ->groupBy('m.conversation')
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['conversationId']] = (int) $row['unreadCount'];
        }

        return $map;
    }

    /**
     * ✅ Dernier message (objet Message) par conversation
     * basé sur MAX(id) (pratique et rapide si ID auto-increment)
     * Retourne un map [conversationId => Message]
     */
    public function getLastMessagesByConversationIds(array $conversationIds): array
    {
        if ($conversationIds === []) {
            return [];
        }

        $subQb = $this->getEntityManager()->createQueryBuilder()
            ->select('MAX(m2.id)')
            ->from(Message::class, 'm2')
            ->where('IDENTITY(m2.conversation) IN (:ids)')
            ->andWhere('m2.deletedAt IS NULL')
            ->andWhere('m2.deleted = false')
            ->groupBy('m2.conversation');

        $messages = $this->createQueryBuilder('m')
            ->leftJoin('m.sender', 's')->addSelect('s')
            ->andWhere('m.id IN (' . $subQb->getDQL() . ')')
            ->andWhere('m.deletedAt IS NULL')
            ->andWhere('m.deleted = false')
            ->setParameter('ids', $conversationIds)
            ->getQuery()
            ->getResult();

        $map = [];
        foreach ($messages as $message) {
            /** @var Message $message */
            $convId = $message->getConversation()?->getId();
            if ($convId) {
                $map[$convId] = $message;
            }
        }

        return $map;
    }

    //////////////////////////////////

    /**
     * Messages d'une conversation
     * - tri ASC pour affichage chat
     * - exclut les messages supprimés
     * - peut exclure les archivés
     */
    public function findForConversation(
        Conversation $conversation,
        int $limit = 50,
        bool $includeArchived = true
    ): array {
        $qb = $this->createQueryBuilder('m')
            ->leftJoin('m.sender', 'sender')->addSelect('sender')
            ->leftJoin('m.replyTo', 'replyTo')->addSelect('replyTo')
            ->andWhere('m.conversation = :conversation')
            ->andWhere('m.deletedAt IS NULL')
            ->andWhere('m.deleted = false')
            ->setParameter('conversation', $conversation)
            ->orderBy('m.createdAt', 'ASC')
            ->setMaxResults(max(1, min(200, $limit)));

        if (!$includeArchived) {
            $qb->andWhere('m.archived = false');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Dernier message d'une conversation
     */
    public function findLastMessageForConversation(
        Conversation $conversation,
        bool $includeArchived = true
    ): ?Message {
        $qb = $this->createQueryBuilder('m')
            ->andWhere('m.conversation = :conversation')
            ->andWhere('m.deletedAt IS NULL')
            ->setParameter('conversation', $conversation)
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults(1);

        if ($this->hasField('deleted')) {
            $qb->andWhere('m.deleted = false');
        }

        if (!$includeArchived && $this->hasField('archived')) {
            $qb->andWhere('m.archived = false');
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * Compte les messages non lus dans une conversation
     * pour un utilisateur donné
     */
    public function countUnreadForConversation(
        Conversation $conversation,
        User $me,
        bool $includeArchived = true
    ): int {
        $qb = $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.conversation = :conversation')
            ->andWhere('m.sender != :me')
            ->andWhere('m.readAt IS NULL')
            ->andWhere('m.deletedAt IS NULL')
            ->setParameter('conversation', $conversation)
            ->setParameter('me', $me);

        if ($this->hasField('deleted')) {
            $qb->andWhere('m.deleted = false');
        }

        if (!$includeArchived && $this->hasField('archived')) {
            $qb->andWhere('m.archived = false');
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Marque comme livrés tous les messages entrants
     * non encore livrés
     */
    public function markIncomingAsDelivered(
        Conversation $conversation,
        User $me
    ): int {
        $qb = $this->getEntityManager()->createQueryBuilder()
            ->update(Message::class, 'm')
            ->set('m.deliveredAt', ':now')
            ->where('m.conversation = :conversation')
            ->andWhere('m.sender != :me')
            ->andWhere('m.deliveredAt IS NULL')
            ->andWhere('m.deletedAt IS NULL')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('conversation', $conversation)
            ->setParameter('me', $me);

        if ($this->hasField('deleted')) {
            $qb->andWhere('m.deleted = false');
        }

        if ($this->hasField('archived')) {
            $qb->andWhere('m.archived = false');
        }

        return $qb->getQuery()->execute();
    }

    /**
     * Marque comme lus tous les messages entrants
     * non encore lus
     */
    public function markIncomingAsRead(
        Conversation $conversation,
        User $me
    ): int {
        $qb = $this->getEntityManager()->createQueryBuilder()
            ->update(Message::class, 'm')
            ->set('m.readAt', ':now')
            ->set('m.deliveredAt', 'COALESCE(m.deliveredAt, :now)')
            ->where('m.conversation = :conversation')
            ->andWhere('m.sender != :me')
            ->andWhere('m.readAt IS NULL')
            ->andWhere('m.deletedAt IS NULL')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('conversation', $conversation)
            ->setParameter('me', $me);

        if ($this->hasField('deleted')) {
            $qb->andWhere('m.deleted = false');
        }

        if ($this->hasField('archived')) {
            $qb->andWhere('m.archived = false');
        }

        return $qb->getQuery()->execute();
    }

    /**
     * Tous les messages visibles d'une conversation
     * pour les actions bulk (mute/archive/delete/report)
     */
    public function findActiveMessagesForConversation(
        Conversation $conversation
    ): array {
        $qb = $this->createQueryBuilder('m')
            ->andWhere('m.conversation = :conversation')
            ->andWhere('m.deletedAt IS NULL')
            ->setParameter('conversation', $conversation)
            ->orderBy('m.createdAt', 'ASC');

        if ($this->hasField('deleted')) {
            $qb->andWhere('m.deleted = false');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Dernier message entrant visible
     */
    public function findLastIncomingMessageForConversation(
        Conversation $conversation,
        User $me
    ): ?Message {
        $qb = $this->createQueryBuilder('m')
            ->andWhere('m.conversation = :conversation')
            ->andWhere('m.sender != :me')
            ->andWhere('m.deletedAt IS NULL')
            ->setParameter('conversation', $conversation)
            ->setParameter('me', $me)
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults(1);

        if ($this->hasField('deleted')) {
            $qb->andWhere('m.deleted = false');
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * Marquer le dernier entrant comme non lu
     */
    public function markLastIncomingAsUnread(
        Conversation $conversation,
        User $me
    ): bool {
        $message = $this->findLastIncomingMessageForConversation($conversation, $me);

        if (!$message) {
            return false;
        }

        $message->setReadAt(null);

        if ($this->hasField('markedUnread')) {
            $message->setMarkedUnread(true);
        }

        $this->getEntityManager()->flush();

        return true;
    }

    /**
     * Soft delete de tous les messages d'une conversation
     */
    public function softDeleteConversationMessages(Conversation $conversation): int
    {
        $messages = $this->findActiveMessagesForConversation($conversation);
        $count = 0;

        foreach ($messages as $message) {
            $message->softDelete();

            if ($this->hasField('deleted')) {
                $message->setDeleted(true);
            }

            $count++;
        }

        $this->getEntityManager()->flush();

        return $count;
    }

    /**
     * Toggle muted sur tous les messages visibles de la conversation
     */
    public function toggleMutedForConversation(Conversation $conversation): array
    {
        $messages = $this->findActiveMessagesForConversation($conversation);

        if (!$this->hasField('muted') || !$messages) {
            return ['state' => false, 'changed' => 0];
        }

        $allMuted = true;
        foreach ($messages as $message) {
            if (!$message->isMuted()) {
                $allMuted = false;
                break;
            }
        }

        $newState = !$allMuted;
        $changed = 0;

        foreach ($messages as $message) {
            if ($message->isMuted() !== $newState) {
                $message->setMuted($newState);
                $changed++;
            }
        }

        $this->getEntityManager()->flush();

        return ['state' => $newState, 'changed' => $changed];
    }

    /**
     * Toggle archived sur tous les messages visibles de la conversation
     */
    public function toggleArchivedForConversation(Conversation $conversation): array
    {
        $messages = $this->findActiveMessagesForConversation($conversation);

        if (!$this->hasField('archived') || !$messages) {
            return ['state' => false, 'changed' => 0];
        }

        $allArchived = true;
        foreach ($messages as $message) {
            if (!$message->isArchived()) {
                $allArchived = false;
                break;
            }
        }

        $newState = !$allArchived;
        $changed = 0;

        foreach ($messages as $message) {
            if ($message->isArchived() !== $newState) {
                $message->setArchived($newState);
                $changed++;
            }
        }

        $this->getEntityManager()->flush();

        return ['state' => $newState, 'changed' => $changed];
    }

    /**
     * Signaler le dernier message entrant
     */
    public function reportLastIncomingMessage(
        Conversation $conversation,
        User $me,
        ?string $reason = null
    ): ?Message {
        $message = $this->findLastIncomingMessageForConversation($conversation, $me);

        if (!$message) {
            return null;
        }

        if ($this->hasField('reportedAt')) {
            $message->setReportedAt(new \DateTimeImmutable());
        }

        if ($this->hasField('reportReason')) {
            $message->setReportReason(
                $reason && trim($reason) !== ''
                    ? trim($reason)
                    : 'Signalement sans précision'
            );
        }

        $this->getEntityManager()->flush();

        return $message;
    }

    /**
     * Vérifie si le champ existe vraiment dans l'entité
     * pour éviter de casser le projet si tu n'as pas encore migré
     */
    private function hasField(string $field): bool
    {
        return $this->getEntityManager()
            ->getClassMetadata(Message::class)
            ->hasField($field);
    }

}
