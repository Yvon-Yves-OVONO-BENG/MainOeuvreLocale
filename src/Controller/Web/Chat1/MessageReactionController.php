<?php

namespace App\Controller\Web\Chat1;

use App\Entity\Message;
use App\Entity\MessageReaction;
use App\Entity\User;
use App\Repository\MessageReactionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/message-reaction', name: 'message_reaction_')]
class MessageReactionController extends AbstractController
{
    #[Route('/{id}/add', name: 'add', methods: ['POST'])]
    public function add(
        Message $message,
        Request $request,
        MessageReactionRepository $messageReactionRepository
    ): JsonResponse {
        $user = $this->getAuthenticatedUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $access = $this->denyIfNotParticipant($message, $user);
        if ($access instanceof JsonResponse) {
            return $access;
        }

        $emoji = $this->extractEmoji($request);
        if ($emoji instanceof JsonResponse) {
            return $emoji;
        }

        $reaction = $messageReactionRepository->findOneByMessageUserAndEmoji($message, $user, $emoji);

        if (!$reaction) {
            $reaction = (new MessageReaction())
                ->setMessage($message)
                ->setUser($user)
                ->setEmoji($emoji);

            $messageReactionRepository->save($reaction, true);
        }

        return $this->json($this->buildReactionResponse(
            $message,
            $messageReactionRepository,
            $emoji,
            'added'
        ));
    }

    #[Route('/{id}/remove', name: 'remove', methods: ['POST'])]
    public function remove(
        Message $message,
        Request $request,
        MessageReactionRepository $messageReactionRepository
    ): JsonResponse {
        $user = $this->getAuthenticatedUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $access = $this->denyIfNotParticipant($message, $user);
        if ($access instanceof JsonResponse) {
            return $access;
        }

        $emoji = $this->extractEmoji($request);
        if ($emoji instanceof JsonResponse) {
            return $emoji;
        }

        $reaction = $messageReactionRepository->findOneByMessageUserAndEmoji($message, $user, $emoji);

        if ($reaction) {
            $messageReactionRepository->remove($reaction, true);
        }

        return $this->json($this->buildReactionResponse(
            $message,
            $messageReactionRepository,
            $emoji,
            'removed'
        ));
    }

    #[Route('/{id}/toggle', name: 'toggle', methods: ['POST'])]
    public function toggle(
        Message $message,
        Request $request,
        MessageReactionRepository $messageReactionRepository
    ): JsonResponse {
        $user = $this->getAuthenticatedUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $access = $this->denyIfNotParticipant($message, $user);
        if ($access instanceof JsonResponse) {
            return $access;
        }

        $emoji = $this->extractEmoji($request);
        if ($emoji instanceof JsonResponse) {
            return $emoji;
        }

        $reaction = $messageReactionRepository->findOneByMessageUserAndEmoji($message, $user, $emoji);

        if ($reaction) {
            $messageReactionRepository->remove($reaction, true);
            $action = 'removed';
            $reactedByMe = false;
        } else {
            $reaction = (new MessageReaction())
                ->setMessage($message)
                ->setUser($user)
                ->setEmoji($emoji);

            $messageReactionRepository->save($reaction, true);
            $action = 'added';
            $reactedByMe = true;
        }

        $response = $this->buildReactionResponse(
            $message,
            $messageReactionRepository,
            $emoji,
            $action
        );
        $response['reactedByMe'] = $reactedByMe;

        return $this->json($response);
    }

    #[Route('/{id}/summary', name: 'summary', methods: ['GET'])]
    public function summary(
        Message $message,
        MessageReactionRepository $messageReactionRepository
    ): JsonResponse {
        $user = $this->getAuthenticatedUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $access = $this->denyIfNotParticipant($message, $user);
        if ($access instanceof JsonResponse) {
            return $access;
        }

        $grouped = $messageReactionRepository->getGroupedCountsForMessage($message);
        $myReactions = $messageReactionRepository->findBy([
            'message' => $message,
            'user' => $user,
        ]);

        $myEmojis = array_map(
            static fn (MessageReaction $reaction): string => (string) $reaction->getEmoji(),
            $myReactions
        );

        return $this->json([
            'ok' => true,
            'messageId' => $message->getId(),
            'counts' => $grouped,
            'myEmojis' => array_values(array_unique($myEmojis)),
        ]);
    }

    private function getAuthenticatedUser(): User|JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json([
                'ok' => false,
                'error' => 'Utilisateur non connecté',
            ], 401);
        }

        return $user;
    }

    private function denyIfNotParticipant(Message $message, User $user): ?JsonResponse
    {
        $conversation = $message->getConversation();

        if (!$conversation) {
            return $this->json([
                'ok' => false,
                'error' => 'Conversation introuvable',
            ], 404);
        }

        $participantA = $conversation->getParticipantA();
        $participantB = $conversation->getParticipantB();

        $isParticipant =
            ($participantA && $participantA->getId() === $user->getId()) ||
            ($participantB && $participantB->getId() === $user->getId());

        if (!$isParticipant) {
            return $this->json([
                'ok' => false,
                'error' => 'Accès refusé',
            ], 403);
        }

        return null;
    }

    private function extractEmoji(Request $request): string|JsonResponse
    {
        $emoji = trim((string) (
            $request->request->get('emoji')
            ?? $request->getPayload()->get('emoji')
            ?? ''
        ));

        if ($emoji === '') {
            return $this->json([
                'ok' => false,
                'error' => 'Emoji manquant',
            ], 400);
        }

        if (mb_strlen($emoji) > 16) {
            return $this->json([
                'ok' => false,
                'error' => 'Emoji invalide',
            ], 400);
        }

        return $emoji;
    }

    private function buildReactionResponse(
        Message $message,
        MessageReactionRepository $messageReactionRepository,
        ?string $emoji = null,
        ?string $action = null
    ): array {
        $response = [
            'ok' => true,
            'messageId' => $message->getId(),
            'counts' => $messageReactionRepository->getGroupedCountsForMessage($message),
        ];

        if ($emoji !== null) {
            $response['emoji'] = $emoji;
        }

        if ($action !== null) {
            $response['action'] = $action;
        }

        return $response;
    }
}