<?php

namespace App\Controller\Web\VerifyEmail;

use App\Service\VerifyEmailService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class VerifyController extends AbstractController
{
    #[Route('/verify-email/{token}', name: 'verify_email', methods: ['GET'])]
    public function __invoke(string $token, VerifyEmailService $verifyEmailService): Response
    {
        $result = $verifyEmailService->verify($token);

        $this->addFlash($result['flashType'], $result['message']);

        return $this->redirectToRoute(
            $result['routeName'],
            $result['routeParams']
        );
    }
}