<?php

namespace App\Entity;

use App\Entity\Conversation;
use App\Entity\Country;
use App\Entity\EmailVerifications;
use App\Entity\Favorite;
use App\Entity\Friendship;
use App\Entity\Invoices;
use App\Entity\Job;
use App\Entity\Message;
use App\Entity\Notification;
use App\Entity\Payment;
use App\Entity\PersonalProfile;
use App\Entity\ProfessionalProfile;
use App\Entity\Rating;
use App\Entity\Report;
use App\Entity\Review;
use App\Entity\StatusJob;
use App\Entity\Subscription;
use App\Entity\TypeCompte;
use App\Entity\UserLog;
use App\Entity\View;
use App\Entity\UserTwoFactor;
use App\Util\HashedSlugGenerator;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use App\Entity\MessageReaction;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[ORM\UniqueConstraint(name: 'uniq_user_slug', fields: ['slug'])]
#[ORM\HasLifecycleCallbacks]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private ?string $slug = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column]
    private ?bool $isActive = null;

    #[ORM\Column]
    private ?bool $isEmailVerified = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $emailVerificationToken = null;


    #[ORM\Column(nullable: true)]
    private ?\DateTime $emailVerificationExpiresAt = null;

    #[ORM\Column]
    private ?\DateTime $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $updatedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $lastLoginAt = null;

    /**
     * @var Collection<int, EmailVerifications>
     */
    #[ORM\OneToMany(targetEntity: EmailVerifications::class, mappedBy: 'user')]
    private Collection $emailVerifications;

    #[ORM\Column(nullable: true)]
    private ?string $resetPasswordToken = null;

    /**
     * @var Collection<int, Subscription>
     */
    #[ORM\OneToMany(targetEntity: Subscription::class, mappedBy: 'user')]
    private Collection $subscriptions;

    /**
     * @var Collection<int, Payment>
     */
    #[ORM\OneToMany(targetEntity: Payment::class, mappedBy: 'user')]
    private Collection $payments;

    /**
     * @var Collection<int, StatusJob>
     */
    #[ORM\OneToMany(targetEntity: StatusJob::class, mappedBy: 'user')]
    private Collection $statusJobs;

    /**
     * @var Collection<int, Invoices>
     */
    #[ORM\OneToMany(targetEntity: Invoices::class, mappedBy: 'user')]
    private Collection $invoices;

    /**
     * @var Collection<int, UserLog>
     */
    #[ORM\OneToMany(targetEntity: UserLog::class, mappedBy: 'user')]
    private Collection $userLogs;

    /**
     * @var Collection<int, Notification>
     */
    #[ORM\OneToMany(targetEntity: Notification::class, mappedBy: 'user')]
    private Collection $notifications;

    /**
     * @var Collection<int, Review>
     */
    #[ORM\OneToMany(targetEntity: Review::class, mappedBy: 'client')]
    private Collection $reviews;

    #[ORM\OneToOne(mappedBy: 'user', targetEntity: PersonalProfile::class)]
    private ?PersonalProfile $personalProfile = null;

    #[ORM\OneToOne(mappedBy: 'user', targetEntity: ProfessionalProfile::class)]
    private ?ProfessionalProfile $professionalProfile = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $resetPasswordExpiresAt = null;

    #[ORM\OneToMany(targetEntity: Report::class, mappedBy: 'user')]
    private Collection $reports;

    #[ORM\OneToMany(targetEntity: Report::class, mappedBy: 'user')]
    private Collection $targetUsers;

    #[ORM\OneToMany(targetEntity: Report::class, mappedBy: 'user')]
    private Collection $handledBys;

    #[ORM\OneToMany(targetEntity: Favorite::class, mappedBy: 'user')]
    private Collection $favorites;

    #[ORM\ManyToOne(inversedBy: 'users')]
    #[ORM\JoinColumn(nullable: true)]
    private ?TypeCompte $typeCompte = null;

    #[ORM\OneToMany(targetEntity: Rating::class, mappedBy: 'user')]
    private Collection $ratings;

    #[ORM\OneToMany(targetEntity: View::class, mappedBy: 'viewer')]
    private Collection $views;

    #[ORM\OneToMany(mappedBy: 'participantA', targetEntity: Conversation::class)]
    private Collection $conversationsAsA;

    #[ORM\OneToMany(mappedBy: 'participantB', targetEntity: Conversation::class)]
    private Collection $conversationsAsB;

    #[ORM\OneToMany(mappedBy: 'sender', targetEntity: Message::class)]
    private Collection $messages;

    #[ORM\OneToMany(mappedBy: 'requester', targetEntity: Friendship::class)]
    private Collection $sentFriendships;

    #[ORM\OneToMany(mappedBy: 'addressee', targetEntity: Friendship::class)]
    private Collection $receivedFriendships;

    #[ORM\OneToMany(mappedBy: 'reporter', targetEntity: Report::class, orphanRemoval: true)]
    private Collection $reportsSent;

    #[ORM\OneToMany(mappedBy: 'targetUser', targetEntity: Report::class, orphanRemoval: true)]
    private Collection $reportsReceived;

    #[ORM\ManyToOne(inversedBy: 'users')]
    private ?Country $country = null;

    #[ORM\OneToMany(targetEntity: Job::class, mappedBy: 'createdBy')]
    private Collection $jobs;

    /**
     * @var Collection<int, FavoriJob>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: FavoriJob::class, orphanRemoval: true)]
    private Collection $favoriteJobs;

    /**
     * @var Collection<int, JobView>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: JobView::class, orphanRemoval: true)]
    private Collection $jobViews;

    #[ORM\Column(options: ['default' => false])]
    private bool $profileViewsPrivate = false;

    #[ORM\OneToOne(mappedBy: 'user', targetEntity: UserTwoFactor::class, cascade: ['persist', 'remove'])]
    private ?UserTwoFactor $userTwoFactor = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $isOnline = false;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastSeenAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastDisconnectedAt = null;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: MessageReaction::class, orphanRemoval: true)]
    private Collection $messageReactions;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $googleId = null;

    #[ORM\Column(length: 191, nullable: true, unique: true)]
    private ?string $facebookId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $tiktokId = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $registrationProvider = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $avatarUrl = null;

    #[ORM\Column(length: 191, nullable: true, unique: true)]
    private ?string $appleId = null;

    #[ORM\Column(length: 191, nullable: true, unique: true)]
    private ?string $microsoftId = null;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $profileType = null;

    public function __construct()
    {
        $this->slug = HashedSlugGenerator::generate();
        $this->emailVerifications = new ArrayCollection();
        $this->subscriptions = new ArrayCollection();
        $this->payments = new ArrayCollection();
        $this->statusJobs = new ArrayCollection();
        $this->messages = new ArrayCollection();
        $this->invoices = new ArrayCollection();
        $this->userLogs = new ArrayCollection();
        $this->notifications = new ArrayCollection();
        $this->reviews = new ArrayCollection();
        $this->reports = new ArrayCollection();
        $this->targetUsers = new ArrayCollection();
        $this->handledBys = new ArrayCollection();
        $this->favorites = new ArrayCollection();
        $this->ratings = new ArrayCollection();
        $this->views = new ArrayCollection();
        $this->conversationsAsA = new ArrayCollection();
        $this->conversationsAsB = new ArrayCollection();
        $this->sentFriendships = new ArrayCollection();
        $this->receivedFriendships = new ArrayCollection(); 
        $this->reportsSent = new ArrayCollection();
        $this->reportsReceived = new ArrayCollection();
        $this->jobs = new ArrayCollection();
        $this->favoriteJobs = new ArrayCollection();
        $this->jobViews = new ArrayCollection();
        $this->messageReactions = new ArrayCollection();

    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = trim($slug);

        return $this;
    }

    #[ORM\PrePersist]
    public function ensureSlug(): void
    {
        if ($this->slug === null || trim($this->slug) === '') {
            $this->slug = HashedSlugGenerator::generate();
        }
    }

    /**
     * Retourne le nom public sûr correspondant au type de compte.
     *
     * Une compagnie est présentée avec son nom commercial ou sa raison sociale.
     * Les autres comptes utilisent leur nom complet. Une adresse e-mail ou une
     * valeur technique ne doit jamais être exposée comme identité publique.
     */
    public function getPublicDisplayName(): string
    {
        $profile = $this->personalProfile;
        $candidates = [];

        if (
            in_array('ROLE_COMPANY', $this->getRoles(), true)
            || in_array('ROLE_ENTREPRISE', $this->getRoles(), true)
        ) {
            $candidates[] = $profile?->getCompanyTradeName();
            $candidates[] = $profile?->getCompanyLegalName();
        }

        $candidates[] = $profile?->getFullName();

        foreach ($candidates as $candidate) {
            if ($this->isValidPublicName($candidate)) {
                return trim((string) $candidate);
            }
        }

        return 'Profil sans nom';
    }

    /**
     * Indique si l'utilisateur possède une identité exploitable publiquement.
     */
    public function hasPublicDisplayName(): bool
    {
        return $this->getPublicDisplayName() !== 'Profil sans nom';
    }

    /**
     * Valide un nom public et refuse les espaces, placeholders et e-mails.
     */
    private function isValidPublicName(?string $candidate): bool
    {
        $name = trim((string) $candidate);
        $email = trim((string) ($this->email ?? ''));

        if ($name === '' || in_array(mb_strtolower($name), ['-', '—', 'profil sans nom'], true)) {
            return false;
        }

        return filter_var($name, FILTER_VALIDATE_EMAIL) === false
            && ($email === '' || strcasecmp($name, $email) !== 0);
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */

    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // @deprecated, to be removed when upgrading to Symfony 8
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function isEmailVerified(): ?bool
    {
        return $this->isEmailVerified;
    }

    public function setIsEmailVerified(bool $isEmailVerified): static
    {
        $this->isEmailVerified = $isEmailVerified;

        return $this;
    }

    public function getEmailVerificationToken(): ?string
    {
        return $this->emailVerificationToken;
    }

    public function setEmailVerificationToken(?string $emailVerificationToken): static
    {
        $this->emailVerificationToken = $emailVerificationToken;

        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getLastLoginAt(): ?\DateTime
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(?\DateTime $lastLoginAt): static
    {
        $this->lastLoginAt = $lastLoginAt;

        return $this;
    }

    /**
     * @return Collection<int, EmailVerifications>
     */
    public function getEmailVerifications(): Collection
    {
        return $this->emailVerifications;
    }

    public function addEmailVerification(EmailVerifications $emailVerification): static
    {
        if (!$this->emailVerifications->contains($emailVerification)) {
            $this->emailVerifications->add($emailVerification);
            $emailVerification->setUser($this);
        }

        return $this;
    }

    public function removeEmailVerification(EmailVerifications $emailVerification): static
    {
        if ($this->emailVerifications->removeElement($emailVerification)) {
            // set the owning side to null (unless already changed)
            if ($emailVerification->getUser() === $this) {
                $emailVerification->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Subscription>
     */
    public function getSubscriptions(): Collection
    {
        return $this->subscriptions;
    }

    public function addSubscription(Subscription $subscription): static
    {
        if (!$this->subscriptions->contains($subscription)) {
            $this->subscriptions->add($subscription);
            $subscription->setUser($this);
        }

        return $this;
    }

    public function removeSubscription(Subscription $subscription): static
    {
        if ($this->subscriptions->removeElement($subscription)) {
            // set the owning side to null (unless already changed)
            if ($subscription->getUser() === $this) {
                $subscription->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Payment>
     */
    public function getPayments(): Collection
    {
        return $this->payments;
    }

    public function addPayment(Payment $payment): static
    {
        if (!$this->payments->contains($payment)) {
            $this->payments->add($payment);
            $payment->setUser($this);
        }

        return $this;
    }

    public function removePayment(Payment $payment): static
    {
        if ($this->payments->removeElement($payment)) {
            // set the owning side to null (unless already changed)
            if ($payment->getUser() === $this) {
                $payment->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, StatusJob>
     */
    public function getStatusJobs(): Collection
    {
        return $this->statusJobs;
    }

    /**
     * @return Collection<int, Message>
     */
    public function getMessages(): Collection
    {
        return $this->messages;
    }

    public function addMessage(Message $message): static
    {
        if (!$this->messages->contains($message)) {
            $this->messages->add($message);
            $message->setSender($this);
        }

        return $this;
    }

    public function removeMessage(Message $message): static
    {
        if ($this->messages->removeElement($message)) {
            // set the owning side to null (unless already changed)
            if ($message->getSender() === $this) {
                $message->setSender(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Invoices>
     */
    public function getInvoices(): Collection
    {
        return $this->invoices;
    }

    public function addInvoice(Invoices $invoice): static
    {
        if (!$this->invoices->contains($invoice)) {
            $this->invoices->add($invoice);
            $invoice->setUser($this);
        }

        return $this;
    }

    public function removeInvoice(Invoices $invoice): static
    {
        if ($this->invoices->removeElement($invoice)) {
            // set the owning side to null (unless already changed)
            if ($invoice->getUser() === $this) {
                $invoice->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, UserLog>
     */
    public function getUserLogs(): Collection
    {
        return $this->userLogs;
    }

    public function addUserLog(UserLog $userLog): static
    {
        if (!$this->userLogs->contains($userLog)) {
            $this->userLogs->add($userLog);
            $userLog->setUser($this);
        }

        return $this;
    }

    public function removeUserLog(UserLog $userLog): static
    {
        if ($this->userLogs->removeElement($userLog)) {
            // set the owning side to null (unless already changed)
            if ($userLog->getUser() === $this) {
                $userLog->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Notification>
     */
    public function getNotifications(): Collection
    {
        return $this->notifications;
    }

    public function addNotification(Notification $notification): static
    {
        if (!$this->notifications->contains($notification)) {
            $this->notifications->add($notification);
            $notification->setUser($this);
        }

        return $this;
    }

    public function removeNotification(Notification $notification): static
    {
        if ($this->notifications->removeElement($notification)) {
            // set the owning side to null (unless already changed)
            if ($notification->getUser() === $this) {
                $notification->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Review>
     */
    public function getReviews(): Collection
    {
        return $this->reviews;
    }

    public function getPersonalProfile(): ?PersonalProfile
    {
        return $this->personalProfile;
    }

    public function setPersonalProfile(?PersonalProfile $personalProfile): self
    {
        $this->personalProfile = $personalProfile;

        // Assure que le côté propriétaire est également mis à jour
        if ($personalProfile !== null && $personalProfile->getUser() !== $this) {
            $personalProfile->setUser($this);
        }

        return $this;
    }

    public function getProfessionalProfile(): ?ProfessionalProfile
    {
        return $this->professionalProfile;
    }

    public function setProfessionalProfile(?ProfessionalProfile $professionalProfile): self
    {
        $this->professionalProfile = $professionalProfile;

        // Assure que le côté propriétaire est également mis à jour
        if ($professionalProfile !== null && $professionalProfile->getUser() !== $this) {
            $professionalProfile->setUser($this);
        }

        return $this;
    }

    public function getEmailVerificationExpiresAt(): ?\DateTime
    {
        return $this->emailVerificationExpiresAt;
    }

    public function setEmailVerificationExpiresAt(?\DateTime $date): static
    {
        $this->emailVerificationExpiresAt = $date;
        return $this;
    }

    public function getResetPasswordToken(): ?string
    {
        return $this->resetPasswordToken;
    }

    public function setResetPasswordToken(?string $token): self
    {
        $this->resetPasswordToken = $token;
        return $this;
    }

    public function getResetPasswordExpiresAt(): ?\DateTimeInterface
    {
        return $this->resetPasswordExpiresAt;
    }

    public function setResetPasswordExpiresAt(?\DateTimeInterface $resetPasswordExpiresAt): static
    {
        $this->resetPasswordExpiresAt = $resetPasswordExpiresAt;

        return $this;
    }

    /**
     * @return Collection<int, Report>
     */
    public function getReports(): Collection
    {
        return $this->reports;
    }

    public function addReport(Report $report): static
    {
        if (!$this->reports->contains($report)) {
            $this->reports->add($report);
            $report->setReporter($this);
        }

        return $this;
    }

    public function removeReport(Report $report): static
    {
        if ($this->reports->removeElement($report)) {
            // set the owning side to null (unless already changed)
            if ($report->getReporter() === $this) {
                $report->setReporter(null);
            }
        }

        return $this;
    }

    ////////////////////////////
    public function addTargetUser(Report $targetUser): static
    {
        if (!$this->targetUsers->contains($targetUser)) {
            $this->targetUsers->add($targetUser);
            $targetUser->setTargetUser($this);
        }

        return $this;
    }

    public function removeTargetUser(Report $targetUser): static
    {
        if ($this->targetUsers->removeElement($targetUser)) {
            // set the owning side to null (unless already changed)
            if ($targetUser->getTargetUser() === $this) {
                $targetUser->setTargetUser(null);
            }
        }

        return $this;
    }

    ////////////////////////////
    public function addHandledBy(Report $handledBy): static
    {
        if (!$this->handledBys->contains($handledBy)) {
            $this->handledBys->add($handledBy);
            $handledBy->setHandledBy($this);
        }

        return $this;
    }

    public function removeHandledBy(Report $handledBy): static
    {
        if ($this->handledBys->removeElement($handledBy)) {
            // set the owning side to null (unless already changed)
            if ($handledBy->getHandledBy() === $this) {
                $handledBy->setHandledBy(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Favorite>
     */
    public function getFavorites(): Collection
    {
        return $this->favorites;
    }

    public function addFavorite(Favorite $favorite): static
    {
        if (!$this->favorites->contains($favorite)) {
            $this->favorites->add($favorite);
            $favorite->setUser($this);
        }

        return $this;
    }

    public function removeFavorite(Favorite $favorite): static
    {
        if ($this->favorites->removeElement($favorite)) {
            // set the owning side to null (unless already changed)
            if ($favorite->getUser() === $this) {
                $favorite->setUser(null);
            }
        }

        return $this;
    }

    public function getTypeCompte(): ?TypeCompte
    {
        return $this->typeCompte;
    }

    public function setTypeCompte(?TypeCompte $typeCompte): static
    {
        $this->typeCompte = $typeCompte;

        return $this;
    }

    /**
     * @return Collection<int, Rating>
     */
    public function getRatings(): Collection
    {
        return $this->ratings;
    }

    public function addRating(Rating $rating): static
    {
        if (!$this->ratings->contains($rating)) {
            $this->ratings->add($rating);
            $rating->setUser($this);
        }

        return $this;
    }

    public function removeRating(Rating $rating): static
    {
        if ($this->ratings->removeElement($rating)) {
            // set the owning side to null (unless already changed)
            if ($rating->getUser() === $this) {
                $rating->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, View>
     */
    public function getViews(): Collection
    {
        return $this->views;
    }

    public function addView(View $view): static
    {
        if (!$this->views->contains($view)) {
            $this->views->add($view);
            $view->setViewer($this);
        }

        return $this;
    }

    public function removeView(View $view): static
    {
        if ($this->views->removeElement($view)) {
            // set the owning side to null (unless already changed)
            if ($view->getViewer() === $this) {
                $view->setViewer(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Conversation>
     */
    public function getConversationsAsA(): Collection
    {
        return $this->conversationsAsA;
    }

    public function addConversationAsA(Conversation $conversation): static
    {
        if (!$this->conversationsAsA->contains($conversation)) {
            $this->conversationsAsA->add($conversation);
            $conversation->setParticipantA($this);
        }

        return $this;
    }

    public function removeConversationAsA(Conversation $conversation): static
    {
        if ($this->conversationsAsA->removeElement($conversation)) {
            if ($conversation->getParticipantA() === $this) {
                $conversation->setParticipantA(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Conversation>
     */
    public function getConversationsAsB(): Collection
    {
        return $this->conversationsAsB;
    }

    public function addConversationAsB(Conversation $conversation): static
    {
        if (!$this->conversationsAsB->contains($conversation)) {
            $this->conversationsAsB->add($conversation);
            $conversation->setParticipantB($this);
        }

        return $this;
    }

    public function removeConversationAsB(Conversation $conversation): static
    {
        if ($this->conversationsAsB->removeElement($conversation)) {
            if ($conversation->getParticipantB() === $this) {
                $conversation->setParticipantB(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Conversation>
     */
    public function getAllConversations(): Collection
    {
        return new ArrayCollection(
            array_merge(
                $this->conversationsAsA->toArray(),
                $this->conversationsAsB->toArray()
            )
        );
    }

    public function getSentFriendships(): Collection
    {
        return $this->sentFriendships;
    }

    public function getReceivedFriendships(): Collection
    {
        return $this->receivedFriendships;
    }


    public function addSentFriendship(Friendship $friendship): self
    {
        if (!$this->sentFriendships->contains($friendship)) {
            $this->sentFriendships[] = $friendship;
            $friendship->setRequester($this);
        }
        return $this;
    }

    public function addReceivedFriendship(Friendship $friendship): self
    {
        if (!$this->receivedFriendships->contains($friendship)) {
            $this->receivedFriendships[] = $friendship;
            $friendship->setAddressee($this);
        }
        return $this;
    }


    /**
     * @return Collection<int, Report>
     */
    public function getReportsSent(): Collection
    {
        return $this->reportsSent;
    }

    public function addReportSent(Report $report): self
    {
        if (!$this->reportsSent->contains($report)) {
            $this->reportsSent->add($report);
            $report->setReporter($this);
        }
        return $this;
    }

    public function removeReportSent(Report $report): self
    {
        if ($this->reportsSent->removeElement($report)) {
            // met à null seulement si c'est bien moi
            if ($report->getReporter() === $this) {
                $report->setReporter(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Report>
     */
    public function getReportsReceived(): Collection
    {
        return $this->reportsReceived;
    }

    public function addReportReceived(Report $report): self
    {
        if (!$this->reportsReceived->contains($report)) {
            $this->reportsReceived->add($report);
            $report->setTargetUser($this);
        }
        return $this;
    }

    public function removeReportReceived(Report $report): self
    {
        if ($this->reportsReceived->removeElement($report)) {
            if ($report->getTargetUser() === $this) {
                $report->setTargetUser(null);
            }
        }
        return $this;
    }

    public function getCountry(): ?Country
    {
        return $this->country;
    }

    public function setCountry(?Country $country): static
    {
        $this->country = $country;

        return $this;
    }

    /**
     * @return Collection<int, Job>
     */
    public function getJobs(): Collection
    {
        return $this->jobs;
    }

    public function addJob(Job $job): static
    {
        if (!$this->jobs->contains($job)) {
            $this->jobs->add($job);
            $job->setCreatedBy($this);
        }

        return $this;
    }

    public function removeJob(Job $job): static
    {
        if ($this->jobs->removeElement($job)) {
            // set the owning side to null (unless already changed)
            if ($job->getCreatedBy() === $this) {
                $job->setCreatedBy(null);
            }
        }

        return $this;
    }


    public function getFavoriteJobs(): Collection
    {
        return $this->favoriteJobs;
    }

    public function getJobViews(): Collection
    {
        return $this->jobViews;
    }

    public function isProfileViewsPrivate(): bool
    {
        return $this->profileViewsPrivate;
    }

    public function setProfileViewsPrivate(bool $profileViewsPrivate): self
    {
        $this->profileViewsPrivate = $profileViewsPrivate;
        return $this;
    }

    public function getUserTwoFactor(): ?UserTwoFactor
    {
        return $this->userTwoFactor;
    }

    public function setUserTwoFactor(?UserTwoFactor $userTwoFactor): static
    {
        $this->userTwoFactor = $userTwoFactor;

        if ($userTwoFactor !== null && $userTwoFactor->getUser() !== $this) {
            $userTwoFactor->setUser($this);
        }

        return $this;
    }

    public function getLastSeenAt(): ?\DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function setLastSeenAt(?\DateTimeImmutable $lastSeenAt): self
    {
        $this->lastSeenAt = $lastSeenAt;
        return $this;
    }

    public function getLastDisconnectedAt(): ?\DateTimeImmutable
    {
        return $this->lastDisconnectedAt;
    }

    public function setLastDisconnectedAt(?\DateTimeImmutable $lastDisconnectedAt): self
    {
        $this->lastDisconnectedAt = $lastDisconnectedAt;
        return $this;
    }

    public function isOnline(): bool
    {
        $lastSeenAt = $this->getLastSeenAt();

        if (!$lastSeenAt instanceof \DateTimeInterface) {
            return false;
        }

        $diff = time() - $lastSeenAt->getTimestamp();

        return $diff >= 0 && $diff <= 70;
    }

    public function getIsOnline(): bool
    {
        return $this->isOnline();
    }

    public function setIsOnline(bool $isOnline): self
    {
        $this->isOnline = $isOnline;
        return $this;
    }

    public function getPresenceStatus(): string
    {
        $lastSeenAt = $this->getLastSeenAt();
        $now = time();

        if ($lastSeenAt instanceof \DateTimeImmutable) {
            $diffSeen = $now - $lastSeenAt->getTimestamp();

            if ($diffSeen >= 0 && $diffSeen <= 70) {
                return 'online';
            }
        }

        $lastDisconnectedAt = $this->getLastDisconnectedAt();

        if ($lastDisconnectedAt instanceof \DateTimeImmutable) {
            $diffDisconnected = $now - $lastDisconnectedAt->getTimestamp();

            if ($diffDisconnected >= 0 && $diffDisconnected <= 120) {
                return 'recently_offline';
            }
        }

        return 'offline';
    }

    /**
     * @return Collection<int, MessageReaction>
     */
    public function getMessageReactions(): Collection
    {
        return $this->messageReactions;
    }

    public function addMessageReaction(MessageReaction $messageReaction): static
    {
        if (!$this->messageReactions->contains($messageReaction)) {
            $this->messageReactions->add($messageReaction);
            $messageReaction->setUser($this);
        }

        return $this;
    }

    public function removeMessageReaction(MessageReaction $messageReaction): static
    {
        if ($this->messageReactions->removeElement($messageReaction)) {
            if ($messageReaction->getUser() === $this) {
                $messageReaction->setUser(null);
            }
        }

        return $this;
    }

    public function getGoogleId(): ?string
    {
        return $this->googleId;
    }

    public function setGoogleId(?string $googleId): static
    {
        $this->googleId = $googleId;

        return $this;
    }

    public function getFacebookId(): ?string
    {
        return $this->facebookId;
    }

    public function setFacebookId(?string $facebookId): static
    {
        $this->facebookId = $facebookId;

        return $this;
    }

    public function getTiktokId(): ?string
    {
        return $this->tiktokId;
    }

    public function setTiktokId(?string $tiktokId): static
    {
        $this->tiktokId = $tiktokId;

        return $this;
    }

    public function getRegistrationProvider(): ?string
    {
        return $this->registrationProvider;
    }

    public function setRegistrationProvider(?string $registrationProvider): static
    {
        $this->registrationProvider = $registrationProvider;

        return $this;
    }

    public function getAvatarUrl(): ?string
    {
        return $this->avatarUrl;
    }

    public function setAvatarUrl(?string $avatarUrl): static
    {
        $this->avatarUrl = $avatarUrl;

        return $this;
    }

    public function getAppleId(): ?string
    {
        return $this->appleId;
    }

    public function setAppleId(?string $appleId): static
    {
        $this->appleId = $appleId;

        return $this;
    }

    public function getMicrosoftId(): ?string
    {
        return $this->microsoftId;
    }

    public function setMicrosoftId(?string $microsoftId): static
    {
        $this->microsoftId = $microsoftId;

        return $this;
    }


    public function getProfileType(): ?string
    {
        return $this->profileType;
    }

    public function setProfileType(?string $profileType): self
    {
        $this->profileType = $profileType;
        return $this;
    }
}
