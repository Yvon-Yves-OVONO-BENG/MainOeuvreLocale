<?php

namespace App\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\KernelInterface;
use Twig\Environment;

/**
 * Transforme les exceptions de production en réponses compréhensibles pour
 * l'utilisateur, sans exposer stack trace, chemins serveurs ou paramètres.
 *
 * En dev/test, Symfony conserve volontairement sa page détaillée afin de ne
 * pas gêner le diagnostic des développeurs.
 */
final class UserFriendlyExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly Environment $twig,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', -64],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest() || \in_array($this->kernel->getEnvironment(), ['dev', 'test'], true)) {
            return;
        }

        $request = $event->getRequest();
        $exception = $event->getThrowable();
        $status = $exception instanceof HttpExceptionInterface
            ? $exception->getStatusCode()
            : Response::HTTP_INTERNAL_SERVER_ERROR;

        // Un code de statut HTTP hors plage ne doit jamais produire une autre exception.
        if ($status < 400 || $status > 599) {
            $status = Response::HTTP_INTERNAL_SERVER_ERROR;
        }

        $reference = $this->createReference();
        $details = $this->friendlyDetails($status);
        $httpHeaders = $exception instanceof HttpExceptionInterface ? $exception->getHeaders() : [];
        $httpHeaders['X-MOL-Incident'] = $reference;

        $context = [
            'reference' => $reference,
            'status' => $status,
            'method' => $request->getMethod(),
            'path' => $request->getPathInfo(),
            'exception' => $exception,
        ];

        if ($status >= 500) {
            $this->logger->error('Erreur applicative masquée par la page utilisateur.', $context);
        } else {
            $this->logger->notice('Erreur HTTP présentée avec une page utilisateur.', $context);
        }

        if ($this->expectsJson($request)) {
            $event->setResponse(new JsonResponse([
                'ok' => false,
                'status' => $status,
                'message' => $details['message'],
                'errorCode' => $reference,
            ], $status, $httpHeaders));

            return;
        }

        $canRetry = \in_array($request->getMethod(), ['GET', 'HEAD'], true)
            && \in_array($status, [500, 502, 503, 504], true);

        try {
            $html = $this->twig->render('error/friendly.html.twig', [
                'status' => $status,
                'title' => $details['title'],
                'message' => $details['message'],
                'reference' => $reference,
                'canRetry' => $canRetry,
                'attemptedAt' => new \DateTimeImmutable(),
            ]);

            $event->setResponse(new Response($html, $status, array_merge($httpHeaders, [
                'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
                'X-Robots-Tag' => 'noindex, nofollow',
            ])));
        } catch (\Throwable $renderingError) {
            // Ultime filet de sécurité : même une erreur Twig ne doit pas exposer la page rouge.
            $this->logger->critical('Impossible de rendre la page d’erreur utilisateur.', [
                'reference' => $reference,
                'exception' => $renderingError,
            ]);

            $safeMessage = htmlspecialchars($details['message'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $safeReference = htmlspecialchars($reference, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $event->setResponse(new Response(
                '<!doctype html><html lang="fr"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
                . '<title>Service indisponible</title><body style="font-family:system-ui;margin:0;background:#f5f8fc;color:#102a43">'
                . '<main style="min-height:100vh;display:grid;place-items:center;padding:24px"><section style="max-width:560px;background:#fff;padding:32px;border-radius:24px;box-shadow:0 20px 50px rgba(16,42,67,.12)">'
                . '<h1 style="margin-top:0">Un imprévu est survenu</h1><p>' . $safeMessage . '</p>'
                . '<p style="font-size:12px;color:#73869d">Référence : ' . $safeReference . '</p>'
                . '<p><a href="/" style="color:#0d6efd;font-weight:700">Retour à l’accueil</a></p></section></main></body></html>',
                $status,
                array_merge($httpHeaders, ['Cache-Control' => 'no-store'])
            ));
        }
    }

    /** @return array{title:string,message:string} */
    private function friendlyDetails(int $status): array
    {
        return match ($status) {
            400, 422 => [
                'title' => 'Certaines informations sont invalides',
                'message' => 'Vérifiez les informations saisies puis réessayez.',
            ],
            401 => [
                'title' => 'Connexion nécessaire',
                'message' => 'Connectez-vous pour continuer cette action.',
            ],
            403 => [
                'title' => 'Accès non autorisé',
                'message' => 'Vous n’avez pas les autorisations nécessaires pour accéder à cette page.',
            ],
            404 => [
                'title' => 'Page introuvable',
                'message' => 'Cette page n’existe plus, a été déplacée ou le lien utilisé est incorrect.',
            ],
            405 => [
                'title' => 'Action non disponible',
                'message' => 'Cette action ne peut pas être effectuée de cette manière.',
            ],
            409 => [
                'title' => 'Action déjà traitée',
                'message' => 'La ressource a changé entre-temps. Actualisez la page puis réessayez.',
            ],
            413 => [
                'title' => 'Fichier trop volumineux',
                'message' => 'Le fichier envoyé dépasse la taille autorisée. Choisissez un fichier plus léger.',
            ],
            429 => [
                'title' => 'Trop de tentatives',
                'message' => 'Veuillez patienter quelques instants avant de réessayer.',
            ],
            502, 503, 504 => [
                'title' => 'Service temporairement indisponible',
                'message' => 'Le service rencontre une indisponibilité passagère. Une nouvelle tentative peut résoudre le problème.',
            ],
            default => [
                'title' => 'Un imprévu est survenu',
                'message' => 'Nous n’avons pas pu terminer cette action. Vos données techniques restent protégées et l’incident peut être identifié grâce à la référence ci-dessous.',
            ],
        };
    }

    private function expectsJson(Request $request): bool
    {
        if ($request->isXmlHttpRequest() || str_starts_with($request->getPathInfo(), '/api/')) {
            return true;
        }

        $accept = strtolower((string) $request->headers->get('Accept', ''));

        return str_contains($accept, 'application/json') && !str_contains($accept, 'text/html');
    }

    private function createReference(): string
    {
        try {
            $random = bin2hex(random_bytes(4));
        } catch (\Throwable) {
            $random = substr(hash('sha256', uniqid('', true)), 0, 8);
        }

        return sprintf('MOL-%s-%s', (new \DateTimeImmutable())->format('YmdHis'), strtoupper($random));
    }
}
