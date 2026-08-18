<?php

namespace App\Controller\Web\Chat1;

use App\Entity\User;
use App\Repository\ConversationRepository;
use App\Security\CurrentUser;
use App\Service\ConversationFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/chat/conversation/with/{userId}', name: 'chat_with_user', methods: ['POST'])]
final class OpenOrCreateConversationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private ConversationRepository $conversationRepository,
        private CurrentUser $currentUser,
        private ConversationFactory $factory,
    ) {}

    public function __invoke(int $userId): JsonResponse
    {
        // je récupère l'utilisateur connecté
        $me = $this->currentUser->requireUser($this);

        // je charge le destinataire
        $other = $this->em->getRepository(User::class)->find($userId);
        if (!$other) {
            return $this->json(['ok' => false], 404);
        }

        // je bloque l'auto-conversation
        if ($other === $me) {
            return $this->json(['ok' => false, 'error' => 'Cannot message yourself'], 400);
        }

        // j'ordonne les participants pour éviter les doublons
        $a = $me->getId() < $other->getId() ? $me : $other;
        $b = $me->getId() < $other->getId() ? $other : $me;

        // si une conversation existe déjà, je la retourne
        $existing = $this->conversationRepository->findBetweenUsers($a, $b);
        if ($existing) {
            return $this->json([
                'ok' => true,
                'conversationId' => $existing->getId(),
                'created' => false,
            ]);
        }

        // je crée la conversation
        $conv = $this->factory->create($a, $b);
        $this->em->persist($conv);

        // je crée un message par défaut
        $message = $this->factory->createDefaultMessage($conv, $me, "Bonjour 👋");
        $this->em->persist($message);

        // j'assure la cohérence (lastMessageAt, relation)
        $conv->addMessage($message);

        // je sauvegarde en base
        $this->em->flush();

        // je réponds au front
        return $this->json([
            'ok' => true,
            'conversationId' => $conv->getId(),
            'created' => true,
            'defaultMessageSent' => true,
        ]);
    }
}