<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class OnboardingGuardSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 20],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route = (string) $request->attributes->get('_route');

        if ($this->isAllowedRoute($route)) {
            return;
        }

        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return;
        }

        $roles = $user->getRoles();
        $isStaff = array_intersect(
            ['ROLE_MODERATEUR', 'ROLE_MODERATOR', 'ROLE_ADMIN', 'ROLE_SUPER_ADMIN'],
            $roles
        ) !== [];

        // Un seul parcours de choix : les trois cartes Talent / Particulier / Entreprise.
        // Les comptes internes existants ne sont pas renvoyés dans l'onboarding client.
        if (!$isStaff && ($user->getProfileType() === null || $user->getProfileType() === '')) {
            $event->setResponse(new RedirectResponse(
                $this->urlGenerator->generate('profile_type_choice')
            ));

            return;
        }

        if ($user->getPersonalProfile() === null && $user->getProfessionalProfile() === null) {
            $event->setResponse(new RedirectResponse(
                $this->urlGenerator->generate('profile_edit')
            ));

            return;
        }

        // Les comptes publics doivent renseigner une vraie identité et une vraie photo/logo
        // avant d'accéder au reste du site. Les appels AJAX du formulaire restent autorisés.
        if (!$isStaff && !$request->isXmlHttpRequest() && in_array($user->getProfileType(), ['talent', 'particulier', 'company'], true)) {
            $profile = $user->getPersonalProfile();
            $fullName = trim((string) $profile?->getFullName());
            $email = trim((string) $user->getEmail());
            $photo = trim((string) $profile?->getPhoto());
            $photoBasename = strtolower((string) pathinfo(str_replace('\\', '/', $photo), PATHINFO_BASENAME));

            if ($user->getProfileType() === 'company') {
                $companyName = trim((string) ($profile?->getCompanyTradeName() ?: $profile?->getCompanyLegalName()));
                $hasRealName = $companyName !== '';
            } else {
                $hasRealName = $fullName !== ''
                    && filter_var($fullName, FILTER_VALIDATE_EMAIL) === false
                    && ($email === '' || strcasecmp($fullName, $email) !== 0);
            }

            $hasRealPhoto = $photo !== ''
                && !in_array($photoBasename, ['avatar.png', 'default-avatar.png', 'default.png'], true);

            if (!$hasRealName || !$hasRealPhoto) {
                $event->setResponse(new RedirectResponse(
                    $this->urlGenerator->generate('profile_edit')
                ));
            }
        }
    }

    private function isAllowedRoute(string $route): bool
    {
        return in_array($route, [
            'app_login',
            'app_logout',
            'app_register',
            'app_register_submit',
            'app_register_check_email',

            'connect_google_start',
            'connect_google_check',
            'connect_facebook_start',
            'connect_facebook_check',
            'connect_apple_start',
            'connect_apple_check',
            'connect_microsoft_start',
            'connect_microsoft_check',

            'profile_type_choice',
            'profile_edit',
            'ajax_professions_by_categorie',
            'app_verify_email',
        ], true);
    }
}
