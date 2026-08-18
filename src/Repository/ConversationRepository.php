<?php
// src/Repository/ConversationRepository.php

namespace App\Repository;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\PersonalProfile;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

class ConversationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, protected EntityManagerInterface $em)
    {
        parent::__construct($registry, Conversation::class);
    }

    /**
     * Liste des conversations de l'utilisateur (triée par dernier message).
     */
    public function findForUser(User $me, ?int $limit = 30): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('(c.participantA = :me OR c.participantB = :me)')
            ->setParameter('me', $me)
            ->orderBy('c.lastMessageAt', 'DESC')
            ->addOrderBy('c.updatedAt', 'DESC')
            ->setMaxResults($limit ?? 30)
            ->getQuery()
            ->getResult();
    }

     public function findBetweenUsers(User $a, User $b): ?Conversation
    {
        return $this->findOneBy([
            'participantA' => $a,
            'participantB' => $b,
        ]);
    }

    /**
     * Get-or-create conversation entre 2 users (en respectant ton tri petit/grand id).
     */
    public function findOrCreateBetween(User $u1, User $u2): Conversation
    {
        $em = $this->getEntityManager();

        $a = $u1->getId() <= $u2->getId() ? $u1 : $u2;
        $b = $u1->getId() <= $u2->getId() ? $u2 : $u1;

        $existing = $this->createQueryBuilder('c')
            ->andWhere('c.participantA = :a AND c.participantB = :b')
            ->setParameter('a', $a)
            ->setParameter('b', $b)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($existing) return $existing;

        $conv = (new Conversation())
            ->setParticipantA($a)
            ->setParticipantB($b);

        $em->persist($conv);
        $em->flush();

        return $conv;
    }


    /**
     * Compte le nombre de talents distincts contactés par un utilisateur.
     * @param User $user
     * @return int
     */
    public function countDistinctTalentsContacted(User $user): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(DISTINCT pp.id)')
            ->leftJoin('c.professionalProfile', 'pp')
            ->andWhere('c.participantA = :u OR c.participantB = :u')
            ->setParameter('u', $user)
            ->andWhere('pp.id IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }


    /**
     * Récupère les conversations récentes d’un utilisateur (participant A ou B).
     * @param User $user
     * @param int $limit
     * @return array
     */
    public function findRecentForClient(User $user, int $limit): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.participantA = :u OR c.participantB = :u')
            ->setParameter('u', $user)
            ->orderBy('c.lastMessageAt', 'DESC')
            ->addOrderBy('c.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }


   /**
     * Compte le nombre de talents distincts contactés par une entreprise (profil).
     * Ici : une entreprise est un User (via PersonalProfile->getUser()) participant A ou B.
     * On compte les ProfessionalProfile distincts liés aux conversations.
     * @param PersonalProfile $company
     * @return int
     */
    public function countDistinctTalentsContactedByCompanyProfile(PersonalProfile $company): int
    {
        $user = $company->getUser();

        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(DISTINCT pp.id)')
            ->leftJoin('c.professionalProfile', 'pp')
            ->andWhere('(c.participantA = :u OR c.participantB = :u)')
            ->andWhere('pp.id IS NOT NULL')
            ->setParameter('u', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * ✅ 3 conversations les plus récentes de l'utilisateur
     * + dernier message (via sous-requête MAX(createdAt))
     * + unreadCount (messages non lus envoyés par l'autre)
     *
     * Retour: array<array{conversation: Conversation, lastMessage: ?Message, unreadCount: int}>
     */
    public function findRecentWithLastMessageForUser(User $me, int $limit = 3): array
    {
        // 1) Conversations récentes
        $conversations = $this->createQueryBuilder('c')
            ->andWhere('c.participantA = :me OR c.participantB = :me')
            ->setParameter('me', $me)
            ->orderBy('c.lastMessageAt', 'DESC')
            ->addOrderBy('c.updatedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        if (!$conversations) {
            return [];
        }

        $ids = array_map(static fn(Conversation $c) => $c->getId(), $conversations);

        // 2) Derniers messages (1er rencontré par conversation)
        $msgRows = $this->em->createQueryBuilder()
            ->select('m', 'IDENTITY(m.conversation) AS cid')
            ->from(\App\Entity\Message::class, 'm')
            ->andWhere('m.conversation IN (:ids)')
            ->andWhere('m.deletedAt IS NULL')
            ->setParameter('ids', $ids)
            ->orderBy('m.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        $lastByConversationId = [];
        foreach ($msgRows as $row) {
            // Doctrine renvoie souvent: [0 => Message, 'cid' => 123]
            $m = $row[0] ?? null;
            $cid = (int)($row['cid'] ?? 0);
            if ($m && $cid && !isset($lastByConversationId[$cid])) {
                $lastByConversationId[$cid] = $m;
            }
        }

        // 3) Unread count par conversation
        $unreadRows = $this->em->createQueryBuilder()
            ->select('IDENTITY(m2.conversation) AS cid, COUNT(m2.id) AS cnt')
            ->from(\App\Entity\Message::class, 'm2')
            ->andWhere('m2.conversation IN (:ids)')
            ->andWhere('m2.deletedAt IS NULL')
            ->andWhere('m2.readAt IS NULL')
            ->andWhere('m2.sender != :me')
            ->setParameter('ids', $ids)
            ->setParameter('me', $me)
            ->groupBy('cid')
            ->getQuery()
            ->getArrayResult();

        $unreadByConversationId = [];
        foreach ($unreadRows as $r) {
            $unreadByConversationId[(int)$r['cid']] = (int)$r['cnt'];
        }

        // 4) Format final (dans le même ordre que $conversations)
        $out = [];
        foreach ($conversations as $c) {
            $cid = (int)$c->getId();
            $out[] = [
                'conversation' => $c,
                'lastMessage'  => $lastByConversationId[$cid] ?? null,
                'unreadCount'  => $unreadByConversationId[$cid] ?? 0,
            ];
        }

        return $out;
    }


    /**
     * ✅ Nombre total de contacts (1 conversation = 1 contact grâce à ton duo unique A/B)
     */
    public function countContactsForUser(User $me): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.participantA = :me OR c.participantB = :me')
            ->setParameter('me', $me)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * ✅ Contacts paginés pour la page /contacts
     * - charge les 2 participants
     * - charge le ProfessionalProfile si lié
     * - trie par activité récente
     */
    public function findContactsForUserPaginated(User $me, int $page = 1, int $limit = 12): array
    {
        $page = max(1, $page);
        $limit = max(1, $limit);
        $offset = ($page - 1) * $limit;

        return $this->createQueryBuilder('c')
            ->leftJoin('c.participantA', 'a')->addSelect('a')
            ->leftJoin('c.participantB', 'b')->addSelect('b')
            ->leftJoin('c.professionalProfile', 'pp')->addSelect('pp')
            ->leftJoin('pp.user', 'ppu')->addSelect('ppu')
            ->andWhere('c.participantA = :me OR c.participantB = :me')
            ->setParameter('me', $me)
            ->orderBy('c.lastMessageAt', 'DESC')
            ->addOrderBy('c.updatedAt', 'DESC')
            ->addOrderBy('c.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * ✅ Pour le dashboard (petite liste récente)
     */
    public function findRecentContactsForDashboard(User $me, int $limit = 5): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.participantA', 'a')->addSelect('a')
            ->leftJoin('c.participantB', 'b')->addSelect('b')
            ->leftJoin('c.professionalProfile', 'pp')->addSelect('pp')
            ->leftJoin('pp.user', 'ppu')->addSelect('ppu')
            ->andWhere('c.participantA = :me OR c.participantB = :me')
            ->setParameter('me', $me)
            ->orderBy('c.lastMessageAt', 'DESC')
            ->addOrderBy('c.updatedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * ✅ Nombre de conversations avec messages non lus (pour stats Contacts)
     */
    public function countContactsWithUnread(User $me): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(DISTINCT c.id)')
            ->innerJoin('c.messages', 'm')
            ->andWhere('c.participantA = :me OR c.participantB = :me')
            ->andWhere('m.sender != :me')
            ->andWhere('m.readAt IS NULL')
            ->andWhere('m.deletedAt IS NULL')
            ->setParameter('me', $me)
            ->getQuery()
            ->getSingleScalarResult();
    }


    // ✅ Cherche une conversation existante entre 2 utilisateurs (dans n'importe quel ordre)
    public function findOneBetweenUsers(User $u1, User $u2): ?Conversation
    {
        return $this->createQueryBuilder('c')
            ->andWhere('(c.participantA = :u1 AND c.participantB = :u2) OR (c.participantA = :u2 AND c.participantB = :u1)')
            ->setParameter('u1', $u1)
            ->setParameter('u2', $u2)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }


    /** Conversations récentes */
    public function findRecentForUser(User $me, int $limit = 12): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.participantA', 'a')->addSelect('a')
            ->leftJoin('c.participantB', 'b')->addSelect('b')
            ->leftJoin('c.professionalProfile', 'pp')->addSelect('pp')
            ->andWhere('c.participantA = :me OR c.participantB = :me')
            ->setParameter('me', $me)
            ->orderBy('c.lastMessageAt', 'DESC')
            ->addOrderBy('c.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** Conversations à relancer (dernier message ancien / aucun message) */
    public function findToRelance(User $me, \DateTimeInterface $cutoff, int $limit = 10): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.participantA', 'a')->addSelect('a')
            ->leftJoin('c.participantB', 'b')->addSelect('b')
            ->leftJoin('c.professionalProfile', 'pp')->addSelect('pp')
            ->andWhere('c.participantA = :me OR c.participantB = :me')
            ->andWhere('(
                (c.lastMessageAt IS NULL AND c.createdAt <= :cutoff)
                OR
                (c.lastMessageAt IS NOT NULL AND c.lastMessageAt <= :cutoff)
            )')
            ->setParameter('me', $me)
            ->setParameter('cutoff', $cutoff)
            ->orderBy('c.lastMessageAt', 'ASC')
            ->addOrderBy('c.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
