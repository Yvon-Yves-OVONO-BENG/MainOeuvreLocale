<?php

namespace App\Controller\Web\Chat;

use App\Entity\Notification;
use App\Entity\User;
use App\Repository\MessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class ChatMetaController extends AbstractController
{
    public function __construct(
        private readonly MessageRepository $messages,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/chat/meta/unread', name: 'chat_unread_count', methods: ['GET'])]
    public function unread(): JsonResponse
    {
        $me = $this->requireChatUser();

        return $this->json([
            'ok' => true,
            'unreadCount' => $this->messages->countUnreadForUser($me),
        ]);
    }

    #[Route('/chat/meta/notifications', name: 'chat_notifications_recent', methods: ['GET'])]
    public function notifications(): JsonResponse
    {
        $me = $this->requireChatUser();

        $items = $this->em->getRepository(Notification::class)->findBy(
            ['user' => $me],
            ['createdAt' => 'DESC'],
            12
        );

        return $this->json([
            'ok' => true,
            'items' => array_map(static fn (Notification $notification): array => [
                'id' => $notification->getId(),
                'title' => $notification->getTitle(),
                'message' => $notification->getMessage(),
                'isRead' => $notification->isRead(),
                'createdAt' => $notification->getCreatedAt()?->format('Y-m-d H:i:s'),
            ], $items),
        ]);
    }

    private function requireChatUser(): User
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Utilisateur non connecté.');
        }

        return $user;
    }
}
