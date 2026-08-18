<?php

namespace App\Service\Chat;

use App\Entity\ChatGroup;
use App\Entity\ChatGroupMember;
use App\Entity\ChatGroupMessage;
use App\Entity\ChatGroupMessageReaction;
use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\MessageReaction;
use App\Entity\User;
use App\Repository\MessageRepository;
use App\Util\ChatTimestamp;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final class ChatPayloadNormalizer
{
    public function __construct(
        private readonly ChatIdentityResolver $identity,
        private readonly ChatAccessGuard $access,
        private readonly EntityManagerInterface $em,
        private readonly MessageRepository $messages,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function conversation(Conversation $conversation, User $viewer): array
    {
        $other = $this->access->otherParticipant($conversation, $viewer);
        $last = $this->messages->findLastMessageForConversation($conversation);
        $presence = $this->identity->presence($other);

        return array_replace([
            'id' => $conversation->getId(),
            'kind' => 'private',
            'userId' => $other?->getId(),
            'name' => $this->identity->label($other),
            'roleLabel' => $this->identity->roleLabel($other),
            'avatarUrl' => $this->identity->avatar($other),
            'lastMessage' => $last instanceof Message ? $this->messagePreview($last) : 'Aucun message',
            'lastMessageAt' => $conversation->getLastMessageAt()
                ? ChatTimestamp::iso($conversation->getLastMessageAt())
                : null,
            'unreadCount' => $this->messages->countUnreadForConversation($conversation, $viewer),
        ], $presence);
    }

    public function privateMessage(Message $message, User $viewer): array
    {
        $sender = $message->getSender();
        $meta = $message->getMeta() ?? [];

        return [
            'id' => $message->getId(),
            'kind' => 'private',
            'conversationId' => $message->getConversation()?->getId(),
            'mine' => $message->isMine($viewer),
            'authorName' => $this->identity->label($sender),
            'authorRole' => $this->identity->roleLabel($sender),
            'avatarUrl' => $this->identity->avatar($sender),
            'content' => $message->isDeleted() ? 'Message supprimé' : $message->getContent(),
            'type' => $message->isDeleted() ? 'deleted' : $message->getType(),
            'createdAt' => ChatTimestamp::clock($message->getCreatedAt(), $meta),
            'createdAtIso' => ChatTimestamp::iso($message->getCreatedAt(), $meta),
            'createdAtFull' => ChatTimestamp::iso($message->getCreatedAt(), $meta),
            'status' => $message->getStatus(),
            'deliveredAt' => $message->getDeliveredAt()?->format('Y-m-d H:i:s'),
            'readAt' => $message->getReadAt()?->format('Y-m-d H:i:s'),
            'deleted' => $message->isDeleted(),
            'attachmentUrl' => $this->attachmentUrl($message->getAttachmentUrl(), $meta),
            'attachmentName' => $meta['originalName'] ?? $meta['filename'] ?? $message->getContent(),
            'meta' => $meta,
            'replyTo' => $this->privateReply($message->getReplyTo()),
            'reactions' => $this->privateReactions($message, $viewer),
        ];
    }

    public function group(ChatGroup $group, User $viewer): array
    {
        $memberCount = $this->em->getRepository(ChatGroupMember::class)->count([
            'chatGroup' => $group,
            'status' => ChatGroupMember::STATUS_ACTIVE,
        ]);

        $membership = $this->em->getRepository(ChatGroupMember::class)->findOneBy([
            'chatGroup' => $group,
            'user' => $viewer,
        ]);

        return [
            'id' => $group->getId(),
            'kind' => 'group',
            'name' => $group->getName(),
            'description' => $group->getDescription(),
            'visibility' => $group->getVisibility(),
            'memberCount' => $memberCount,
            'membershipStatus' => $membership?->getStatus(),
            'role' => $membership?->getRole(),
            'canManage' => $this->access->canManageGroup($group, $viewer),
            'allowFiles' => $group->isAllowFiles(),
            'allowVoice' => $group->isAllowVoice(),
            'ownerName' => $this->identity->label($group->getOwner()),
            'avatarUrl' => null,
        ];
    }

    public function groupMessage(ChatGroupMessage $message, User $viewer): array
    {
        $sender = $message->getSender();
        $meta = $message->getMeta() ?? [];

        return [
            'id' => $message->getId(),
            'kind' => 'group',
            'groupId' => $message->getChatGroup()?->getId(),
            'mine' => $sender?->getId() === $viewer->getId(),
            'authorName' => $this->identity->label($sender),
            'authorRole' => $this->identity->roleLabel($sender),
            'avatarUrl' => $this->identity->avatar($sender),
            'content' => $message->isDeleted() ? 'Message supprimé' : $message->getContent(),
            'type' => $message->isDeleted() ? 'deleted' : $message->getType(),
            'createdAt' => ChatTimestamp::clock($message->getCreatedAt(), $meta),
            'createdAtIso' => ChatTimestamp::iso($message->getCreatedAt(), $meta),
            'createdAtFull' => ChatTimestamp::iso($message->getCreatedAt(), $meta),
            'deleted' => $message->isDeleted(),
            'attachmentUrl' => $this->groupAttachmentUrl($message->getAttachmentUrl(), $meta),
            'attachmentName' => $meta['originalName'] ?? $meta['filename'] ?? $message->getContent(),
            'meta' => $meta,
            'replyTo' => $this->groupReply($message->getReplyTo()),
            'reactions' => $this->groupReactions($message, $viewer),
        ];
    }

    public function messagePreview(Message $message): string
    {
        if ($message->isDeleted()) {
            return 'Message supprimé';
        }

        if ($message->getContent()) {
            return mb_strimwidth($message->getContent(), 0, 90, '...');
        }

        return match ($message->getType()) {
            'voice', 'audio' => 'Message vocal',
            'image' => 'Photo',
            'video' => 'Vidéo',
            Message::TYPE_FILE => 'Fichier',
            Message::TYPE_CALL => 'Appel',
            default => 'Message',
        };
    }

    private function privateReply(?Message $reply): ?array
    {
        if (!$reply instanceof Message) {
            return null;
        }

        return [
            'id' => $reply->getId(),
            'authorName' => $reply->isDeleted() ? 'Message supprimé' : $this->identity->label($reply->getSender()),
            'content' => $reply->isDeleted() ? 'Ce message n’est plus disponible.' : $this->messagePreview($reply),
            'type' => $reply->getType(),
        ];
    }

    private function groupReply(?ChatGroupMessage $reply): ?array
    {
        if (!$reply instanceof ChatGroupMessage) {
            return null;
        }

        return [
            'id' => $reply->getId(),
            'authorName' => $reply->isDeleted() ? 'Message supprimé' : $this->identity->label($reply->getSender()),
            'content' => $reply->isDeleted() ? 'Ce message n’est plus disponible.' : mb_strimwidth((string) $reply->getContent(), 0, 90, '...'),
            'type' => $reply->getType(),
        ];
    }

    private function privateReactions(Message $message, User $viewer): array
    {
        $counts = [];
        $mine = [];

        foreach ($this->em->getRepository(MessageReaction::class)->findBy(['message' => $message]) as $reaction) {
            $emoji = $reaction->getEmoji();
            $counts[$emoji] = ($counts[$emoji] ?? 0) + 1;
            if ($reaction->getUser()?->getId() === $viewer->getId()) {
                $mine[$emoji] = true;
            }
        }

        return array_map(
            static fn (string $emoji, int $total): array => ['emoji' => $emoji, 'total' => $total, 'mine' => isset($mine[$emoji])],
            array_keys($counts),
            array_values($counts)
        );
    }

    private function groupReactions(ChatGroupMessage $message, User $viewer): array
    {
        $counts = [];
        $mine = [];

        foreach ($this->em->getRepository(ChatGroupMessageReaction::class)->findBy(['message' => $message]) as $reaction) {
            $emoji = $reaction->getEmoji();
            $counts[$emoji] = ($counts[$emoji] ?? 0) + 1;
            if ($reaction->getUser()?->getId() === $viewer->getId()) {
                $mine[$emoji] = true;
            }
        }

        return array_map(
            static fn (string $emoji, int $total): array => ['emoji' => $emoji, 'total' => $total, 'mine' => isset($mine[$emoji])],
            array_keys($counts),
            array_values($counts)
        );
    }

    private function attachmentUrl(?string $path, array $meta): ?string
    {
        if (!$path) {
            return null;
        }

        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        $category = $meta['category'] ?? null;
        $folder = $category === 'voice' ? 'voices' : 'files';

        return $this->publicBase() . '/uploads/chat/' . $folder . '/' . ltrim($path, '/');
    }

    private function groupAttachmentUrl(?string $path, array $meta): ?string
    {
        if (!$path) {
            return null;
        }

        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        return $this->publicBase() . '/uploads/chat/groups/' . ltrim($path, '/');
    }

    private function publicBase(): string
    {
        return $this->requestStack->getCurrentRequest()?->getBasePath() ?? '';
    }
}
