<?php

namespace App\Controller\Web;

use App\Entity\ChatTypingState;
use App\Entity\Conversation;
use App\Entity\User;
use App\Repository\ChatTypingStateRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/** Indicateur "écrit..." synchronisé par AJAX, sans Mercure. */
final class ChatTypingController extends AbstractController
{
    #[Route('/chat/conversations/{id}/typing', name: 'chat_typing', methods: ['GET', 'POST'])]
    public function typing(
        Conversation $conversation,
        Request $request,
        ChatTypingStateRepository $states,
        EntityManagerInterface $em,
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof User) return $this->json(['ok' => false, 'error' => 'Utilisateur non connecté.'], 401);

        if ($conversation->getParticipantA()?->getId() !== $user->getId() && $conversation->getParticipantB()?->getId() !== $user->getId()) {
            return $this->json(['ok' => false, 'error' => 'Accès refusé.'], 403);
        }

        if ($request->isMethod('GET')) {
            return $this->json([
                'ok' => true,
                'conversationId' => $conversation->getId(),
                'typing' => $states->otherIsTyping($conversation, $user),
            ]);
        }

        $data = json_decode((string) $request->getContent(), true) ?: [];
        $typing = (bool) ($data['typing'] ?? false);
        $state = $states->findOneBy(['conversation' => $conversation, 'user' => $user]);
        if (!$state instanceof ChatTypingState) {
            $state = (new ChatTypingState())->setConversation($conversation)->setUser($user);
            $em->persist($state);
        }
        $state->setTyping($typing);
        $em->flush();

        return $this->json(['ok' => true, 'conversationId' => $conversation->getId(), 'typing' => $typing]);
    }
}
