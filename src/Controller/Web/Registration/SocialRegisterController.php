<?php

namespace App\Controller\Web\Registration;

use App\Entity\User;
use App\Security\UserAuthenticator;
use App\Service\AccountActivationService;
use App\Service\SocialRegistrationService;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Client\OAuth2Client;
use KnpU\OAuth2ClientBundle\Client\OAuth2ClientInterface;
use KnpU\OAuth2ClientBundle\Client\Provider\FacebookClient;
use KnpU\OAuth2ClientBundle\Client\Provider\GoogleClient;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;


final class SocialRegisterController extends AbstractController
{
    #[Route('/connect/google', name: 'connect_google_start', methods: ['GET'])]
    public function connectGoogle(ClientRegistry $clientRegistry): RedirectResponse
    {
        /** @var GoogleClient $client */
        $client = $clientRegistry->getClient('google');

        return $client->redirect([
            'openid',
            'email',
            'profile',
        ], [
            'prompt' => 'select_account',
        ]);
    }

    #[Route('/connect/google/check', name: 'connect_google_check', methods: ['GET'])]
    public function connectGoogleCheck(
        ClientRegistry $clientRegistry,
        SocialRegistrationService $socialRegistrationService,
        AccountActivationService $accountActivationService,
        Security $security,
        LoggerInterface $logger,
    ): Response {
        try {
            /** @var OAuth2ClientInterface $client */
            $client = $clientRegistry->getClient('google');

            $googleUser = $client->fetchUser();
            $data = $googleUser->toArray();

            $user = $socialRegistrationService->findOrCreateUser(
                provider: 'google',
                providerId: (string) $googleUser->getId(),
                email: $data['email'] ?? null,
                name: $data['name'] ?? null,
                avatarUrl: $data['picture'] ?? null,
            );

            return $this->finalizeSocialConnection($user, $accountActivationService, $security);
        } catch (Throwable $e) {
            $logger->error('Erreur pendant la connexion Google', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $this->addFlash('danger', 'Connexion Google impossible. Veuillez réessayer.');

            return $this->redirectToRoute('accueil');
        }
    }

    #[Route('/connect/facebook', name: 'connect_facebook_start', methods: ['GET'])]
    public function connectFacebook(ClientRegistry $clientRegistry): RedirectResponse
    {
        /** @var FacebookClient $client */
        $client = $clientRegistry->getClient('facebook');

        return $client->redirect([
            'email',
            'public_profile',
        ]);
    }

    #[Route('/connect/facebook/check', name: 'connect_facebook_check', methods: ['GET'])]
    public function connectFacebookCheck(
        ClientRegistry $clientRegistry,
        SocialRegistrationService $socialRegistrationService,
        AccountActivationService $accountActivationService,
        Security $security,
        LoggerInterface $logger,
    ): Response {
        try {
            /** @var OAuth2ClientInterface $client */
            $client = $clientRegistry->getClient('facebook');

            $facebookUser = $client->fetchUser();
            $data = $facebookUser->toArray();

            $user = $socialRegistrationService->findOrCreateUser(
                provider: 'facebook',
                providerId: (string) $facebookUser->getId(),
                email: $data['email'] ?? null,
                name: $data['name'] ?? null,
                avatarUrl: $this->extractFacebookAvatar($data),
            );

            return $this->finalizeSocialConnection($user, $accountActivationService, $security);
        } catch (Throwable $e) {
            $logger->error('Erreur pendant la connexion Facebook', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $this->addFlash('danger', 'Connexion Facebook impossible. Veuillez réessayer.');

            return $this->redirectToRoute('app_login');
        }
    }


    #[Route('/connect/apple', name: 'connect_apple_start', methods: ['GET'])]
    public function connectApple(ClientRegistry $clientRegistry): RedirectResponse
    {
        /** @var OAuth2Client $client */
        $client = $clientRegistry->getClient('apple');

        return $client->redirect([
            'email',
            'name',
        ]);
    }

    #[Route('/connect/apple/check', name: 'connect_apple_check', methods: ['GET', 'POST'])]
    public function connectAppleCheck(
        Request $request,
        ClientRegistry $clientRegistry,
        SocialRegistrationService $socialRegistrationService,
        AccountActivationService $accountActivationService,
        Security $security,
        LoggerInterface $logger,
    ): Response {
        try {
            /** @var OAuth2ClientInterface $client */
            $client = $clientRegistry->getClient('apple');

            $appleUser = $client->fetchUser();
            $data = $appleUser->toArray();

            /*
             * Apple transmet le bloc JSON "user" uniquement lors de la
             * première autorisation et utilise response_mode=form_post.
             */
            $postedUser = json_decode(
                (string) $request->request->get('user', ''),
                true
            );
            $postedUser = is_array($postedUser) ? $postedUser : [];

            $firstName = $data['firstName']
                ?? $data['first_name']
                ?? $postedUser['name']['firstName']
                ?? '';
            $lastName = $data['lastName']
                ?? $data['last_name']
                ?? $postedUser['name']['lastName']
                ?? '';
            $name = trim($firstName . ' ' . $lastName);

            if ($name === '') {
                $name = $data['name'] ?? null;
            }

            $user = $socialRegistrationService->findOrCreateUser(
                provider: 'apple',
                providerId: (string) $appleUser->getId(),
                email: $data['email'] ?? $postedUser['email'] ?? null,
                name: $name,
                avatarUrl: null,
            );

            return $this->finalizeSocialConnection(
                $user,
                $accountActivationService,
                $security
            );
        } catch (Throwable $e) {
            $logger->error('Erreur pendant la connexion Apple', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $this->addFlash('danger', 'Connexion Apple impossible. Veuillez réessayer.');

            return $this->redirectToRoute('app_login');
        }
    }


    #[Route('/connect/microsoft', name: 'connect_microsoft_start', methods: ['GET'])]
    public function connectMicrosoft(ClientRegistry $clientRegistry): RedirectResponse
    {
        /** @var OAuth2Client $client */
        $client = $clientRegistry->getClient('microsoft');

        return $client->redirect([
            'openid',
            'email',
            'profile',
            'https://graph.microsoft.com/User.Read',
        ]);
    }

    #[Route('/connect/microsoft/check', name: 'connect_microsoft_check', methods: ['GET'])]
    public function connectMicrosoftCheck(
        ClientRegistry $clientRegistry,
        SocialRegistrationService $socialRegistrationService,
        AccountActivationService $accountActivationService,
        Security $security,
        LoggerInterface $logger,
    ): Response {
        try {
            /** @var OAuth2ClientInterface $client */
            $client = $clientRegistry->getClient('microsoft');

            $microsoftUser = $client->fetchUser();
            $data = $microsoftUser->toArray();

            $email = $data['mail']
                ?? $data['userPrincipalName']
                ?? $data['email']
                ?? $data['preferred_username']
                ?? $data['upn']
                ?? $data['unique_name']
                ?? null;

            $name = $data['displayName']
                ?? $data['name']
                ?? null;

            if (!$email) {
                throw new \RuntimeException('Microsoft n’a pas retourné d’adresse email.');
            }

            $user = $socialRegistrationService->findOrCreateUser(
                provider: 'microsoft',
                providerId: (string) $microsoftUser->getId(),
                email: $email,
                name: $name,
                avatarUrl: null,
            );

            return $this->finalizeSocialConnection(
                $user,
                $accountActivationService,
                $security
            );
        } catch (Throwable $e) {
            $logger->error('Erreur pendant la connexion Microsoft', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $this->addFlash('danger', 'Connexion Microsoft impossible. Veuillez réessayer.');

            return $this->redirectToRoute('app_login');
        }
    }

    private function finalizeSocialConnection(
        User $user,
        AccountActivationService $accountActivationService,
        Security $security
    ): Response {
        if (!$user->IsActive() || !$user->IsEmailVerified()) {
            $accountActivationService->sendActivationEmail($user);

            $this->addFlash(
                'success',
                'Votre compte existe mais n’est pas encore activé. Un nouveau mail d’activation vous a été envoyé.'
            );

            return $this->redirectToRoute('accueil');
        }

        $security->login($user, UserAuthenticator::class, 'main');

        if ($user->getProfileType() === null || $user->getProfileType() === '') {
            return $this->redirectToRoute('profile_type_choice');
        }

        return $this->redirectToRoute('accueil');
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractFacebookAvatar(array $data): ?string
    {
        $picture = $data['picture'] ?? null;

        if (is_array($picture)) {
            $pictureData = $picture['data'] ?? $picture;

            if (is_array($pictureData) && is_string($pictureData['url'] ?? null)) {
                return $pictureData['url'];
            }
        }

        return is_string($data['picture_url'] ?? null)
            ? $data['picture_url']
            : null;
    }
}
