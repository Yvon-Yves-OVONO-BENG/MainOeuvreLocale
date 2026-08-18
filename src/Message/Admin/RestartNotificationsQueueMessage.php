<?php

namespace App\Message\Admin;

final class RestartNotificationsQueueMessage
{
    public function __construct(
        public readonly string $actor,
        public readonly string $requestedAt,
    ) {
    }
}