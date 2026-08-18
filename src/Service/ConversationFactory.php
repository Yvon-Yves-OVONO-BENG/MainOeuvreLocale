<?php

namespace App\Service;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\User;

final class ConversationFactory
{
    public function create(User $a, User $b): Conversation
    {
        // je crée la conversation
        return (new Conversation())
            ->setParticipantA($a)
            ->setParticipantB($b);
    }

    public function createDefaultMessage(Conversation $conv, User $sender, string $text = "Bonjour 👋"): Message
    {
        // je crée le message par défaut
        return (new Message())
            ->setConversation($conv)
            ->setSender($sender)
            ->setContent($text);
    }
}