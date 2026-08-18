<?php

namespace App\Controller\Web\Registration;

use App\Form\RegistrationFormType;
use App\Repository\UserRepository;
use App\Service\RegistrationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RegisterController extends AbstractController
{
    #[Route('/register', name: 'app_register', methods: ['GET'])]
    public function __invoke(RegistrationService $registrationService): Response
    {
        $user = $registrationService->createRegistrationDraft();
        $form = $this->createForm(RegistrationFormType::class, $user);

        return $this->render('registration/registration.html.twig', [
            'registrationForm' => $form->createView(),
            'csrfToken' => $registrationService->getRegisterPageData()['csrfToken'],
        ]);
    }

    #[Route('/register/check-email', name: 'app_register_check_email', methods: ['GET'])]
    public function checkEmail(Request $request, UserRepository $userRepository): JsonResponse
    {
        $email = strtolower(trim((string) $request->query->get('email', '')));

        if ($email === '') {
            return new JsonResponse([
                'valid' => false,
                'exists' => false,
                'count' => 0,
                'message' => 'Email vide',
            ]);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse([
                'valid' => false,
                'exists' => false,
                'count' => 0,
                'message' => 'Email non valide',
            ]);
        }

        $exists = $userRepository->findOneByEmailAddress($email) !== null;

        return new JsonResponse([
            'valid' => true,
            'exists' => $exists,
            'count' => $exists ? 1 : 0,
            'message' => $exists ? 'Email déjà utilisé' : 'Email disponible',
        ]);
    }
}