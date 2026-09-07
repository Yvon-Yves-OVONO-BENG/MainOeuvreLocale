<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Service\IncidentRecorder;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Environment;

/**
 * Intercepte les exceptions avant la page d'exception Symfony, les journalise
 * dans la table incident puis affiche une page utilisateur sûre et responsive.
 */
final class UserFriendlyExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Environment $twig,
        private readonly LoggerInterface $logger,
        private readonly IncidentRecorder $incidentRecorder,
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Priorité volontairement haute : même en environnement dev, la page
        // rouge Symfony n'est plus exposée dans le navigateur.
        return [KernelEvents::EXCEPTION => ['onKernelException', 2048]];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest() || $event->hasResponse()) return;

        $request = $event->getRequest();
        $exception = $event->getThrowable();
        $status = $exception instanceof HttpExceptionInterface
            ? $exception->getStatusCode()
            : Response::HTTP_INTERNAL_SERVER_ERROR;

        if ($status < 400 || $status > 599) $status = Response::HTTP_INTERNAL_SERVER_ERROR;

        $tokenUser = $this->tokenStorage->getToken()?->getUser();
        $user = $tokenUser instanceof User ? $tokenUser : null;
        $incident = $this->incidentRecorder->record($exception, $request, $status, $user);
        $reference = $incident?->getReference() ?? $this->fallbackReference();
        $details = $this->friendlyDetails($status);

        $headers = $exception instanceof HttpExceptionInterface ? $exception->getHeaders() : [];
        $headers['X-MOL-Incident'] = $reference;
        $headers['Cache-Control'] = 'no-store, no-cache, must-revalidate, private';
        $headers['X-Robots-Tag'] = 'noindex, nofollow';

        $this->logger->log($status >= 500 ? 'error' : 'notice', 'Incident technique intercepté.', [
            'reference' => $reference,
            'status' => $status,
            'method' => $request->getMethod(),
            'path' => $request->getPathInfo(),
            'exception' => $exception,
        ]);

        if ($this->expectsJson($request)) {
            $event->setResponse(new JsonResponse([
                'ok' => false,
                'status' => $status,
                'message' => $details['message'],
                'reference' => $reference,
                'errorCode' => $reference,
            ], $status, $headers));
            return;
        }

        try {
            $html = $this->twig->render('error/friendly.html.twig', [
                'status' => $status,
                'title' => $details['title'],
                'message' => $details['message'],
                'reference' => $reference,
                'attemptedAt' => new \DateTimeImmutable(),
            ]);
            $event->setResponse(new Response($html, $status, $headers));
        } catch (\Throwable $renderingError) {
            $this->logger->critical('Impossible de rendre la page d’incident.', [
                'reference' => $reference,
                'exception' => $renderingError,
            ]);

            $safeReference = htmlspecialchars($reference, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $event->setResponse(new Response(
                '<!doctype html><html lang="fr"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
                . '<title>Incident technique</title><body style="margin:0;background:#071426;color:#f8fafc;font-family:Arial,sans-serif">'
                . '<main style="min-height:100vh;display:grid;place-items:center;padding:24px"><section style="max-width:620px;text-align:center;background:#0d1c31;border:1px solid #203553;padding:40px;border-radius:28px">'
                . '<h1>Un incident technique est survenu</h1><p>Nous n’avons pas pu terminer cette action. L’erreur a été enregistrée automatiquement.</p>'
                . '<p style="opacity:.75;font-size:13px">Référence : ' . $safeReference . '</p><p><a href="/" style="color:#93c5fd;font-weight:700">Retour à l’accueil</a></p></section></main></body></html>',
                $status,
                $headers
            ));
        }
    }

    /** @return array{title:string,message:string} */
    private function friendlyDetails(int $status): array
    {
        return match ($status) {
            400, 422 => ['title' => 'Certaines informations sont invalides', 'message' => 'Vérifiez les informations saisies puis réessayez.'],
            401 => ['title' => 'Connexion nécessaire', 'message' => 'Connectez-vous pour continuer cette action.'],
            403 => ['title' => 'Accès non autorisé', 'message' => 'Vous n’avez pas les autorisations nécessaires pour accéder à cette ressource.'],
            404 => ['title' => 'Page ou ressource introuvable', 'message' => 'La ressource demandée est introuvable. L’incident a été enregistré pour vérification.'],
            405 => ['title' => 'Action non disponible', 'message' => 'Cette action ne peut pas être effectuée de cette manière.'],
            413 => ['title' => 'Fichier trop volumineux', 'message' => 'Le fichier dépasse la taille autorisée. Choisissez un fichier plus léger.'],
            429 => ['title' => 'Trop de tentatives', 'message' => 'Veuillez patienter quelques instants avant de réessayer.'],
            502, 503, 504 => ['title' => 'Service temporairement indisponible', 'message' => 'Le service rencontre une indisponibilité passagère. L’incident a été enregistré automatiquement.'],
            default => ['title' => 'Un incident technique est survenu', 'message' => 'Nous n’avons pas pu terminer cette action. L’erreur a été enregistrée automatiquement pour l’administration.'],
        };
    }

    private function expectsJson(Request $request): bool
    {
        if ($request->isXmlHttpRequest() || str_starts_with($request->getPathInfo(), '/api/')) return true;
        $accept = strtolower((string) $request->headers->get('Accept', ''));
        return str_contains($accept, 'application/json') && !str_contains($accept, 'text/html');
    }

    private function fallbackReference(): string
    {
        try { $random = strtoupper(bin2hex(random_bytes(4))); }
        catch (\Throwable) { $random = strtoupper(substr(hash('sha256', uniqid('', true)), 0, 8)); }
        return sprintf('MOL-%s-%s', (new \DateTimeImmutable())->format('Ymd-His'), $random);
    }
}
