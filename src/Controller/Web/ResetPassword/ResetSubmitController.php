<?php

namespace App\Controller\Web\ResetPassword;

use App\Form\ResetPasswordFormType;
use App\Service\ResetPasswordService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ResetSubmitController extends AbstractController
{
    #[Route('/reset-password/{token}', name: 'app_reset_password_submit', methods: ['POST'])]
    public function __invoke(
        string $token,
        Request $request,
        ResetPasswordService $resetPasswordService
    ): Response {
        $pageData = $resetPasswordService->getResetPageData($token);

        if (!$pageData['ok']) {
            $this->addFlash($pageData['flashType'], $pageData['message']);

            return $this->redirectToRoute($pageData['routeName'], $pageData['routeParams']);
        }

        $form = $this->createForm(ResetPasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $result = $resetPasswordService->resetPassword(
                $token,
                (string) $form->get('plainPassword')->getData(),
                $request->request->get('csrfToken')
            );

            $this->addFlash($result['flashType'], $result['message']);

            return $this->redirectToRoute($result['routeName'], $result['routeParams']);
        }

        return $this->render('security/reset_password.html.twig', [
            'form' => $form->createView(),
            'token' => $token,
            'csrfToken' => $pageData['csrfToken'],
        ]);
    }
}