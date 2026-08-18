<?php

namespace App\Entity;

use App\Repository\PlanRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlanRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Plan
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $name = null;

    #[ORM\Column(length: 50, unique: true)]
    private ?string $slug = null;

    #[ORM\Column]
    private ?int $price = 0;

    #[ORM\Column(name: 'duration_days')]
    private ?int $durationDays = 0;

    #[ORM\Column(name: 'max_contacts')]
    private ?int $maxContacts = 0;

    #[ORM\Column(name: 'can_chat')]
    private ?bool $canChat = false;

    #[ORM\Column(name: 'chat_with', length: 255, nullable: true)]
    private ?string $chatWith = null;

    #[ORM\Column(name: 'chat_type', length: 20)]
    private ?string $chatType = 'bidirectional';

    #[ORM\Column(name: 'can_view_missions')]
    private ?bool $canViewMissions = true;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $features = [];

    #[ORM\Column(name: 'is_active')]
    private ?bool $isActive = true;

    #[ORM\Column(name: 'created_at', type: 'datetime', nullable: false)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: 'datetime', nullable: false)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\OneToMany(mappedBy: 'plan', targetEntity: Subscription::class)]
    private Collection $subscriptions;

    #[ORM\Column(name: 'can_video_call', options: ['default' => false])]
    private ?bool $canVideoCall = false;

    #[ORM\OneToMany(mappedBy: 'plan', targetEntity: PlanDuration::class, cascade: ['persist', 'remove'])]
    private Collection $durations;

    public function __construct()
    {
        $this->subscriptions = new ArrayCollection();
        $this->durations = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    // ✅ Getters et Setters avec gestion des types

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;
        return $this;
    }

    public function getPrice(): ?int
    {
        return $this->price;
    }

    public function setPrice(int $price): self
    {
        $this->price = $price;
        return $this;
    }

    public function getDurationDays(): ?int
    {
        return $this->durationDays;
    }

    public function setDurationDays(int $durationDays): self
    {
        $this->durationDays = $durationDays;
        return $this;
    }

    public function getMaxContacts(): ?int
    {
        return $this->maxContacts;
    }

    public function setMaxContacts(int $maxContacts): self
    {
        $this->maxContacts = $maxContacts;
        return $this;
    }

    public function getCanChat(): ?bool
    {
        return $this->canChat;
    }

    public function setCanChat(bool $canChat): self
    {
        $this->canChat = $canChat;
        return $this;
    }

    public function getChatWith(): ?string
    {
        return $this->chatWith;
    }

    public function setChatWith(?string $chatWith): self
    {
        $this->chatWith = $chatWith;
        return $this;
    }

    public function getChatType(): ?string
    {
        return $this->chatType;
    }

    public function setChatType(string $chatType): self
    {
        $this->chatType = $chatType;
        return $this;
    }

    public function getCanViewMissions(): ?bool
    {
        return $this->canViewMissions;
    }

    public function setCanViewMissions(bool $canViewMissions): self
    {
        $this->canViewMissions = $canViewMissions;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getFeatures(): ?array
    {
        return $this->features;
    }

    public function setFeatures(?array $features): self
    {
        $this->features = $features;
        return $this;
    }

    public function getIsActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }

    // ✅ Setter avec gestion des types string et DateTime
    public function setCreatedAt(\DateTimeInterface|string $createdAt): self
    {
        if (is_string($createdAt)) {
            $createdAt = new \DateTime($createdAt);
        }
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    // ✅ Setter avec gestion des types string et DateTime
    public function setUpdatedAt(\DateTimeInterface|string $updatedAt): self
    {
        if (is_string($updatedAt)) {
            $updatedAt = new \DateTime($updatedAt);
        }
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function getSubscriptions(): Collection
    {
        return $this->subscriptions;
    }

    public function addSubscription(Subscription $subscription): self
    {
        if (!$this->subscriptions->contains($subscription)) {
            $this->subscriptions->add($subscription);
            $subscription->setPlan($this);
        }
        return $this;
    }

    public function removeSubscription(Subscription $subscription): self
    {
        if ($this->subscriptions->removeElement($subscription)) {
            if ($subscription->getPlan() === $this) {
                $subscription->setPlan(null);
            }
        }
        return $this;
    }

    // ✅ Méthode pour mettre à jour automatiquement updated_at
    #[ORM\PreUpdate]
    public function preUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }

    // Getter et Setter
    public function getCanVideoCall(): ?bool
    {
        return $this->canVideoCall;
    }

    public function setCanVideoCall(bool $canVideoCall): self
    {
        $this->canVideoCall = $canVideoCall;
        return $this;
    }


    /**
     * @return Collection<int, PlanDuration>
     */
    public function getDurations(): Collection
    {
        return $this->durations;
    }

    public function addDuration(PlanDuration $duration): self
    {
        if (!$this->durations->contains($duration)) {
            $this->durations->add($duration);
            $duration->setPlan($this);
        }
        return $this;
    }

    public function removeDuration(PlanDuration $duration): self
    {
        if ($this->durations->removeElement($duration)) {
            if ($duration->getPlan() === $this) {
                $duration->setPlan(null);
            }
        }
        return $this;
    }
}