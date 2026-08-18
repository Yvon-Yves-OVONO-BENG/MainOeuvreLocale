<?php

namespace App\Controller\Web;

use App\Repository\EmailVerificationsRepository; 
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class VerifyEmailController extends AbstractController
{
    #[Route('/verification/email/{token}', name: 'verify_email_legacy', methods: ['GET'])]
    public function verify(
        string $token,
        EmailVerificationsRepository $emailVerificationsRepository,
        EntityManagerInterface $em,
        TranslatorInterface $translator
    ): Response {
        
        $emailVerification = $emailVerificationsRepository->findOneBy([
            'token' => $token,
        ]);
        
        if (!$emailVerification) {
            $this->addFlash('danger', $translator->trans('Lien d’activation invalide.'));
            return $this->redirectToRoute('app_login');
        }

        $user = $emailVerification->getUser();

        if (!$user) {
            $this->addFlash('danger', $translator->trans('Lien d’activation invalide.'));
            return $this->redirectToRoute('app_login');
        }

        if ($emailVerification->isUsed()) {
            
            $this->addFlash('info', $translator->trans('Votre compte est déjà activé.'));
            return $this->redirectToRoute('app_login');
        }

        if (
            !$user->getEmailVerificationExpiresAt() ||
            $user->getEmailVerificationExpiresAt() < new \DateTimeImmutable()
        ) {
            $this->addFlash('danger', $translator->trans('Le lien d’activation a expiré.'));
            return $this->redirectToRoute('app_login');
        }
        
        $emailVerification->setIsUsed(true);

        $user
            ->setEmailVerificationToken(null)
            ->setEmailVerificationExpiresAt(null)
            ->setIsEmailVerified(true)
            ->setIsActive(true);

        $em->flush();

        $this->addFlash('success', $translator->trans('Votre compte a bien été activé. Veuillez vous connecter.'));

        return $this->redirectToRoute('app_login');
    }
}