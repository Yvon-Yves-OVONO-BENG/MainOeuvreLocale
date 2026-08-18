<?php

namespace App\Controller\Web\Registration;

use App\Form\RegistrationFormType;
use App\Service\RegistrationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RegisterSubmitController extends AbstractController
{
    #[Route('/register', name: 'app_register_submit', methods: ['POST'])]
    public function __invoke(
        Request $request,
        RegistrationService $registrationService
    ): Response {
        $user = $registrationService->createRegistrationDraft();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $result = $registrationService->register(
                $user,
                (string) $form->get('plainPassword')->getData(),
                $request->request->get('csrfToken')
            );

            $this->addFlash($result['flashType'], $result['message']);

            return $this->redirectToRoute($result['routeName'], $result['routeParams']);
        }

        return $this->render('registration/registration.html.twig', [
            'registrationForm' => $form->createView(),
            'csrfToken' => $registrationService->getRegisterPageData()['csrfToken'],
        ]);
    }
}