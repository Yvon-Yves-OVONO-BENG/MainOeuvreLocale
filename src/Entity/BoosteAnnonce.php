<?php

namespace App\Entity;

use App\Repository\BoosteAnnonceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BoosteAnnonceRepository::class)]
#[ORM\Table(name: 'booste_annonce')]
#[ORM\Index(name: 'IDX_BOOSTE_ANNONCE_STATUS_DATES', columns: ['statut', 'date_debut', 'date_fin'])]
class BoosteAnnonce
{
    public const STATUS_PENDING_PAYMENT = 'en_attente_paiement';
    public const STATUS_ACTIVE = 'actif';
    public const STATUS_EXPIRED = 'expire';
    public const STATUS_CANCELLED = 'annule';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Annonce::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Annonce $annonce = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 30)]
    private string $plan = 'essentiel';

    #[ORM\Column]
    private int $montant = 200;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $dureeSemaines = 1;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $niveauPriorite = 1;

    #[ORM\Column(options: ['default' => true])]
    private bool $renouvelable = true;

    #[ORM\Column(length: 30)]
    private string $statut = self::STATUS_PENDING_PAYMENT;

    #[ORM\Column(length: 40, unique: true)]
    private string $reference;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $dateDemande;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dateDebut = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dateFin = null;

    public function __construct()
    {
        $this->dateDemande = new \DateTimeImmutable();
        $this->reference = 'BA-' . strtoupper(bin2hex(random_bytes(8)));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAnnonce(): ?Annonce
    {
        return $this->annonce;
    }

    public function setAnnonce(Annonce $annonce): static
    {
        $this->annonce = $annonce;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getPlan(): string
    {
        return $this->plan;
    }

    public function setPlan(string $plan): static
    {
        $this->plan = trim($plan);

        return $this;
    }

    public function getMontant(): int
    {
        return $this->montant;
    }

    public function setMontant(int $montant): static
    {
        $this->montant = $montant;

        return $this;
    }

    public function getDureeSemaines(): int
    {
        return $this->dureeSemaines;
    }

    public function setDureeSemaines(int $dureeSemaines): static
    {
        $this->dureeSemaines = $dureeSemaines;

        return $this;
    }

    public function getNiveauPriorite(): int
    {
        return $this->niveauPriorite;
    }

    public function setNiveauPriorite(int $niveauPriorite): static
    {
        $this->niveauPriorite = $niveauPriorite;

        return $this;
    }

    public function isRenouvelable(): bool
    {
        return $this->renouvelable;
    }

    public function setRenouvelable(bool $renouvelable): static
    {
        $this->renouvelable = $renouvelable;

        return $this;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = trim($statut);

        return $this;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function getDateDemande(): \DateTimeImmutable
    {
        return $this->dateDemande;
    }

    public function setDateDemande(\DateTimeImmutable $dateDemande): static
    {
        $this->dateDemande = $dateDemande;

        return $this;
    }

    public function getDateDebut(): ?\DateTimeImmutable
    {
        return $this->dateDebut;
    }

    public function setDateDebut(?\DateTimeImmutable $dateDebut): static
    {
        $this->dateDebut = $dateDebut;

        return $this;
    }

    public function getDateFin(): ?\DateTimeImmutable
    {
        return $this->dateFin;
    }

    public function setDateFin(?\DateTimeImmutable $dateFin): static
    {
        $this->dateFin = $dateFin;

        return $this;
    }

    public function activate(?\DateTimeImmutable $startsAt = null): static
    {
        $this->dateDebut = $startsAt ?? new \DateTimeImmutable();
        $this->dateFin = $this->dateDebut->modify(sprintf('+%d weeks', $this->dureeSemaines));
        $this->statut = self::STATUS_ACTIVE;

        return $this;
    }

    public function isCurrentlyActive(): bool
    {
        $now = new \DateTimeImmutable();

        return $this->statut === self::STATUS_ACTIVE
            && $this->dateDebut !== null
            && $this->dateDebut <= $now
            && $this->dateFin !== null
            && $this->dateFin > $now;
    }
}
