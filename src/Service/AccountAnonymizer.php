<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class AccountAnonymizer
{
    public function __construct(private EntityManagerInterface $em) {}

    public function anonymize(User $user, array $meta = []): void
    {
        $id = $user->getId() ?? random_int(1000, 9999);
        $stamp = (new \DateTimeImmutable())->format('YmdHis');

        // ✅ Email unique + non livrable
        $user->setEmail("deleted_{$id}_{$stamp}@example.invalid");

        // ✅ Téléphone neutralisé (si tu acceptes null, sinon garder un placeholder)
        $user->setPhone("000000000");

        // ✅ Désactiver
        $user->setIsActive(false);
        $user->setIsEmailVerified(false);

        // ✅ Tokens reset / verification si présents
        if (method_exists($user, 'setResetPasswordToken')) $user->setResetPasswordToken(null);
        if (method_exists($user, 'setEmailVerificationToken')) $user->setEmailVerificationToken(null);

        // ✅ Profils (si relations existent) — on “scrub” sans dépendre du code exact
        $pp = method_exists($user, 'getPersonalProfile') ? $user->getPersonalProfile() : null;
        if ($pp) {
            if (method_exists($pp, 'setFullName')) $pp->setFullName('Utilisateur supprimé');
            if (method_exists($pp, 'setPhoto')) $pp->setPhoto(null);
        }

        $pro = method_exists($user, 'getProfessionalProfile') ? $user->getProfessionalProfile() : null;
        if ($pro) {
            if (method_exists($pro, 'setBio')) $pro->setBio(null);
            if (method_exists($pro, 'setCv')) $pro->setCv(null);
        }

        // ✅ UpdatedAt si dispo
        if (method_exists($user, 'setUpdatedAt')) {
            $user->setUpdatedAt(new \DateTime());
        }

        $this->em->flush();
    }
}