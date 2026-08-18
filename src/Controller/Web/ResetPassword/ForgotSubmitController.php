<?php

namespace App\Controller\Web\ResetPassword;

use App\Form\ForgotPasswordFormType;
use App\Service\ResetPasswordService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ForgotSubmitController extends AbstractController
{
    #[Route('/forgot-password', name: 'app_forgot_password_submit', methods: ['POST'])]
    public function __invoke(Request $request, ResetPasswordService $resetPasswordService): Response
    {
        $form = $this->createForm(ForgotPasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $result = $resetPasswordService->requestPasswordReset(
                (string) $form->get('email')->getData(),
                $request->request->get('csrfToken')
            );

            $this->addFlash($result['flashType'], $result['message']);

            return $this->redirectToRoute($result['routeName'], $result['routeParams']);
        }

        return $this->render('security/forgot_password.html.twig', [
            'form' => $form->createView(),
            'csrfToken' => $resetPasswordService->getForgotPageData()['csrfToken'],
        ]);
    }
}