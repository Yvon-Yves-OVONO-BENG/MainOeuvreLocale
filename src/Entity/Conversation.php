<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\ConversationRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;

#[ORM\Entity(repositoryClass: ConversationRepository::class)]
#[ORM\Table(name: 'conversation')]
#[ORM\Index(name: 'idx_conversation_last_message_at', columns: ['last_message_at'])]
class Conversation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * ✅ Participant A (User)
     * Astuce: stocke toujours le plus petit user_id dans participantA, et le plus grand dans participantB,
     * pour garantir l’unicité du duo.
     */
    #[ORM\ManyToOne(inversedBy: 'conversationsAsA')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $participantA = null;

    /**
     * ✅ Participant B (User)
     */
    #[ORM\ManyToOne(inversedBy: 'conversationsAsB')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $participantB = null;

    /**
     * Optionnel: utile si ton chat est “lié à un talent”.
     * Si tu n’en as pas besoin, tu peux enlever ce champ.
     */
    #[ORM\ManyToOne(inversedBy: 'conversations')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?ProfessionalProfile $professionalProfile = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastMessageAt = null;

    /**
     * @var Collection<int, Message>
     */
    #[ORM\OneToMany(targetEntity: Message::class, mappedBy: 'conversation', orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $messages;

    public function __construct()
    {
        $this->messages = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getParticipantA(): ?User { return $this->participantA; }
    public function setParticipantA(?User $user): static { $this->participantA = $user; return $this; }

    public function getParticipantB(): ?User { return $this->participantB; }
    public function setParticipantB(?User $user): static { $this->participantB = $user; return $this; }

    public function getProfessionalProfile(): ?ProfessionalProfile { return $this->professionalProfile; }
    public function setProfessionalProfile(?ProfessionalProfile $p): static { $this->professionalProfile = $p; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $dt): static { $this->createdAt = $dt; return $this; }

    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(?\DateTimeImmutable $dt): static { $this->updatedAt = $dt; return $this; }

    public function getLastMessageAt(): ?\DateTimeImmutable { return $this->lastMessageAt; }
    public function setLastMessageAt(?\DateTimeImmutable $dt): static { $this->lastMessageAt = $dt; return $this; }

    /**
     * @return Collection<int, Message>
     */
    public function getMessages(): Collection { return $this->messages; }

    public function addMessage(Message $message): static
    {
        if (!$this->messages->contains($message)) {
            $this->messages->add($message);
            $message->setConversation($this);
            $this->lastMessageAt = $message->getCreatedAt();
            $this->updatedAt = new \DateTimeImmutable();
        }
        return $this;
    }

    public function removeMessage(Message $message): static
    {
        if ($this->messages->removeElement($message)) {
            if ($message->getConversation() === $this) {
                $message->setConversation(null);
            }
            $this->updatedAt = new \DateTimeImmutable();
        }
        return $this;
    }

    /**
     * ✅ Helper: récupérer l'autre participant (pour afficher “le contact”)
     */

    public function getOtherParticipant(User $me): ?User
    {
        $a = $this->getParticipantA();
        $b = $this->getParticipantB();

        if ($a instanceof User && $a->getId() === $me->getId()) {
            return $b;
        }

        if ($b instanceof User && $b->getId() === $me->getId()) {
            return $a;
        }

        return null;
    }
}
