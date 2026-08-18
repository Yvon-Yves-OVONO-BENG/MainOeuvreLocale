<?php

namespace App\Entity;

use App\Repository\AnnonceRepository;
use App\Util\HashedSlugGenerator;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AnnonceRepository::class)]
#[ORM\Table(name: 'annonce')]
#[ORM\Index(name: 'IDX_ANNONCE_PUBLICATION', columns: ['afficher_maintenant', 'publier', 'date_creation'])]
#[ORM\UniqueConstraint(name: 'uniq_annonce_slug', columns: ['slug'])]
#[ORM\HasLifecycleCallbacks]
class Annonce
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private ?string $slug = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: 'Le titre de l’annonce est obligatoire.')]
    #[Assert\Length(min: 5, max: 180)]
    private ?string $titre = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'La description de l’annonce est obligatoire.')]
    #[Assert\Length(min: 20, max: 5000)]
    private ?string $description = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank(message: 'La ville est obligatoire.')]
    #[Assert\Length(max: 120)]
    private ?string $ville = null;

    #[ORM\Column(nullable: true)]
    #[Assert\NotNull(message: 'Le salaire journalier est obligatoire.')]
    #[Assert\Positive(message: 'Le salaire journalier doit être supérieur à zéro.')]
    #[Assert\LessThanOrEqual(value: 100000000, message: 'Le salaire journalier indiqué est trop élevé.')]
    private ?int $salaireJournalier = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull(message: 'Sélectionnez une catégorie.')]
    private ?Categorie $categorie = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull(message: 'Sélectionnez une profession.')]
    private ?Profession $profession = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $afficherMaintenant = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $publier = false;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'publie_par_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $publiePar = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $datePublication = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $dateCreation;

    public function __construct()
    {
        $this->slug = HashedSlugGenerator::generate();
        $this->dateCreation = new \DateTimeImmutable();
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

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = trim($titre);

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = trim($description);

        return $this;
    }

    public function getVille(): ?string
    {
        return $this->ville;
    }

    public function setVille(string $ville): static
    {
        $this->ville = trim($ville);

        return $this;
    }

    public function getSalaireJournalier(): ?int
    {
        return $this->salaireJournalier;
    }

    public function setSalaireJournalier(?int $salaireJournalier): static
    {
        $this->salaireJournalier = $salaireJournalier;

        return $this;
    }

    public function getCategorie(): ?Categorie
    {
        return $this->categorie;
    }

    public function setCategorie(?Categorie $categorie): static
    {
        $this->categorie = $categorie;

        return $this;
    }

    public function getProfession(): ?Profession
    {
        return $this->profession;
    }

    public function setProfession(?Profession $profession): static
    {
        $this->profession = $profession;

        return $this;
    }

    public function isAfficherMaintenant(): bool
    {
        return $this->afficherMaintenant;
    }

    public function setAfficherMaintenant(bool $afficherMaintenant): static
    {
        $this->afficherMaintenant = $afficherMaintenant;

        return $this;
    }

    public function isPublier(): bool
    {
        return $this->publier;
    }

    public function setPublier(bool $publier): static
    {
        $this->publier = $publier;

        return $this;
    }

    public function getPubliePar(): ?User
    {
        return $this->publiePar;
    }

    public function setPubliePar(?User $publiePar): static
    {
        $this->publiePar = $publiePar;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getDatePublication(): ?\DateTimeImmutable
    {
        return $this->datePublication;
    }

    public function setDatePublication(?\DateTimeImmutable $datePublication): static
    {
        $this->datePublication = $datePublication;

        return $this;
    }

    public function getDateCreation(): \DateTimeImmutable
    {
        return $this->dateCreation;
    }

    public function setDateCreation(\DateTimeImmutable $dateCreation): static
    {
        $this->dateCreation = $dateCreation;

        return $this;
    }
}
