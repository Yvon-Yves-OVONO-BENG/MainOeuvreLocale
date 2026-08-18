<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\EmailVerifications;
use App\Exception\EmailVerification\InvalidTokenException;
use App\Exception\EmailVerification\TokenAlreadyUsedException;
use App\Exception\EmailVerification\TokenExpiredException;
use App\Exception\EmailVerification\UserNotFoundException;
use Doctrine\ORM\EntityManagerInterface;

class EmailVerificationManager
{
    public function __construct(private EntityManagerInterface $em) {}

    /**
     * Vérifie le token, active le user, marque le token comme utilisé.
     * Retourne le User activé si tout est OK.
     */
    public function verifyAndActivate(string $token): User
    {
        $verification = $this->em->getRepository(EmailVerifications::class)
            ->findOneBy(['token' => $token]);
    
        if (!$verification) {
            throw new InvalidTokenException();
        }
    
        if ($verification->isUsed()) {
            throw new TokenAlreadyUsedException();
        }
    
        $expiresAt = $verification->getEspiresAt(); // (orthographe à corriger si possible)
        if ($expiresAt && $expiresAt < new \DateTimeImmutable()) {
            throw new TokenExpiredException();
        }
    
        $user = $verification->getUser();
        if (!$user instanceof User) {
            throw new UserNotFoundException();
        }
    
        // ✅ Activer + consommer token
        $user->setIsEmailVerified(true);
        $user->setIsActive(true);
    
        $verification->setIsUsed(true);
    
        // Optionnel mais safe si tu as des doutes sur le "managed state"
        $this->em->persist($user);
        $this->em->persist($verification);
    
        $this->em->flush();
    
        return $user;
    }

}
