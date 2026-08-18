<?php

namespace App\EventSubscriber;

use App\Service\ApiMetricsRecorder;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ApiMetricsSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly ApiMetricsRecorder $apiMetricsRecorder)
    {
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

        if (!str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        $request->attributes->set('_api_metric_started_at', microtime(true));
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $startedAt = $request->attributes->get('_api_metric_started_at');

        if ($startedAt === null) {
            return;
        }

        $durationMs = (microtime(true) - (float) $startedAt) * 1000;

        $this->apiMetricsRecorder->record(
            $request,
            $event->getResponse(),
            $durationMs
        );
    }
}