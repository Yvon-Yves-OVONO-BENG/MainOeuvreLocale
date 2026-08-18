<?php

namespace App\Security;

use App\Entity\Conversation;
use App\Entity\User;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class ConversationAccess
{
    public function assertParticipant(Conversation $c, User $me): void
    {
        // je vérifie que l'utilisateur connecté fait partie de la conversation
        if ($c->getParticipantA() !== $me && $c->getParticipantB() !== $me) {
            throw new AccessDeniedException('Accès refusé.');
        }
    }
}