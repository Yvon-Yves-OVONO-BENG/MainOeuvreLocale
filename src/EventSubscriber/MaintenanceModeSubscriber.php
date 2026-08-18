<?php

namespace App\EventSubscriber;

use App\Service\MaintenanceService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class MaintenanceModeSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly MaintenanceService $maintenanceService,
        private readonly Security $security,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // priorité plus basse pour laisser Symfony résoudre la route d'abord
            KernelEvents::REQUEST => ['onKernelRequest', 0],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if (!$this->maintenanceService->isEnabled()) {
            return;
        }

        $request = $event->getRequest();
        $route = (string) $request->attributes->get('_route', '');
        $path = $request->getPathInfo();

        // Routes autorisées pendant la maintenance
        $allowedRoutes = [
            'app_maintenance',
            'admin_login',
            'admin_logout',
        ];

        // Profiler Symfony
        if (str_starts_with($route, '_profiler') || str_starts_with($route, '_wdt')) {
            return;
        }

        // Fichiers statiques si besoin
        if (
            str_starts_with($path, '/build/')
            || str_starts_with($path, '/bundles/')
            || str_starts_with($path, '/assets/')
        ) {
            return;
        }

        // Admin connecté => accès autorisé
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return;
        }

        // Autoriser maintenance + login admin
        if (\in_array($route, $allowedRoutes, true)) {
            return;
        }

        $maintenanceUrl = $this->urlGenerator->generate('app_maintenance');

        // sécurité anti-boucle
        if ($path === parse_url($maintenanceUrl, PHP_URL_PATH)) {
            return;
        }

        $event->setResponse(new RedirectResponse($maintenanceUrl));
    }
}