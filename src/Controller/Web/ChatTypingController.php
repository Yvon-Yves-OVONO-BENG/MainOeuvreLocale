<?php

namespace App\Controller\Web;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

class ChatTypingController extends AbstractController
{
    #[Route('/chat/conversations/{id}/typing', name: 'chat_typing', methods: ['POST'])]
    public function typing(int $id, Request $request, HubInterface $hub): JsonResponse
    {
        /**
         * @var User
         */
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse([
                'ok' => false,
                'error' => 'Utilisateur non connecté.'
            ], 403);
        }

        $data = json_decode($request->getContent(), true) ?: [];
        $typing = (bool) ($data['typing'] ?? false);

        $update = new Update(
            "chat/conversation/{$id}/typing",
            json_encode([
                'conversationId' => $id,
                'userId' => $user->getId(),
                'typing' => $typing,
            ], JSON_UNESCAPED_UNICODE)
        );

        $hub->publish($update);

        return new JsonResponse([
            'ok' => true,
            'conversationId' => $id,
            'typing' => $typing,
        ]);
    }
}