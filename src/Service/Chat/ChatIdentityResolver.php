<?php

namespace App\Service\Chat;

use App\Entity\User;
use Symfony\Component\HttpFoundation\RequestStack;

final class ChatIdentityResolver
{
    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    public function label(?User $user): string
    {
        if (!$user instanceof User) {
            return 'Utilisateur';
        }

        $profile = method_exists($user, 'getPersonalProfile') ? $user->getPersonalProfile() : null;

        if ($profile && method_exists($profile, 'getDisplayIdentity') && $profile->getDisplayIdentity()) {
            return (string) $profile->getDisplayIdentity();
        }

        if ($profile && method_exists($profile, 'getFullName') && $profile->getFullName()) {
            return (string) $profile->getFullName();
        }

        if (method_exists($user, 'getEmail') && $user->getEmail()) {
            return (string) $user->getEmail();
        }

        return 'Utilisateur #' . ($user->getId() ?? '');
    }

    public function avatar(?User $user): string
    {
        $basePath = $this->requestStack->getCurrentRequest()?->getBasePath() ?? '';
        $fallback = $basePath . '/uploads/profiles/avatar.png';

        if (!$user instanceof User) {
            return $fallback;
        }

        if (method_exists($user, 'getAvatarUrl') && $user->getAvatarUrl()) {
            return $this->normalizePublicUrl((string) $user->getAvatarUrl(), $fallback);
        }

        $profile = method_exists($user, 'getPersonalProfile') ? $user->getPersonalProfile() : null;
        $photo = $profile && method_exists($profile, 'getPhoto') ? $profile->getPhoto() : null;

        if (!is_string($photo) || trim($photo) === '') {
            return $fallback;
        }

        return $this->normalizePublicUrl($photo, $fallback, '/uploads/profiles/');
    }

    public function roleLabel(?User $user): string
    {
        if (!$user instanceof User) {
            return 'Utilisateur';
        }

        $roles = method_exists($user, 'getRoles') ? $user->getRoles() : [];

        if (in_array('ROLE_ADMIN', $roles, true) || in_array('ROLE_SUPER_ADMIN', $roles, true)) {
            return 'Admin';
        }

        if (in_array('ROLE_COMPANY', $roles, true) || in_array('ROLE_ENTREPRISE', $roles, true)) {
            return 'Entreprise';
        }

        if (in_array('ROLE_PROFESSIONAL', $roles, true) || in_array('ROLE_TALENT', $roles, true)) {
            return 'Talent';
        }

        return 'Utilisateur';
    }

    public function presence(?User $user): array
    {
        if (!$user instanceof User) {
            return [
                'presence' => 'offline',
                'online' => false,
                'lastSeenAt' => null,
                'lastDisconnectedAt' => null,
            ];
        }

        $presence = method_exists($user, 'getPresenceStatus') ? $user->getPresenceStatus() : 'offline';

        return [
            'presence' => $presence,
            'online' => $presence === 'online',
            'lastSeenAt' => method_exists($user, 'getLastSeenAt') ? $user->getLastSeenAt()?->format('Y-m-d H:i:s') : null,
            'lastDisconnectedAt' => method_exists($user, 'getLastDisconnectedAt') ? $user->getLastDisconnectedAt()?->format('Y-m-d H:i:s') : null,
        ];
    }

    private function normalizePublicUrl(string $value, string $fallback, string $defaultPrefix = ''): string
    {
        $value = trim(str_replace('\\', '/', $value));

        if ($value === '' || $value === 'null' || $value === 'undefined') {
            return $fallback;
        }

        if (preg_match('#^https?://#i', $value) || str_starts_with($value, 'data:image/')) {
            return $value;
        }

        $basePath = $this->requestStack->getCurrentRequest()?->getBasePath() ?? '';
        $value = preg_replace('#^/?maindoeuvrelocale/public/#', '', $value) ?? $value;
        $value = preg_replace('#^/?public/#', '', $value) ?? $value;
        $value = str_replace('/public/uploads/', '/uploads/', $value);
        $value = ltrim($value, '/');

        if (str_starts_with($value, 'uploads/')) {
            return $basePath . '/' . $this->encodePath($value);
        }

        if ($defaultPrefix !== '') {
            return $basePath . rtrim($defaultPrefix, '/') . '/' . $this->encodePath($value);
        }

        return $basePath . '/' . $this->encodePath($value);
    }

    private function encodePath(string $path): string
    {
        return implode('/', array_map(
            static fn (string $part): string => rawurlencode(rawurldecode($part)),
            array_filter(explode('/', $path), static fn (string $part): bool => $part !== '')
        ));
    }
}
