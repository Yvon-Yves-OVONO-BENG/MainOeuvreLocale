<?php

namespace App\Controller\Api\Registration;

use App\Form\RegistrationFormType;
use App\Service\RegistrationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class RegisterSubmitController extends AbstractController
{
    #[Route('/api/register', name: 'api_register_submit', methods: ['POST'])]
    public function __invoke(
        Request $request,
        RegistrationService $registrationService
    ): JsonResponse {
        $user = $registrationService->createRegistrationDraft();

        $form = $this->createForm(RegistrationFormType::class, $user, [
            'csrf_protection' => false,
        ]);

        $payload = json_decode($request->getContent(), true);
        $payload = is_array($payload) ? $payload : $request->request->all();

        $form->submit($payload);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $errors = [];

            foreach ($form->getErrors(true) as $error) {
                if ($error instanceof FormError) {
                    $errors[] = $error->getMessage();
                }
            }

            return $this->json([
                'ok' => false,
                'message' => 'Le formulaire est invalide.',
                'errors' => $errors,
            ], 400);
        }

        $result = $registrationService->register(
            $user,
            (string) $form->get('plainPassword')->getData(),
            $payload['csrfToken'] ?? null
        );

        return $this->json([
            'ok' => $result['ok'],
            'message' => $result['message'],
            'redirect' => [
                'route' => $result['routeName'],
                'params' => $result['routeParams'],
            ],
            'email' => $result['ok'] ? $result['user']->getEmail() : null,
        ], $result['ok'] ? 201 : 400);
    }
}