<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'chat_test_message')]
class ChatTestMessage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\Column(type: 'text')]
    public string $visitorMessage;

    #[ORM\Column(type: 'text')]
    public string $botReply;

    #[ORM\Column(type: 'json')]
    public array $settingsSnapshot = [];

    #[ORM\Column(type: 'datetime_immutable')]
    public \DateTimeImmutable $createdAt;

    public function __construct(string $visitorMessage, string $botReply, array $settingsSnapshot = [])
    {
        $this->visitorMessage = $visitorMessage;
        $this->botReply = $botReply;
        $this->settingsSnapshot = $settingsSnapshot;
        $this->createdAt = new \DateTimeImmutable();
    }
}