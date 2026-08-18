<?php

namespace App\Controller\Web\Moderateur;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/moderateur/notify', name: 'moderateur_notify_')]
#[IsGranted(new Expression('is_granted("ROLE_MODERATEUR") or is_granted("ROLE_ADMIN") or is_granted("ROLE_SUPER_ADMIN")'))]
class NotifyController extends AbstractController
{
    /**
     * Décode de manière sûre le corps JSON d'une requête de notification.
     */
    private function jsonBody(Request $request): array
    {
        $data = json_decode((string)$request->getContent(), true);
        return is_array($data) ? $data : [];
    }

    /**
     * Envoie manuellement un e-mail demandant à l'utilisateur de compléter son profil.
     */
    #[Route('/{id}/email', name: 'email', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function email(int $id, Request $request, UserRepository $userRepo, MailerInterface $mailer): JsonResponse
    {
        $payload = $this->jsonBody($request);
        if (!$this->isCsrfTokenValid('notify_email_'.$id, (string)($payload['_token'] ?? ''))) {
            return $this->json(['ok' => false, 'message' => 'CSRF invalide.'], 403);
        }

        $u = $userRepo->find($id);
        if (!$u) throw new NotFoundHttpException('Utilisateur introuvable.');

        if (!$u->getEmail()) {
            return $this->json(['ok' => false, 'message' => 'Email manquant.'], 400);
        }

        $profileEditUrl = $request->getSchemeAndHttpHost() . $this->generateUrl('profile_edit');

        $email = (new TemplatedEmail())
            ->from('no-reply@maindoeuvrelocale.com')
            ->to($u->getEmail())
            ->subject('Complétez votre profil pour être visible et boosté')
            ->htmlTemplate('emails/complete_profile.html.twig')
            ->context([
                'user' => $u,
                'profileUrl' => $profileEditUrl,
                'missingParts' => ['photo ou logo', 'nom public', 'informations du profil'],
            ]);

        $mailer->send($email);

        return $this->json(['ok' => true]);
    }

    #[Route('/{id}/sms', name: 'sms', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function sms(int $id, Request $request, UserRepository $userRepo): JsonResponse
    {
        $payload = $this->jsonBody($request);
        if (!$this->isCsrfTokenValid('notify_sms_'.$id, (string)($payload['_token'] ?? ''))) {
            return $this->json(['ok' => false, 'message' => 'CSRF invalide.'], 403);
        }

        $u = $userRepo->find($id);
        if (!$u) throw new NotFoundHttpException('Utilisateur introuvable.');

        if (!$u->getPhone()) {
            return $this->json(['ok' => false, 'message' => 'Numéro manquant.'], 400);
        }

        // ✅ Ici tu branches ton fournisseur SMS (Twilio/Infobip/etc.)
        // Exemple : $smsService->send($u->getPhone(), $message);

        return $this->json(['ok' => true]);
    }

    #[Route('/{id}/whatsapp', name: 'whatsapp', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function whatsapp(int $id, Request $request, UserRepository $userRepo): JsonResponse
    {
        $payload = $this->jsonBody($request);
        if (!$this->isCsrfTokenValid('notify_whatsapp_'.$id, (string)($payload['_token'] ?? ''))) {
            return $this->json(['ok' => false, 'message' => 'CSRF invalide.'], 403);
        }

        $u = $userRepo->find($id);
        if (!$u) throw new NotFoundHttpException('Utilisateur introuvable.');

        $phone = (string)($u->getPhone() ?? '');
        $phone = str_replace([' ', '+'], '', $phone);

        // ✅ petit bonus Cameroun: si "6xxxxxxxx" => "2376xxxxxxxx"
        if (preg_match('/^(6|2)\d{8}$/', $phone)) {
            $phone = '237'.$phone;
        }

        if ($phone === '') {
            return $this->json(['ok' => false, 'message' => 'Numéro manquant.'], 400);
        }

        $msg = "Bonjour 👋. Pour que votre profil soit visible et boosté sur la plateforme, veuillez compléter votre profil (photo, nom, adresse…). Merci !";
        $url = "https://wa.me/".$phone."?text=".rawurlencode($msg);

        return $this->json(['ok' => true, 'url' => $url]);
    }
}
