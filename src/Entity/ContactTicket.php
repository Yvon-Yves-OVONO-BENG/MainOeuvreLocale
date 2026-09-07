<?php

namespace App\Entity;

use App\Repository\ContactTicketRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ContactTicketRepository::class)]
#[ORM\Table(name: 'contact_ticket')]
#[ORM\Index(columns: ['user_id', 'profession_id', 'is_active'], name: 'idx_contact_ticket_available')]
class ContactTicket
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private ?Profession $profession = null;

    #[ORM\Column]
    private int $quantity = 3;

    #[ORM\Column]
    private int $remainingContacts = 3;

    #[ORM\Column]
    private int $price = 200;

    #[ORM\Column(length: 120, unique: true)]
    private ?string $paymentId = null;

    #[ORM\Column(length: 30)]
    private string $paymentMethod = 'card';

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(User $user): self { $this->user = $user; return $this; }
    public function getProfession(): ?Profession { return $this->profession; }
    public function setProfession(Profession $profession): self { $this->profession = $profession; return $this; }
    public function getQuantity(): int { return $this->quantity; }
    public function setQuantity(int $quantity): self { $this->quantity = max(1, $quantity); return $this; }
    public function getRemainingContacts(): int { return $this->remainingContacts; }
    public function setRemainingContacts(int $remainingContacts): self { $this->remainingContacts = max(0, $remainingContacts); return $this; }
    public function getPrice(): int { return $this->price; }
    public function setPrice(int $price): self { $this->price = max(0, $price); return $this; }
    public function getPaymentId(): ?string { return $this->paymentId; }
    public function setPaymentId(string $paymentId): self { $this->paymentId = $paymentId; return $this; }
    public function getPaymentMethod(): string { return $this->paymentMethod; }
    public function setPaymentMethod(string $paymentMethod): self { $this->paymentMethod = $paymentMethod; return $this; }
    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): self { $this->isActive = $isActive; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function consume(): void
    {
        if (!$this->isActive || $this->remainingContacts < 1) {
            throw new \LogicException('Ce ticket ne contient plus de contact disponible.');
        }

        --$this->remainingContacts;
        if ($this->remainingContacts === 0) {
            $this->isActive = false;
        }
    }
}
