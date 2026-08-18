<?php

namespace App\Controller\Web\Registration;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\AccountActivationService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ResendActivationController extends AbstractController
{
    #[Route('/resend-activation', name: 'app_resend_activation', methods: ['POST'])]
    public function __invoke(
        Request $request,
        UserRepository $userRepository,
        AccountActivationService $accountActivationService,
        LoggerInterface $logger,
    ): Response {
        if (!$this->isCsrfTokenValid('resend_activation', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Votre session a expiré. Veuillez réessayer.');
            return $this->redirectToRoute('app_login');
        }

        $email = strtolower(trim((string) $request->request->get('email', '')));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('danger', 'Saisissez une adresse email valide.');
            return $this->redirectToRoute('app_login');
        }

        $user = $userRepository->findOneByEmailAddress($email);

        // Réponse volontairement générique lorsque le compte n'existe pas :
        // on évite de révéler les adresses enregistrées dans la plateforme.
        if (!$user instanceof User) {
            $this->addFlash('info', 'Si un compte correspondant existe et doit encore être activé, un nouveau lien lui sera envoyé.');
            return $this->redirectToRoute('app_login');
        }

        if ($user->IsEmailVerified() === true && $user->IsActive() === true) {
            $this->addFlash('info', 'Ce compte est déjà activé. Vous pouvez vous connecter.');
            return $this->redirectToRoute('app_login');
        }

        try {
            $accountActivationService->sendActivationEmail($user);
            $this->addFlash('success', 'Un nouveau lien d’activation vient d’être envoyé. Pensez à vérifier vos courriers indésirables.');
        } catch (\Throwable $e) {
            $logger->error('Échec du renvoi du mail d’activation', [
                'userId' => $user->getId(),
                'error' => $e->getMessage(),
            ]);
            $this->addFlash('warning', 'L’email n’a pas pu être envoyé pour le moment. Réessayez dans quelques instants.');
        }

        return $this->redirectToRoute('app_login');
    }
}
