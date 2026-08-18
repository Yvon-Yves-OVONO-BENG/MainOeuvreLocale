<?php

namespace App\Controller\Web;

use App\Entity\Message;
use App\Entity\User;
use App\Repository\ConversationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/chat/panel', name: 'chat_panel_')]
class ChatPanelMenuAjaxController extends AbstractController
{
    private function requireUser(): User
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Utilisateur non connecté.');
        }

        return $user;
    }

    #[Route('/mark-all-read', name: 'mark_all_read', methods: ['POST'])]
    public function markAllRead(
        ConversationRepository $conversationRepository,
        EntityManagerInterface $em
    ): JsonResponse {
        $me = $this->requireUser();

        $rows = $conversationRepository->createQueryBuilder('c')
            ->select('c.id')
            ->where('c.participantA = :me OR c.participantB = :me')
            ->setParameter('me', $me)
            ->getQuery()
            ->getScalarResult();

        $conversationIds = array_map('intval', array_column($rows, 'id'));

        if (!$conversationIds) {
            return $this->json([
                'ok' => true,
                'updated' => 0,
                'message' => 'Aucune conversation à mettre à jour.',
            ]);
        }

        $now = new \DateTimeImmutable();

        $updated = $em->createQueryBuilder()
            ->update(Message::class, 'm')
            ->set('m.readAt', ':now')
            ->set('m.markedUnread', ':false')
            ->where('IDENTITY(m.conversation) IN (:conversationIds)')
            ->andWhere('m.sender != :me')
            ->andWhere('m.deleted = :false')
            ->andWhere('(m.readAt IS NULL OR m.markedUnread = :true)')
            ->setParameter('now', $now)
            ->setParameter('false', false)
            ->setParameter('true', true)
            ->setParameter('me', $me)
            ->setParameter('conversationIds', $conversationIds)
            ->getQuery()
            ->execute();

        return $this->json([
            'ok' => true,
            'updated' => $updated,
            'message' => 'Toutes les conversations ont été marquées comme lues.',
        ]);
    }

}