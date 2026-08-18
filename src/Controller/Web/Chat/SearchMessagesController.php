<?php

namespace App\Controller\Web\Chat;

use App\Entity\ChatGroup;
use App\Entity\ChatGroupMessage;
use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\User;
use App\Service\Chat\ChatAccessGuard;
use App\Service\Chat\ChatPayloadNormalizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class SearchMessagesController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ChatAccessGuard $access,
        private readonly ChatPayloadNormalizer $normalizer,
    ) {
    }

    #[Route('/chat/conversation/{id}/search', name: 'chat_search_conversation_messages', methods: ['GET'])]
    public function privateSearch(Conversation $conversation, Request $request): JsonResponse
    {
        $me = $this->requireChatUser();
        $this->access->assertPrivateParticipant($conversation, $me);

        $q = trim((string) $request->query->get('q', ''));
        $limit = max(1, min(80, (int) $request->query->get('limit', 30)));

        if (mb_strlen($q) < 2) {
            return $this->json(['ok' => true, 'items' => []]);
        }

        $items = $this->em->createQueryBuilder()
            ->select('m', 's', 'reply')
            ->from(Message::class, 'm')
            ->leftJoin('m.sender', 's')
            ->leftJoin('m.replyTo', 'reply')
            ->andWhere('m.conversation = :conversation')
            ->andWhere('m.deletedAt IS NULL')
            ->andWhere('m.deleted = false')
            ->andWhere('LOWER(m.content) LIKE :q OR LOWER(m.attachmentUrl) LIKE :q')
            ->setParameter('conversation', $conversation)
            ->setParameter('q', '%' . mb_strtolower($q) . '%')
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $this->json([
            'ok' => true,
            'kind' => 'private',
            'query' => $q,
            'items' => array_map(fn (Message $message): array => $this->normalizer->privateMessage($message, $me), $items),
        ]);
    }

    #[Route('/chat/groups/{id}/search', name: 'chat_group_search_messages', methods: ['GET'])]
    public function groupSearch(ChatGroup $group, Request $request): JsonResponse
    {
        $me = $this->requireChatUser();
        $this->access->assertGroupMember($group, $me);

        $q = trim((string) $request->query->get('q', ''));
        $limit = max(1, min(80, (int) $request->query->get('limit', 30)));

        if (mb_strlen($q) < 2) {
            return $this->json(['ok' => true, 'items' => []]);
        }

        $items = $this->em->createQueryBuilder()
            ->select('m', 's', 'reply')
            ->from(ChatGroupMessage::class, 'm')
            ->leftJoin('m.sender', 's')
            ->leftJoin('m.replyTo', 'reply')
            ->andWhere('m.chatGroup = :group')
            ->andWhere('m.deleted = false')
            ->andWhere('LOWER(m.content) LIKE :q OR LOWER(m.attachmentUrl) LIKE :q')
            ->setParameter('group', $group)
            ->setParameter('q', '%' . mb_strtolower($q) . '%')
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $this->json([
            'ok' => true,
            'kind' => 'group',
            'query' => $q,
            'items' => array_map(fn (ChatGroupMessage $message): array => $this->normalizer->groupMessage($message, $me), $items),
        ]);
    }

    #[Route('/chat/search/messages', name: 'chat_search_messages', methods: ['GET'])]
    public function globalSearch(Request $request): JsonResponse
    {
        $me = $this->requireChatUser();
        $q = trim((string) $request->query->get('q', ''));
        $limit = max(1, min(50, (int) $request->query->get('limit', 20)));

        if (mb_strlen($q) < 2) {
            return $this->json(['ok' => true, 'items' => []]);
        }

        $privateItems = $this->em->createQueryBuilder()
            ->select('m', 'c', 's')
            ->from(Message::class, 'm')
            ->join('m.conversation', 'c')
            ->leftJoin('m.sender', 's')
            ->andWhere('c.participantA = :me OR c.participantB = :me')
            ->andWhere('m.deletedAt IS NULL')
            ->andWhere('m.deleted = false')
            ->andWhere('LOWER(m.content) LIKE :q OR LOWER(m.attachmentUrl) LIKE :q')
            ->setParameter('me', $me)
            ->setParameter('q', '%' . mb_strtolower($q) . '%')
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $this->json([
            'ok' => true,
            'query' => $q,
            'items' => array_map(fn (Message $message): array => $this->normalizer->privateMessage($message, $me), $privateItems),
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
