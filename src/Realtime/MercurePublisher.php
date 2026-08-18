<?php

namespace App\Realtime;

use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

final class MercurePublisher
{
    public function __construct(private ?HubInterface $hub = null) {}

    public function publish(string $topic, array $payload): void
    {
        // si Mercure n'est pas configuré, je n'empêche pas l'app de fonctionner
        if (!$this->hub) {
            return;
        }

        // je publie l'événement sur Mercure
        $this->hub->publish(new Update($topic, json_encode($payload)));
    }
}