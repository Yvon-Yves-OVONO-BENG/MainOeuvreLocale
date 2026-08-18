<?php

namespace App\Controller\Api\TalentPaymentDispute;

use App\Entity\User;
use App\Form\PaymentDisputeClientType;
use App\Service\TalentPaymentDisputeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/talent')]
final class CreateController extends AbstractController
{
    #[Route('/payment-disputes', name: 'api_talent_payment_dispute_create', methods: ['POST'])]
    public function __invoke(
        Request $request,
        TalentPaymentDisputeService $talentPaymentDisputeService
    ): JsonResponse {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json([
                'ok' => false,
                'message' => 'Accès refusé.',
            ], 403);
        }

        $paymentDispute = $talentPaymentDisputeService->createDisputeDraft($user);

        $form = $this->createForm(PaymentDisputeClientType::class, $paymentDispute, [
            'user' => $user,
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

        try {
            return $this->json(
                $talentPaymentDisputeService->submitNewDispute($paymentDispute, $user),
                201
            );
        } catch (ConflictHttpException $exception) {
            return $this->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 409);
        } catch (BadRequestHttpException $exception) {
            return $this->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 400);
        }
    }
}