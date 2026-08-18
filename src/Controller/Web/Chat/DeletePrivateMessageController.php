<?php

namespace App\Controller\Web\Chat;

use App\Entity\Message;
use App\Entity\User;
use App\Service\Chat\ChatAccessGuard;
use App\Service\Chat\ChatRealtimeNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class DeletePrivateMessageController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ChatAccessGuard $access,
        private readonly ChatRealtimeNotifier $realtime,
    ) {
    }

    #[Route('/chat/message/{id}/delete', name: 'chat_message_delete', methods: ['POST', 'DELETE'])]
    public function __invoke(Message $message): JsonResponse
    {
        $me = $this->requireChatUser();
        $conversation = $message->getConversation();

        if (!$conversation) {
            return $this->json(['ok' => false, 'error' => 'Conversation introuvable.'], 404);
        }

        $this->access->assertPrivateParticipant($conversation, $me);

        $isOwner = $message->getSender()?->getId() === $me->getId();
        $isModerator = $this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_MODERATOR');

        if (!$isOwner && !$isModerator) {
            return $this->json(['ok' => false, 'error' => 'Vous ne pouvez supprimer que vos messages.'], 403);
        }

        $message->softDelete();
        $this->em->flush();

        $this->realtime->privateMessage((int) $conversation->getId(), (int) $message->getId(), 'message_deleted');

        return $this->json([
            'ok' => true,
            'messageId' => $message->getId(),
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
