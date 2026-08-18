<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

class UserAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private UserRepository $userRepository,
    ) {}

    public function authenticate(Request $request): Passport
    {
        $email = strtolower(trim((string) $request->request->get('_username', '')));

        if ($request->hasSession()) {
            $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $email);
        }

        return new Passport(
            new UserBadge($email, function (string $userIdentifier): User {
                $user = $this->userRepository->findOneByEmailAddress($userIdentifier);

                // ✅ Email inconnu (message “propre”)
                if (!$user instanceof User) {
                    throw new CustomUserMessageAuthenticationException('Identifiants invalides.');
                }

                // ✅ Compte non activé / email non vérifié
                if ($user->IsEmailVerified() === false || $user->IsActive() === false) {
                    throw new CustomUserMessageAuthenticationException(
                        "Votre compte n'est pas encore activé. Consultez votre email pour l'activer."
                    );
                }

                return $user;
            }),
            new PasswordCredentials((string) $request->request->get('_password', '')),
            [
                new CsrfTokenBadge('authenticate', (string) $request->request->get('_csrf_token')),
                new RememberMeBadge(),
            ]
        );
    }

    public function onAuthenticationSuccess(
        Request $request,
        TokenInterface $token,
        string $firewallName
    ): ?Response {
        if ($request->hasSession()) {
            $session = $request->getSession();
            $targetPath = $this->getTargetPath($session, $firewallName);

            // Le chemin mémorisé doit être consommé une seule fois. Sans cela,
            // un ancien appel AJAX peut rester en session et reprendre le dessus
            // lors d'une connexion suivante.
            $this->removeTargetPath($session, $firewallName);

            if ($targetPath && $this->isSafeHtmlTargetPath($targetPath, $request)) {
                return new RedirectResponse($targetPath);
            }
        }

        // 👉 redirection tableau de bord
        return new RedirectResponse(
            $this->urlGenerator->generate('tableau_de_bord')
        );
    }

    /**
     * Empêche une authentification de rediriger le navigateur vers un endpoint
     * JSON/AJAX mémorisé pendant que l'utilisateur était encore anonyme.
     */
    private function isSafeHtmlTargetPath(string $targetPath, Request $request): bool
    {
        $parts = parse_url($targetPath);

        if ($parts === false) {
            return false;
        }

        // Protection supplémentaire contre une redirection vers un autre site.
        if (isset($parts['host']) && strcasecmp($parts['host'], $request->getHost()) !== 0) {
            return false;
        }

        $path = '/' . ltrim((string) ($parts['path'] ?? ''), '/');

        $blockedPaths = [
            '/contact/remaining',
        ];

        if (in_array($path, $blockedPaths, true)) {
            return false;
        }

        $blockedPrefixes = [
            '/api/',
            '/contact/show/',
            '/presence/',
        ];

        foreach ($blockedPrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return false;
            }
        }

        return true;
    }


    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}
