<?php

namespace App\Controller\Web\ResetPassword;

use App\Form\ForgotPasswordFormType;
use App\Service\ResetPasswordService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ForgotController extends AbstractController
{
    #[Route('/forgot-password', name: 'app_forgot_password', methods: ['GET'])]
    public function __invoke(ResetPasswordService $resetPasswordService): Response
    {
        $form = $this->createForm(ForgotPasswordFormType::class);

        return $this->render('security/forgot_password.html.twig', [
            'form' => $form->createView(),
            'csrfToken' => $resetPasswordService->getForgotPageData()['csrfToken'],
        ]);
    }
}