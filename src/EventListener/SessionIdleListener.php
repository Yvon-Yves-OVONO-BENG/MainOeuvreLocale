<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class SessionIdleListener implements EventSubscriberInterface
{
    private RequestStack $requestStack;
    private TokenStorageInterface $tokenStorage;
    private AuthorizationCheckerInterface $authorizationChecker;
    private UrlGeneratorInterface $urlGenerator;
    private int $maxIdleTime;

    public function __construct(
        RequestStack $requestStack,  // Utiliser RequestStack au lieu de SessionInterface
        TokenStorageInterface $tokenStorage,
        AuthorizationCheckerInterface $authorizationChecker,
        UrlGeneratorInterface $urlGenerator,
        int $maxIdleTime = 1800
    ) {
        $this->requestStack = $requestStack;
        $this->tokenStorage = $tokenStorage;
        $this->authorizationChecker = $authorizationChecker;
        $this->urlGenerator = $urlGenerator;
        $this->maxIdleTime = $maxIdleTime;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 0],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        // Ne pas traiter les sous-requêtes
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $session = $request->getSession();
        
        // Vérifier si une session existe
        if (!$session || !$session->isStarted()) {
            return;
        }

        // Vérifier si l'utilisateur est connecté
        if (!$this->authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            return;
        }

        // Routes à ignorer
        $currentRoute = $request->attributes->get('_route');
        $ignoredRoutes = ['app_login', 'app_logout', 'app_keep_alive'];
        
        if (in_array($currentRoute, $ignoredRoutes)) {
            return;
        }

        // Vérifier la dernière activité
        $lastActivity = $session->get('last_activity', time());
        $currentTime = time();

        if (($currentTime - $lastActivity) > $this->maxIdleTime) {
            // Session expirée, déconnecter l'utilisateur
            $session->invalidate();
            $this->tokenStorage->setToken(null);
            
            // Message flash optionnel
            $session->getFlashBag()->add('warning', 'Vous avez été déconnecté pour inactivité.');
            
            // Rediriger vers la page de connexion
            $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_login')));
            return;
        }

        // Mettre à jour la dernière activité
        $session->set('last_activity', $currentTime);
    }
}