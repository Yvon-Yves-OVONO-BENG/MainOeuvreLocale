<?php

namespace App\Entity;

use App\Repository\ReviewScoreRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReviewScoreRepository::class)]
class ReviewScore
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Review $review = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?ReviewCriteria $criteria = null;

    /**
     * Note de 1 à 10
     */
    #[ORM\Column]
    private int $score = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReview(): ?Review
    {
        return $this->review;
    }

    public function setReview(?Review $review): static
    {
        $this->review = $review;
        return $this;
    }

    public function getCriteria(): ?ReviewCriteria
    {
        return $this->criteria;
    }

    public function setCriteria(?ReviewCriteria $criteria): static
    {
        $this->criteria = $criteria;
        return $this;
    }

    public function getScore(): int
    {
        return $this->score;
    }

    public function setScore(int $score): static
    {
        if ($score < 1 || $score > 10) {
            throw new \InvalidArgumentException(
                'La note doit être comprise entre 1 et 10.'
            );
        }

        $this->score = $score;

        return $this;
    }
}