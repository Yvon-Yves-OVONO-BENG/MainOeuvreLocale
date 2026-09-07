<?php

namespace App\Controller\Web\Security;

use App\Service\SecurityService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class LoginController extends AbstractController
{
    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function __invoke(
        Request $request,
        AuthenticationUtils $authenticationUtils,
        SecurityService $securityService
    ): Response {
        $data = $securityService->getLoginPageData(
            $this->getUser(),
            $authenticationUtils->getLastUsername(),
            $authenticationUtils->getLastAuthenticationError()
        );

        if ($data['isAuthenticated']) {
            return $this->redirectToRoute(
                $data['redirectRoute'],
                $data['redirectParams']
            );
        }

        $emailFromReset = strtolower(trim((string) $request->query->get('email', '')));

        return $this->render('security/login.html.twig', [
            'last_username' => $emailFromReset !== '' ? $emailFromReset : $data['last_username'],
            'error' => $data['error'],
        ]);
    }
}
