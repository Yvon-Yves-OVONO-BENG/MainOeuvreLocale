<?php

namespace App\Service;

use Symfony\Component\Security\Core\Exception\AuthenticationException;

class SecurityService
{
    public function getLoginPageData(
        ?object $user,
        string $lastUsername,
        ?AuthenticationException $error
    ): array {
        return [
            'isAuthenticated' => $user !== null,
            'redirectRoute' => 'accueil',
            'redirectParams' => [],
            'last_username' => $lastUsername,
            'error' => $error,
        ];
    }

    public function getApiLoginPayload(
        ?object $user,
        string $lastUsername,
        ?AuthenticationException $error
    ): array {
        return [
            'ok' => true,
            'isAuthenticated' => $user !== null,
            'redirect' => [
                'route' => 'accueil',
                'params' => [],
            ],
            'last_username' => $lastUsername,
            'error' => $error ? $error->getMessageKey() : null,
        ];
    }

    public function getLogoutInterceptPayload(): array
    {
        return [
            'ok' => true,
            'message' => 'La déconnexion est interceptée par le firewall Symfony.',
        ];
    }
}