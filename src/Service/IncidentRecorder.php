<?php

namespace App\Service;

use App\Entity\Incident;
use App\Entity\User;
use App\Repository\IncidentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

/** Enregistre les erreurs techniques sans jamais casser la réponse d'erreur. */
final class IncidentRecorder
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly IncidentRepository $repository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function record(\Throwable $exception, Request $request, int $status, ?User $user = null): ?Incident
    {
        try {
            $fingerprint = $this->fingerprint($exception, $request, $status);
            $now = new \DateTimeImmutable();
            $incident = $this->repository->findRecentOpenByFingerprint($fingerprint, $now->modify('-30 minutes'));

            if ($incident instanceof Incident) {
                $incident
                    ->incrementOccurrenceCount()
                    ->setLastOccurredAt($now)
                    ->setIp($request->getClientIp())
                    ->setUserAgent($this->cut($request->headers->get('User-Agent'), 4000))
                    ->setReferer($this->cut($request->headers->get('Referer'), 4000));
                if ($user) $incident->setUser($user);
                $this->em->flush();
                return $incident;
            }

            $incident = (new Incident())
                ->setReference($this->createReference())
                ->setFingerprint($fingerprint)
                ->setStatus(Incident::STATUS_OPEN)
                ->setSeverity($this->severityForStatus($status))
                ->setHttpStatus($status)
                ->setExceptionClass($exception::class)
                ->setMessage($this->cut($exception->getMessage(), 12000))
                ->setRoute($this->cut((string) $request->attributes->get('_route', ''), 255))
                ->setRequestUri($this->cut($request->getRequestUri(), 8000))
                ->setMethod($request->getMethod())
                ->setSourceFile($this->cut($exception->getFile(), 8000))
                ->setSourceLine($exception->getLine())
                ->setTrace($this->trace($exception))
                ->setContext($this->context($request))
                ->setIp($request->getClientIp())
                ->setUserAgent($this->cut($request->headers->get('User-Agent'), 4000))
                ->setReferer($this->cut($request->headers->get('Referer'), 4000))
                ->setUser($user);

            $this->em->persist($incident);
            $this->em->flush();
            return $incident;
        } catch (\Throwable $loggingFailure) {
            $this->logger->critical('Impossible d’enregistrer un incident technique.', [
                'original_exception' => $exception::class,
                'original_message' => $exception->getMessage(),
                'recording_error' => $loggingFailure->getMessage(),
            ]);
            return null;
        }
    }

    private function fingerprint(\Throwable $e, Request $request, int $status): string
    {
        $message = preg_replace('/\b\d+\b/', '#', mb_strtolower($e->getMessage())) ?: '';
        return hash('sha256', implode('|', [$status, $e::class, $request->getPathInfo(), $message]));
    }

    private function severityForStatus(int $status): string
    {
        return match (true) {
            $status >= 500 => Incident::SEVERITY_CRITICAL,
            $status === 404 => Incident::SEVERITY_WARNING,
            $status >= 400 => Incident::SEVERITY_ERROR,
            default => Incident::SEVERITY_INFO,
        };
    }

    private function createReference(): string
    {
        try { $random = strtoupper(bin2hex(random_bytes(4))); }
        catch (\Throwable) { $random = strtoupper(substr(hash('sha256', uniqid('', true)), 0, 8)); }
        return sprintf('MOL-%s-%s', (new \DateTimeImmutable())->format('Ymd-His'), $random);
    }

    private function trace(\Throwable $e): array
    {
        return array_slice(array_map(static function (array $frame): array {
            return [
                'file' => $frame['file'] ?? null,
                'line' => $frame['line'] ?? null,
                'class' => $frame['class'] ?? null,
                'type' => $frame['type'] ?? null,
                'function' => $frame['function'] ?? null,
            ];
        }, $e->getTrace()), 0, 80);
    }

    private function context(Request $request): array
    {
        $routeParameters = $request->attributes->get('_route_params', []);
        if (!is_array($routeParameters)) $routeParameters = [];

        return [
            'route_parameters' => $this->redact($routeParameters),
            'query_keys' => array_keys($request->query->all()),
            'is_ajax' => $request->isXmlHttpRequest(),
            'locale' => $request->getLocale(),
        ];
    }

    private function redact(array $data): array
    {
        $sensitive = ['password', 'plainPassword', 'token', '_token', 'authorization', 'secret', 'code'];
        foreach ($data as $key => $value) {
            if (in_array((string) $key, $sensitive, true)) $data[$key] = '[redacted]';
        }
        return $data;
    }

    private function cut(?string $value, int $max): ?string
    {
        if ($value === null || $value === '') return $value;
        return mb_substr($value, 0, $max);
    }
}
