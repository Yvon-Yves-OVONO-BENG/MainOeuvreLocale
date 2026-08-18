<?php

namespace App\Controller\Web;

use App\Entity\ChatSetting;
use App\Entity\ChatTestMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/chat')]
class ChatSettingsController extends AbstractController
{
    #[Route('/parametres', name: 'app_chat_settings', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        $settings = $this->getOrCreateSettings($em);

        $lastTests = $em->getRepository(ChatTestMessage::class)->findBy(
            [],
            ['createdAt' => 'DESC'],
            8
        );

        return $this->render('chat1/settings.html.twig', [
            'chatSettings' => $settings,
            'lastTests' => $lastTests,
        ]);
    }

    #[Route('/parametres/enregistrer', name: 'app_chat_settings_save', methods: ['POST'])]
    public function save(Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('chat_settings', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $settings = $this->getOrCreateSettings($em);

        $settings->chatEnabled = $request->request->has('chat_enabled');
        $settings->chatName = $this->text($request, 'chat_name', 'Assistance Main d’œuvre locale');
        $settings->welcomeMessage = $this->text($request, 'welcome_message', 'Bonjour 👋 Comment pouvons-nous vous aider aujourd’hui ?');
        $settings->defaultDepartment = $this->choice($request, 'default_department', ['support', 'recrutement', 'commercial', 'technique'], 'support');

        $settings->primaryColor = $this->text($request, 'primary_color', '#2563eb');
        $settings->chatPosition = $this->choice($request, 'chat_position', ['bottom_right', 'bottom_left', 'inline'], 'bottom_right');
        $settings->chatTheme = $this->choice($request, 'chat_theme', ['light', 'dark', 'system'], 'light');
        $settings->showAvatar = $request->request->has('show_avatar');

        $settings->openTime = $this->time($request, 'open_time', '08:00');
        $settings->closeTime = $this->time($request, 'close_time', '18:00');
        $settings->timezone = $this->text($request, 'timezone', 'Africa/Douala');
        $settings->offlineMessage = $this->text($request, 'offline_message', 'Nous sommes actuellement indisponibles.');

        $settings->autoOpen = $request->request->has('auto_open');
        $settings->autoOpenDelay = max(0, (int) $request->request->get('auto_open_delay', 8));
        $settings->requireContact = $request->request->has('require_contact');
        $settings->humanTransfer = $request->request->has('human_transfer');

        $settings->notificationEmail = trim((string) $request->request->get('notification_email')) ?: null;
        $settings->notifyNewMessage = $request->request->has('notify_new_message');
        $settings->dailySummary = $request->request->has('daily_summary');

        $settings->requireConsent = $request->request->has('require_consent');
        $settings->retentionDays = max(1, (int) $request->request->get('retention_days', 90));
        $settings->consentText = $this->text($request, 'consent_text', 'En utilisant ce chat, vous acceptez que vos messages soient traités.');

        $settings->updatedAt = new \DateTimeImmutable();

        $em->flush();

        $this->addFlash('success', 'Les paramètres du chat ont été enregistrés.');

        return $this->redirectToRoute('app_chat_settings');
    }

    #[Route('/tester', name: 'app_chat_test', methods: ['POST'])]
    public function test(Request $request, EntityManagerInterface $em): JsonResponse
    {
        if (!$this->isCsrfTokenValid('chat_settings', (string) $request->request->get('_token'))) {
            return $this->json(['success' => false, 'error' => 'Token CSRF invalide.'], 403);
        }

        $message = trim((string) $request->request->get('message'));

        if ($message === '') {
            return $this->json(['success' => false, 'error' => 'Le message est vide.'], 422);
        }

        $settings = $this->getOrCreateSettings($em);
        $reply = $this->buildLocalReply($message, $settings);

        $testMessage = new ChatTestMessage($message, $reply, [
            'chatName' => $settings->chatName,
            'chatEnabled' => $settings->chatEnabled,
            'defaultDepartment' => $settings->defaultDepartment,
            'primaryColor' => $settings->primaryColor,
            'openTime' => $settings->openTime,
            'closeTime' => $settings->closeTime,
            'timezone' => $settings->timezone,
        ]);

        $em->persist($testMessage);
        $em->flush();

        return $this->json([
            'success' => true,
            'reply' => $reply,
        ]);
    }

    private function getOrCreateSettings(EntityManagerInterface $em): ChatSetting
    {
        $settings = $em->getRepository(ChatSetting::class)->findOneBy([
            'keyName' => 'default',
        ]);

        if (!$settings) {
            $settings = new ChatSetting();
            $em->persist($settings);
            $em->flush();
        }

        return $settings;
    }

    private function buildLocalReply(string $message, ChatSetting $settings): string
    {
        if (!$settings->chatEnabled) {
            return 'Le chat est désactivé. Message configuré : ' . $settings->offlineMessage;
        }

        if (!$this->isWithinOpeningHours($settings)) {
            return $settings->offlineMessage;
        }

        if ($settings->requireContact && !$this->containsContact($message)) {
            return 'Merci. Avant de continuer, pouvez-vous laisser votre nom avec un téléphone ou une adresse email ?';
        }

        return sprintf(
            'Message reçu par %s. Votre demande est orientée vers le service "%s". Un agent vous répondra rapidement.',
            $settings->chatName,
            $settings->defaultDepartment
        );
    }

    private function isWithinOpeningHours(ChatSetting $settings): bool
    {
        try {
            $timezone = new \DateTimeZone($settings->timezone);
        } catch (\Throwable) {
            $timezone = new \DateTimeZone('Africa/Douala');
        }

        $now = new \DateTimeImmutable('now', $timezone);

        [$openHour, $openMinute] = array_map('intval', explode(':', $settings->openTime));
        [$closeHour, $closeMinute] = array_map('intval', explode(':', $settings->closeTime));

        $current = ((int) $now->format('H')) * 60 + (int) $now->format('i');
        $open = $openHour * 60 + $openMinute;
        $close = $closeHour * 60 + $closeMinute;

        if ($open <= $close) {
            return $current >= $open && $current <= $close;
        }

        return $current >= $open || $current <= $close;
    }

    private function containsContact(string $message): bool
    {
        $hasEmail = preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $message);
        $hasPhone = preg_match('/(\+?\d[\d\s().-]{7,}\d)/', $message);

        return (bool) ($hasEmail || $hasPhone);
    }

    private function text(Request $request, string $key, string $default): string
    {
        $value = trim((string) $request->request->get($key, ''));

        return $value !== '' ? $value : $default;
    }

    private function choice(Request $request, string $key, array $allowed, string $default): string
    {
        $value = (string) $request->request->get($key, $default);

        return in_array($value, $allowed, true) ? $value : $default;
    }

    private function time(Request $request, string $key, string $default): string
    {
        $value = (string) $request->request->get($key, $default);

        return preg_match('/^\d{2}:\d{2}$/', $value) ? $value : $default;
    }
}