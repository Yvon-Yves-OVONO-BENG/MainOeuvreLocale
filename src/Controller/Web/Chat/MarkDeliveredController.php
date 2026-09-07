<?php

namespace App\Controller\Web\Chat;

use App\Entity\Conversation;
use App\Security\ConversationAccess;
use App\Security\CurrentUser;
use App\Repository\MessageRepository;
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
    ) {}

    public function __invoke(Conversation $conversation): JsonResponse
    {
        // je récupère l'utilisateur connecté
        $me = $this->currentUser->requireUser($this);

        // je vérifie la participation
        $this->access->assertParticipant($conversation, $me);

        // je marque delivered pour les messages entrants
        $changed = $this->messageRepository->markIncomingAsDelivered($conversation, $me);
        // Le front récupère le changement de statut au prochain poll AJAX.

        // je réponds au front
        return $this->json(['ok' => true, 'changed' => $changed]);
    }
}