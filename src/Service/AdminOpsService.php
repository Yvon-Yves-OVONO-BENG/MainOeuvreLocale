<?php

namespace App\Service;

use App\Message\Admin\ReindexSearchMessage;
use App\Message\Admin\RestartNotificationsQueueMessage;
use App\Message\Admin\RetryFailedWebhooksMessage;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class AdminOpsService
{
    private const KEY_MAINTENANCE = 'admin.ops.maintenance';
    private const KEY_LAST_REINDEX_AT = 'admin.ops.last_reindex_at';
    private const KEY_LAST_QUEUE_RESTART_AT = 'admin.ops.last_queue_restart_at';
    private const KEY_LAST_WEBHOOK_RETRY_AT = 'admin.ops.last_webhook_retry_at';

    public function __construct(
        private readonly CacheItemPoolInterface $cache,
        private readonly LoggerInterface $logger,
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function getDashboardData(): array
    {
        $maintenance = $this->getBool(self::KEY_MAINTENANCE);

        return [
            'maintenance' => $maintenance,
            'maintenanceLabel' => $maintenance ? 'ON' : 'OFF',
            'queueHealth' => 'Healthy',
            'failedWebhooks' => 12,
            'lastReindexAt' => $this->getValue(self::KEY_LAST_REINDEX_AT),
            'lastQueueRestartAt' => $this->getValue(self::KEY_LAST_QUEUE_RESTART_AT),
            'lastWebhookRetryAt' => $this->getValue(self::KEY_LAST_WEBHOOK_RETRY_AT),
            'securityScore' => 92,
            'auditState' => 'Live',
        ];
    }

    public function reindexSearch(string $actor): array
    {
        $now = new \DateTimeImmutable();

        $this->bus->dispatch(new ReindexSearchMessage($actor, $now->format(\DATE_ATOM)));

        $this->setValue(self::KEY_LAST_REINDEX_AT, $now->format('d/m/Y H:i'));

        $this->logger->info('Réindexation moteur demandée', [
            'actor' => $actor,
            'at' => $now->format('Y-m-d H:i:s'),
        ]);

        return [
            'message' => 'Réindexation du moteur lancée.',
        ];
    }

    public function restartNotificationsQueue(string $actor): array
    {
        $now = new \DateTimeImmutable();

        $this->bus->dispatch(new RestartNotificationsQueueMessage($actor, $now->format(\DATE_ATOM)));

        $this->setValue(self::KEY_LAST_QUEUE_RESTART_AT, $now->format('d/m/Y H:i'));

        $this->logger->warning('Redémarrage queue notifications demandé', [
            'actor' => $actor,
            'at' => $now->format('Y-m-d H:i:s'),
        ]);

        return [
            'message' => 'Relance de la queue demandée.',
        ];
    }

    public function retryFailedWebhooks(string $actor): array
    {
        $now = new \DateTimeImmutable();

        $this->bus->dispatch(new RetryFailedWebhooksMessage($actor, $now->format(\DATE_ATOM)));

        $this->setValue(self::KEY_LAST_WEBHOOK_RETRY_AT, $now->format('d/m/Y H:i'));

        $this->logger->notice('Relance des webhooks échoués demandée', [
            'actor' => $actor,
            'at' => $now->format('Y-m-d H:i:s'),
        ]);

        return [
            'message' => 'Relance des webhooks déclenchée.',
        ];
    }

    public function toggleMaintenance(string $actor): array
    {
        $current = $this->getBool(self::KEY_MAINTENANCE);
        $next = !$current;

        $this->setValue(self::KEY_MAINTENANCE, $next);

        $this->logger->critical('Bascule du mode maintenance', [
            'actor' => $actor,
            'from' => $current,
            'to' => $next,
        ]);

        return [
            'message' => $next
                ? 'Le mode maintenance est activé.'
                : 'Le mode maintenance est désactivé.',
        ];
    }

    private function getBool(string $key, bool $default = false): bool
    {
        $item = $this->cache->getItem($key);

        if (!$item->isHit()) {
            return $default;
        }

        return (bool) $item->get();
    }

    private function getValue(string $key): mixed
    {
        $item = $this->cache->getItem($key);

        return $item->isHit() ? $item->get() : null;
    }

    private function setValue(string $key, mixed $value): void
    {
        $item = $this->cache->getItem($key);
        $item->set($value);
        $this->cache->save($item);
    }
}