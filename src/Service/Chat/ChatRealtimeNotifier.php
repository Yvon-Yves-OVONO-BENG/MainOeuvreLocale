<?php

namespace App\Service\Chat;

/**
 * Compatibilité avec les contrôleurs historiques.
 *
 * Le front synchronise les conversations et les statuts par polling AJAX.
 * Ces méthodes restent pour ne
 * pas casser les appels métier existants pendant la transition.
 */
final class ChatRealtimeNotifier
{
    public function publish(string $topic, array $payload): void {}
    public function privateMessage(int $conversationId, int $messageId, string $event = 'message'): void {}
    public function privateStatus(int $conversationId, string $event, array $extra = []): void {}
    public function privateTyping(int $conversationId, int $userId, bool $typing): void {}
    public function groupMessage(int $groupId, ?int $messageId = null, string $event = 'message'): void {}
}
