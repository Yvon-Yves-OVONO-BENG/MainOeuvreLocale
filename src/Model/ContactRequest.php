<?php

namespace App\Model;

use Symfony\Component\Validator\Constraints as Assert;

class ContactRequest
{
    #[Assert\NotBlank(message: 'Veuillez renseigner votre nom complet.')]
    #[Assert\Length(
        min: 3,
        max: 120,
        minMessage: 'Le nom doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le nom ne doit pas dépasser {{ limit }} caractères.'
    )]
    #[Assert\Regex(
        pattern: "/^[\\p{L}\\p{M}\\s'’\\-]+$/u",
        message: 'Le nom ne doit pas contenir de chiffres ni de caractères spéciaux.'
    )]
    private ?string $name = '';

    #[Assert\NotBlank(message: 'Veuillez renseigner votre adresse email.')]
    #[Assert\Email(message: 'Veuillez renseigner une adresse email valide.')]
    #[Assert\Length(max: 180)]
    private ?string $email = '';

    #[Assert\Length(
        max: 30,
        maxMessage: 'Le numéro de téléphone ne doit pas dépasser {{ limit }} caractères.'
    )]
    private ?string $phone = '';

    #[Assert\NotBlank(message: 'Veuillez choisir un sujet.')]
    #[Assert\Choice(
        choices: ['support', 'account', 'payment', 'partnership', 'business', 'other'],
        message: 'Sujet invalide.'
    )]
    private ?string $subject = '';

    #[Assert\NotBlank(message: 'Veuillez saisir votre message.')]
    #[Assert\Length(
        min: 10,
        max: 3000,
        minMessage: 'Le message doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le message ne doit pas dépasser {{ limit }} caractères.'
    )]
    private ?string $message = '';

    #[Assert\IsTrue(message: 'Veuillez accepter d’être recontacté(e).')]
    private bool $consent = false;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name ? trim($name) : '';

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email ? trim($email) : '';

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): self
    {
        $this->phone = $phone ? trim($phone) : '';

        return $this;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(?string $subject): self
    {
        $this->subject = $subject ? trim($subject) : '';

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): self
    {
        $this->message = $message ? trim($message) : '';

        return $this;
    }

    public function isConsent(): bool
    {
        return $this->consent;
    }

    public function setConsent(bool $consent): self
    {
        $this->consent = $consent;

        return $this;
    }
}