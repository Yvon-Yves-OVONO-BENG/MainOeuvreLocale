<?php

namespace App\Service\Chat;

use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

final class ChatRealtimeNotifier
{
    public function __construct(private readonly ?HubInterface $hub = null)
    {
    }

    public function publish(string $topic, array $payload): void
    {
        if (!$this->hub) {
            return;
        }

        try {
            $this->hub->publish(new Update(
                $topic,
                json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
            ));
        } catch (\Throwable) {
            // Le chat doit rester utilisable même si Mercure est indisponible.
        }
    }

    public function privateMessage(int $conversationId, int $messageId, string $event = 'message'): void
    {
        $payload = [
            'type' => $event,
            'event' => $event,
            'conversationId' => $conversationId,
            'messageId' => $messageId,
        ];

        $this->publish(sprintf('/conversations/%d/messages', $conversationId), $payload);
        $this->publish(sprintf('chat/conversation/%d', $conversationId), $payload);
    }

    public function privateStatus(int $conversationId, string $event, array $extra = []): void
    {
        $payload = array_replace([
            'type' => $event,
            'event' => $event,
            'conversationId' => $conversationId,
        ], $extra);

        $this->publish(sprintf('/conversations/%d/status', $conversationId), $payload);
        $this->publish(sprintf('chat/conversation/%d/status', $conversationId), $payload);
    }

    public function privateTyping(int $conversationId, int $userId, bool $typing): void
    {
        $payload = [
            'type' => 'typing',
            'event' => 'typing',
            'conversationId' => $conversationId,
            'userId' => $userId,
            'typing' => $typing,
        ];

        $this->publish(sprintf('chat/conversation/%d/typing', $conversationId), $payload);
        $this->publish(sprintf('/conversations/%d/typing', $conversationId), $payload);
    }

    public function groupMessage(int $groupId, ?int $messageId = null, string $event = 'message'): void
    {
        $payload = [
            'type' => $event,
            'event' => $event,
            'groupId' => $groupId,
            'messageId' => $messageId,
        ];

        $this->publish(sprintf('/groups/%d/messages', $groupId), $payload);
        $this->publish(sprintf('chat/group/%d', $groupId), $payload);
    }
}
