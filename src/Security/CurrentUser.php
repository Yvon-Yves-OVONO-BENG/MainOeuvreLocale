<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class CurrentUser
{
    public function __construct(private TokenStorageInterface $tokens) {}

    public function requireUser(): User
    {
        $me = $this->tokens->getToken()?->getUser();
        if (!$me instanceof User) {
            throw new AccessDeniedException('Non connecté.');
        }
        return $me;
    }
}