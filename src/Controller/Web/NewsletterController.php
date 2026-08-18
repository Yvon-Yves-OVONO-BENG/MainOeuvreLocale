<?php

namespace App\Controller\Web;

use App\Entity\User;
use App\Repository\NewsletterSubscriberRepository;
use App\Repository\UserRepository;
use App\Service\Newsletter\NewsletterService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class NewsletterController extends AbstractController
{
    #[Route('/newsletter/abonnement', name: 'newsletter_subscribe', methods: ['POST'])]
    public function subscribe(
        Request $request,
        NewsletterService $newsletterService,
        UserRepository $userRepository,
    ): Response {
        $redirectUrl = $request->headers->get('referer') ?: $this->generateUrl('accueil');

        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('newsletter_subscribe', $token)) {
            $this->addFlash('toast_error', "La demande n'a pas été traitée. Veuillez réessayer.");
            return $this->redirect($redirectUrl);
        }

        $email = mb_strtolower(trim((string) $request->request->get('email', '')));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('toast_error', 'Veuillez saisir une adresse email valide.');
            return $this->redirect($redirectUrl);
        }

        $user = $userRepository->findOneByEmailAddress($email);
        $status = $newsletterService->subscribe(
            $email,
            $user instanceof User ? $user : null,
            'footer'
        );

        $message = match ($status) {
            'already_active' => 'Cette adresse reçoit déjà les alertes offres.',
            'reactivated' => 'Votre abonnement aux alertes offres a été réactivé.',
            'updated' => 'Votre abonnement aux alertes offres a été mis à jour.',
            default => 'Abonné avec succès.',
        };
        $this->addFlash('toast_success', $message);
        return $this->redirect($redirectUrl);
    }

    #[Route('/alertes-offres/desabonnement', name: 'offer_alert_unsubscribe_account', methods: ['POST'])]
    public function unsubscribeAccount(
        Request $request,
        NewsletterSubscriberRepository $newsletterSubscriberRepository,
        NewsletterService $newsletterService,
    ): Response {
        $redirectUrl = $request->headers->get('referer') ?: $this->generateUrl('accueil');
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Connectez-vous pour gérer cette alerte.');
        }

        if (!$this->isCsrfTokenValid('offer_alert_unsubscribe_account', (string) $request->request->get('_token', ''))) {
            $this->addFlash('toast_error', 'Jeton de sécurité invalide. Veuillez réessayer.');
            return $this->redirect($redirectUrl);
        }

        $subscriber = $newsletterSubscriberRepository->findOneByEmail($user->getEmail());
        if (!$subscriber) {
            $this->addFlash('toast_error', 'Aucune alerte active n’a été trouvée pour votre compte.');
            return $this->redirect($redirectUrl);
        }

        $newsletterService->unsubscribe($subscriber);
        $this->addFlash('toast_success', 'Vous êtes maintenant désabonné des alertes offres.');

        return $this->redirect($redirectUrl);
    }

    #[Route('/newsletter/desinscription/{token}', name: 'newsletter_unsubscribe', methods: ['GET'])]
    public function unsubscribePage(
        string $token,
        NewsletterSubscriberRepository $newsletterSubscriberRepository,
    ): Response {
        $subscriber = $newsletterSubscriberRepository->findOneByToken($token);

        if (!$subscriber) {
            throw $this->createNotFoundException('Lien de désinscription introuvable.');
        }

        return $this->render('emails/newsletter/unsubscribe.html.twig', [
            'subscriber' => $subscriber,
            'token' => $token,
            'done' => false,
        ]);
    }

    #[Route('/newsletter/desinscription/{token}/confirmer', name: 'newsletter_unsubscribe_confirm', methods: ['POST'])]
    public function confirmUnsubscribe(
        string $token,
        Request $request,
        NewsletterSubscriberRepository $newsletterSubscriberRepository,
        NewsletterService $newsletterService,
    ): Response {
        $subscriber = $newsletterSubscriberRepository->findOneByToken($token);

        if (!$subscriber) {
            throw $this->createNotFoundException('Lien de désinscription introuvable.');
        }

        $csrf = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('newsletter_unsubscribe_'.$subscriber->getId(), $csrf)) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $newsletterService->unsubscribeByToken($token);

        return $this->render('emails/newsletter/unsubscribe.html.twig', [
            'subscriber' => $subscriber,
            'token' => $token,
            'done' => true,
        ]);
    }
}
