<?php

namespace App\Entity;

use App\Repository\PersonalProfileRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PersonalProfileRepository::class)]
class PersonalProfile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $fullName = null;

    #[ORM\Column(length: 255)]
    private ?string $photo = null;

    #[ORM\Column(length: 255)]
    private ?string $adress = null;

    #[ORM\Column(length: 255)]
    private ?string $city = null;

    #[ORM\Column(length: 255)]
    private ?string $slug = null;

    #[ORM\ManyToOne(inversedBy: 'personalProfiles')]
    private ?Sexe $sexe = null;

    #[ORM\OneToOne(inversedBy: 'personalProfile', targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $cni = null;

    #[ORM\Column(length: 20, options: ['default' => 'pending'])]
    private string $cniStatus = 'pending'; // pending|verified|rejected

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $cniVerifiedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $cniVerifiedBy = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $companyLegalName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $companyTradeName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $companyRegistrationNumber = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $companyTaxNumber = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $companyContactName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $companyContactPhone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $companyWebsite = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $companyDescription = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFullName(): ?string
    {
        return $this->fullName;
    }

    public function setFullName(string $fullName): static
    {
        $this->fullName = $fullName;

        return $this;
    }

    public function getPhoto(): ?string
    {
        return $this->photo;
    }

    public function setPhoto(string $photo): static
    {
        $this->photo = $photo;

        return $this;
    }

    public function getAdress(): ?string
    {
        return $this->adress;
    }

    public function setAdress(string $adress): static
    {
        $this->adress = $adress;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(string $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getSexe(): ?Sexe
    {
        return $this->sexe;
    }

    public function setSexe(?Sexe $sexe): static
    {
        $this->sexe = $sexe;

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

    public function getCni(): ?string
    {
        return $this->cni;
    }

    public function setCni(?string $cni): static
    {
        $this->cni = $cni;

        return $this;
    }

    // getters/setters
    public function getCniStatus(): string { return $this->cniStatus; }
    public function setCniStatus(string $s): static { $this->cniStatus = $s; return $this; }

    public function getCniVerifiedAt(): ?\DateTimeInterface { return $this->cniVerifiedAt; }
    public function setCniVerifiedAt(?\DateTimeInterface $d): static { $this->cniVerifiedAt = $d; return $this; }

    public function getCniVerifiedBy(): ?User { return $this->cniVerifiedBy; }
    public function setCniVerifiedBy(?User $u): static { $this->cniVerifiedBy = $u; return $this; }


    public function getCompanyLegalName(): ?string
    {
        return $this->companyLegalName;
    }

    public function setCompanyLegalName(?string $companyLegalName): static
    {
        $this->companyLegalName = $companyLegalName;
        return $this;
    }

    public function getCompanyTradeName(): ?string
    {
        return $this->companyTradeName;
    }

    public function setCompanyTradeName(?string $companyTradeName): static
    {
        $this->companyTradeName = $companyTradeName;
        return $this;
    }

    public function getCompanyRegistrationNumber(): ?string
    {
        return $this->companyRegistrationNumber;
    }

    public function setCompanyRegistrationNumber(?string $companyRegistrationNumber): static
    {
        $this->companyRegistrationNumber = $companyRegistrationNumber;
        return $this;
    }

    public function getCompanyTaxNumber(): ?string
    {
        return $this->companyTaxNumber;
    }

    public function setCompanyTaxNumber(?string $companyTaxNumber): static
    {
        $this->companyTaxNumber = $companyTaxNumber;
        return $this;
    }

    public function getCompanyContactName(): ?string
    {
        return $this->companyContactName;
    }

    public function setCompanyContactName(?string $companyContactName): static
    {
        $this->companyContactName = $companyContactName;
        return $this;
    }

    public function getCompanyContactPhone(): ?string
    {
        return $this->companyContactPhone;
    }

    public function setCompanyContactPhone(?string $companyContactPhone): static
    {
        $this->companyContactPhone = $companyContactPhone;
        return $this;
    }

    public function getCompanyWebsite(): ?string
    {
        return $this->companyWebsite;
    }

    public function setCompanyWebsite(?string $companyWebsite): static
    {
        $this->companyWebsite = $companyWebsite;
        return $this;
    }

    public function getCompanyDescription(): ?string
    {
        return $this->companyDescription;
    }

    public function setCompanyDescription(?string $companyDescription): static
    {
        $this->companyDescription = $companyDescription;
        return $this;
    }

    public function isCompanyProfile(): bool
    {
        return $this->user !== null && in_array('ROLE_COMPANY', $this->user->getRoles(), true);
    }

    public function getDisplayIdentity(): ?string
    {
        if ($this->isCompanyProfile()) {
            return $this->companyTradeName
                ?: $this->companyLegalName
                ?: $this->companyContactName
                ?: $this->fullName;
        }

        return $this->fullName;
    }

    public function hasCompanyCoreData(): bool
    {
        return !empty($this->companyLegalName)
            || !empty($this->companyTradeName)
            || !empty($this->companyRegistrationNumber)
            || !empty($this->companyTaxNumber);
    }
}
