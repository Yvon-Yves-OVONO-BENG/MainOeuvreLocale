<?php

namespace App\Controller\Web\Chat1;

use App\Entity\Conversation;
use App\Security\ConversationAccess;
use App\Security\CurrentUser;
use App\Repository\MessageRepository;
use App\Realtime\MercurePublisher;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/chat/conversation/{id}/delivered', name: 'chat_mark_delivered', methods: ['POST'])]
final class MarkDeliveredController extends AbstractController
{
    public function __construct(
        private MessageRepository $messageRepository,
        private CurrentUser $currentUser,
        private ConversationAccess $access,
        private MercurePublisher $mercure,
    ) {}

    public function __invoke(Conversation $conversation): JsonResponse
    {
        // je récupère l'utilisateur connecté
        $me = $this->currentUser->requireUser($this);

        // je vérifie la participation
        $this->access->assertParticipant($conversation, $me);

        // je marque delivered pour les messages entrants
        $changed = $this->messageRepository->markIncomingAsDelivered($conversation, $me);

        // je push le status en temps réel
        if ($changed > 0) {
            $topic = sprintf('/conversations/%d/status', $conversation->getId());

            $this->mercure->publish($topic, [
                'type' => 'delivered',
                'conversationId' => $conversation->getId(),
                'byUserId' => $me->getId(),
                'changed' => $changed,
            ]);
        }

        // je réponds au front
        return $this->json(['ok' => true, 'changed' => $changed]);
    }
}