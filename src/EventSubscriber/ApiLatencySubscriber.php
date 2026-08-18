<?php

namespace App\EventSubscriber;

use App\Entity\ApiLatencyLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ApiLatencySubscriber implements EventSubscriberInterface
{
    private const ATTR_START_TIME = '_api_latency_start_time';

    public function __construct(
        private readonly EntityManagerInterface $em
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (!str_starts_with($path, '/api')) {
            return;
        }

        $request->attributes->set(self::ATTR_START_TIME, microtime(true));
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (!str_starts_with($path, '/api')) {
            return;
        }

        $start = $request->attributes->get(self::ATTR_START_TIME);

        if ($start === null) {
            return;
        }

        $durationMs = (int) round((microtime(true) - (float) $start) * 1000);
        $response = $event->getResponse();

        $log = new ApiLatencyLog();
        $log->setPath($path);
        $log->setMethod($request->getMethod());
        $log->setRouteName($request->attributes->get('_route'));
        $log->setStatusCode($response->getStatusCode());
        $log->setResponseTimeMs($durationMs);
        $log->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($log);
        $this->em->flush();
    }
}