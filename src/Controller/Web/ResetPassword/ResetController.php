<?php

namespace App\Controller\Web\ResetPassword;

use App\Form\ResetPasswordFormType;
use App\Service\ResetPasswordService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ResetController extends AbstractController
{
    #[Route('/reset-password/{token}', name: 'app_reset_password', methods: ['GET'])]
    public function __invoke(string $token, ResetPasswordService $resetPasswordService): Response
    {
        $data = $resetPasswordService->getResetPageData($token);

        if (!$data['ok']) {
            $this->addFlash($data['flashType'], $data['message']);

            return $this->redirectToRoute($data['routeName'], $data['routeParams']);
        }

        $form = $this->createForm(ResetPasswordFormType::class);

        return $this->render('security/reset_password.html.twig', [
            'form' => $form->createView(),
            'token' => $token,
            'csrfToken' => $data['csrfToken'],
        ]);
    }
}