<?php

namespace App\Controller\Web\Settings;

use App\Entity\AccountDeletionRequest;
use App\Entity\User;
use App\Form\AccountSettingsType;
use App\Form\PasswordChangeType;
use App\Form\PrivacySettingsType;
use App\Form\NotificationSettingsType;
use App\Repository\AccountDeletionRequestRepository;
use App\Repository\UserActivityLogRepository;
use App\Repository\UserSettingRepository;
use App\Service\UserActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class SettingsController extends AbstractController
{
    #[Route('/settings', name: 'settings_index', methods: ['GET'])]
    public function index(
        Request $request,
        UserSettingRepository $settingRepo,
        UserActivityLogRepository $logRepo,
        AccountDeletionRequestRepository $delRepo,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $tab = (string) $request->query->get('tab', 'account');

        // forms “classiques”
        $accountForm = $this->createForm(AccountSettingsType::class, $user, [
            'action' => $this->generateUrl('settings_account_update'),
            'method' => 'POST',
        ]);
        $privacyForm = $this->createForm(PrivacySettingsType::class, $user, [
            'action' => $this->generateUrl('settings_privacy_update'),
            'method' => 'POST',
        ]);
        $passwordForm = $this->createForm(PasswordChangeType::class, null, [
            'action' => $this->generateUrl('settings_password_update'),
            'method' => 'POST',
        ]);

        // notifications JSON (table dédiée)
        $setting = $settingRepo->getOrCreate($user);
        $n = $setting->getNotifications();

        $notifDefaults = [
            'emailEnabled' => (bool)($n['channels']['email']['enabled'] ?? true),
            'smsEnabled' => (bool)($n['channels']['sms']['enabled'] ?? false),
            'whatsappEnabled' => (bool)($n['channels']['whatsapp']['enabled'] ?? false),

            'evNewApplication' => (bool)($n['events']['new_application'] ?? true),
            'evNewMessage' => (bool)($n['events']['new_message'] ?? true),
            'evJobExpiring' => (bool)($n['events']['job_expiring'] ?? true),

            'digestEnabled' => (bool)($n['digest']['enabled'] ?? false),
            'digestFrequency' => (string)($n['digest']['frequency'] ?? 'weekly'),
        ];

        $notifForm = $this->createForm(NotificationSettingsType::class, $notifDefaults, [
            'action' => $this->generateUrl('settings_notifications_update'),
            'method' => 'POST',
        ]);

        // activité
        $logs = $logRepo->findRecentByUser($user, 25);

        return $this->render('settings/settings.html.twig', [
            'tab' => $tab,
            'accountForm' => $accountForm->createView(),
            'privacyForm' => $privacyForm->createView(),
            'passwordForm' => $passwordForm->createView(),
            'notifForm' => $notifForm->createView(),
            'logs' => $logs,
            'hasDeletionRequest' => $delRepo->existsFor($user),
        ]);
    }

    #[Route('/settings/account', name: 'settings_account_update', methods: ['POST'])]
    public function updateAccount(Request $request, EntityManagerInterface $em, UserActivityLogger $logger): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(AccountSettingsType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setUpdatedAt(new \DateTime());
            $em->flush();
            $logger->log($user, 'settings.account.updated', $request);
            $this->addFlash('success', 'Compte mis à jour ✅');
        } else {
            $this->addFlash('error', 'Vérifie les champs du compte.');
        }

        return $this->redirectToRoute('settings_index', ['tab' => 'account']);
    }

    #[Route('/settings/privacy', name: 'settings_privacy_update', methods: ['POST'])]
    public function updatePrivacy(Request $request, EntityManagerInterface $em, UserActivityLogger $logger): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(PrivacySettingsType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setUpdatedAt(new \DateTime());
            $em->flush();
            $logger->log($user, 'settings.privacy.updated', $request);
            $this->addFlash('success', 'Confidentialité mise à jour ✅');
        } else {
            $this->addFlash('error', 'Vérifie la confidentialité.');
        }

        return $this->redirectToRoute('settings_index', ['tab' => 'privacy']);
    }

    #[Route('/settings/password', name: 'settings_password_update', methods: ['POST'])]
    public function updatePassword(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        UserActivityLogger $logger
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(PasswordChangeType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted()) {
            $this->addFlash('error', 'La demande de changement de mot de passe est incomplète.');
            return $this->redirectToRoute('settings_index', ['tab' => 'security']);
        }

        if (!$form->isValid()) {
            $messages = [];
            foreach ($form->getErrors(true) as $error) {
                $messages[] = $error->getMessage();
            }
            $this->addFlash('error', $messages !== [] ? implode(' ', array_unique($messages)) : 'Vérifiez les champs du mot de passe.');
            return $this->redirectToRoute('settings_index', ['tab' => 'security']);
        }

        // Les deux champs sont mapped=false : getData() sur le formulaire racine
        // ne contient pas leurs valeurs. Il faut lire chaque champ explicitement.
        $current = (string) $form->get('currentPassword')->getData();
        $new = (string) $form->get('newPassword')->getData();

        if (!$hasher->isPasswordValid($user, $current)) {
            $logger->log($user, 'settings.password.rejected', $request, [
                'reason' => 'invalid_current_password',
            ]);
            $this->addFlash('error', 'Le mot de passe actuel est incorrect. Aucun changement n’a été effectué.');
            return $this->redirectToRoute('settings_index', ['tab' => 'security']);
        }

        $user->setPassword($hasher->hashPassword($user, $new));
        $user->setUpdatedAt(new \DateTime());
        $em->flush();
        $logger->log($user, 'settings.password.updated', $request);
        $this->addFlash('success', 'Mot de passe mis à jour ✅');

        return $this->redirectToRoute('settings_index', ['tab' => 'security']);
    }

    #[Route('/settings/notifications', name: 'settings_notifications_update', methods: ['POST'])]
    public function updateNotifications(
        Request $request,
        UserSettingRepository $settingRepo,
        EntityManagerInterface $em,
        UserActivityLogger $logger
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $setting = $settingRepo->getOrCreate($user);

        $form = $this->createForm(NotificationSettingsType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $d = $form->getData() ?: [];

            $prefs = [
                'channels' => [
                    'email' => ['enabled' => !empty($d['emailEnabled'])],
                    'sms' => ['enabled' => !empty($d['smsEnabled'])],
                    'whatsapp' => ['enabled' => !empty($d['whatsappEnabled'])],
                ],
                'events' => [
                    'new_application' => !empty($d['evNewApplication']),
                    'new_message' => !empty($d['evNewMessage']),
                    'job_expiring' => !empty($d['evJobExpiring']),
                ],
                'digest' => [
                    'enabled' => !empty($d['digestEnabled']),
                    'frequency' => in_array(($d['digestFrequency'] ?? 'weekly'), ['daily','weekly'], true)
                        ? $d['digestFrequency']
                        : 'weekly',
                ],
            ];

            $setting->setNotifications($prefs)->touch();
            $em->flush();

            $logger->log($user, 'settings.notifications.updated', $request, [
                'channels' => $prefs['channels'],
                'digest' => $prefs['digest'],
            ]);

            $this->addFlash('success', 'Notifications mises à jour ✅');
        } else {
            $this->addFlash('error', 'Vérifie tes choix de notifications.');
        }

        return $this->redirectToRoute('settings_index', ['tab' => 'notifications']);
    }

    #[Route('/settings/danger/deactivate', name: 'settings_deactivate', methods: ['POST'])]
    public function deactivate(
        Request $request,
        EntityManagerInterface $em,
        CsrfTokenManagerInterface $csrf,
        UserActivityLogger $logger
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $token = (string) $request->request->get('_token', '');
        if (!$csrf->isTokenValid(new CsrfToken('settings_deactivate', $token))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('settings_index', ['tab' => 'danger']);
        }

        $user->setIsActive(false);
        $user->setUpdatedAt(new \DateTime());
        $em->flush();

        $logger->log($user, 'account.deactivated', $request);

        $this->addFlash('success', 'Compte désactivé. À bientôt 👋');

        // ⚠️ adapte le nom de ta route logout si différent
        return $this->redirectToRoute('app_logout');
    }

    #[Route('/settings/danger/delete-request', name: 'settings_delete_request', methods: ['POST'])]
    public function deleteRequest(
        Request $request,
        EntityManagerInterface $em,
        CsrfTokenManagerInterface $csrf,
        UserPasswordHasherInterface $hasher,
        AccountDeletionRequestRepository $delRepo,
        UserActivityLogger $logger
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $token = (string) $request->request->get('_token', '');
        if (!$csrf->isTokenValid(new CsrfToken('settings_delete_request', $token))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('settings_index', ['tab' => 'danger']);
        }

        $pwd = (string) $request->request->get('password', '');
        if (!$hasher->isPasswordValid($user, $pwd)) {
            $this->addFlash('error', 'Mot de passe incorrect.');
            return $this->redirectToRoute('settings_index', ['tab' => 'danger']);
        }

        if (!$delRepo->existsFor($user)) {
            $req = new AccountDeletionRequest();
            $req->setUser($user);
            $req->setReason(trim((string)$request->request->get('reason', '')) ?: null);
            $em->persist($req);
        }

        $user->setIsActive(false);
        $user->setUpdatedAt(new \DateTime());
        $em->flush();

        $logger->log($user, 'account.deletion.requested', $request);

        $this->addFlash('success', 'Demande de suppression enregistrée ✅');
        return $this->redirectToRoute('app_logout');
    }
}