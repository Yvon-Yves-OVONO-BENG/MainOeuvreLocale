<?php

namespace App\Service;

use App\Entity\Message;
use App\Entity\Report;
use App\Entity\ReportJob;
use App\Entity\Review;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class AdminContentModerationService
{
    private const SUSPICIOUS_TERMS = [
        'arnaque',
        'escroquerie',
        'fraude',
        'spam',
        'harcèlement',
        'harcelement',
        'insulte',
        'menace',
        'violence',
        'faux paiement',
        'paiement en avance',
        'contact whatsapp',
        'numéro direct',
        'numero direct',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function getQueueData(int $limit = 80): array
    {
        $items = array_merge(
            $this->normalizeProfileReports(
                $this->entityManager->getRepository(Report::class)->findBy([], ['createdAt' => 'DESC'], $limit)
            ),
            $this->normalizeJobReports(
                $this->entityManager->getRepository(ReportJob::class)->findBy([], ['createdAt' => 'DESC'], $limit)
            ),
            $this->normalizeSuspiciousMessages(
                $this->entityManager->getRepository(Message::class)->findBy([], ['createdAt' => 'DESC'], $limit)
            ),
            $this->normalizeSuspiciousReviews(
                $this->entityManager->getRepository(Review::class)->findBy([], ['createdAt' => 'DESC'], $limit)
            ),
        );

        $items = array_values(array_filter($items, static fn(array $item): bool => $item['shouldAppear'] === true));

        usort($items, static function (array $a, array $b): int {
            $aTime = $a['createdAt']?->getTimestamp() ?? 0;
            $bTime = $b['createdAt']?->getTimestamp() ?? 0;

            return $bTime <=> $aTime;
        });

        $items = array_slice($items, 0, 120);

        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');

        return [
            'items' => $items,
            'stats' => [
                'queue' => count($items),
                'urgent' => count(array_filter($items, static fn(array $i): bool => in_array($i['severity'], ['high', 'critical'], true))),
                'approvedToday' => count(array_filter(
                    $items,
                    static fn(array $i): bool =>
                        $i['status'] === 'approved'
                        && $i['createdAt'] instanceof \DateTimeInterface
                        && $i['createdAt']->format('Y-m-d') === $today
                )),
                'rejectedToday' => count(array_filter(
                    $items,
                    static fn(array $i): bool =>
                        in_array($i['status'], ['rejected', 'blocked'], true)
                        && $i['createdAt'] instanceof \DateTimeInterface
                        && $i['createdAt']->format('Y-m-d') === $today
                )),
            ],
        ];
    }

    /**
     * @param array<int, Report> $reports
     * @return array<int, array<string, mixed>>
     */
    private function normalizeProfileReports(array $reports): array
    {
        $rows = [];

        foreach ($reports as $report) {
            $targetUser = $report->getTargetUser();
            $reporter = $report->getReporter();
            $category = $report->getCategorie();

            $status = match ($report->getStatus()) {
                Report::STATUS_OPEN => $report->getHandledBy() ? 'reviewing' : 'pending',
                Report::STATUS_RESOLVED => 'approved',
                Report::STATUS_CANCELED => 'rejected',
                default => 'pending',
            };

            $reasonText = trim($report->getReason());
            $severity = $this->computeSeverityFromText(
                ($category && method_exists($category, 'getNom') ? (string) $category->getLabel() : '') . ' ' . $reasonText
            );

            if ($report->isSupprimer()) {
                $severity = 'critical';
            }

            $rows[] = [
                'id' => $report->getId(),
                'type' => 'Profil',
                'title' => 'Profil signalé : ' . $this->resolveUserName($targetUser),
                'author' => $this->resolveUserName($reporter),
                'excerpt' => $this->buildProfileReportExcerpt($report),
                'source' => 'Report',
                'severity' => $severity,
                'status' => $status,
                'createdAt' => $report->getCreatedAt(),
                'shouldAppear' => true,
            ];
        }

        return $rows;
    }

    /**
     * @param array<int, ReportJob> $reports
     * @return array<int, array<string, mixed>>
     */
    private function normalizeJobReports(array $reports): array
    {
        $rows = [];

        foreach ($reports as $report) {
            $job = $report->getJob();
            $reporter = $report->getUser();

            $status = match (strtolower($report->getStatus())) {
                'resolved' => 'approved',
                'rejected' => 'rejected',
                'reviewed' => 'reviewing',
                default => 'pending',
            };

            $severity = $this->computeSeverityFromText($report->getReason() ?? '');

            $rows[] = [
                'id' => $report->getId(),
                'type' => 'Offre',
                'title' => $job?->getTitle() ?: ('Offre #' . ($job?->getId() ?? '—')),
                'author' => $this->resolveUserName($job?->getCreatedBy()),
                'excerpt' => sprintf(
                    'Signalée par %s • %s',
                    $this->resolveUserName($reporter),
                    $this->excerpt($report->getReason(), 140)
                ),
                'source' => 'ReportJob',
                'severity' => $severity,
                'status' => $status,
                'createdAt' => $report->getCreatedAt(),
                'shouldAppear' => true,
            ];
        }

        return $rows;
    }

    /**
     * @param array<int, Message> $messages
     * @return array<int, array<string, mixed>>
     */
    private function normalizeSuspiciousMessages(array $messages): array
    {
        $rows = [];

        foreach ($messages as $message) {
            if ($message->isDeleted()) {
                continue;
            }

            $content = trim((string) $message->getContent());

            if (!$this->isSuspicious($content)) {
                continue;
            }

            $severity = $this->computeSeverityFromText($content);

            $rows[] = [
                'id' => $message->getId(),
                'type' => 'Message',
                'title' => $this->excerpt($content, 70),
                'author' => $this->resolveUserName($message->getSender()),
                'excerpt' => $this->excerpt($content, 150),
                'source' => 'Inbox',
                'severity' => $severity,
                'status' => 'pending',
                'createdAt' => $message->getCreatedAt(),
                'shouldAppear' => true,
            ];
        }

        return $rows;
    }

    /**
     * @param array<int, Review> $reviews
     * @return array<int, array<string, mixed>>
     */
    private function normalizeSuspiciousReviews(array $reviews): array
    {
        $rows = [];

        foreach ($reviews as $review) {
            $comment = trim((string) $review->getComment());

            if (!$this->isSuspicious($comment)) {
                continue;
            }

            $severity = $this->computeSeverityFromText($comment);

            $rows[] = [
                'id' => $review->getId(),
                'type' => 'Avis',
                'title' => $this->excerpt($comment, 70),
                'author' => $this->resolveUserName($review->getTargetBy()),
                'excerpt' => sprintf(
                    'Cible : %s • %s',
                    $this->resolveUserName($review->getTargetReview()),
                    $this->excerpt($comment, 140)
                ),
                'source' => 'Review',
                'severity' => $severity,
                'status' => 'pending',
                'createdAt' => $review->getCreatedAt(),
                'shouldAppear' => true,
            ];
        }

        return $rows;
    }

    private function buildProfileReportExcerpt(Report $report): string
    {
        $parts = [];

        if ($report->getCategorie() !== null) {
            $category = $this->extractEntityLabel($report->getCategorie());
            if ($category) {
                $parts[] = 'Catégorie : ' . $category;
            }
        }

        $parts[] = 'Cible : ' . $this->resolveUserName($report->getTargetUser());

        if (trim($report->getReason()) !== '') {
            $parts[] = $this->excerpt($report->getReason(), 140);
        }

        return implode(' • ', $parts);
    }

    private function computeSeverityFromText(string $text): string
    {
        $text = mb_strtolower($text);

        if ($text === '') {
            return 'medium';
        }

        $criticalTerms = ['fraude', 'escroquerie', 'arnaque', 'harcèlement', 'harcelement', 'menace', 'violence'];
        foreach ($criticalTerms as $term) {
            if (str_contains($text, $term)) {
                return 'critical';
            }
        }

        $highTerms = ['spam', 'whatsapp', 'paiement', 'insulte', 'abus'];
        foreach ($highTerms as $term) {
            if (str_contains($text, $term)) {
                return 'high';
            }
        }

        return 'medium';
    }

    private function isSuspicious(string $text): bool
    {
        $text = mb_strtolower(trim($text));

        if ($text === '') {
            return false;
        }

        foreach (self::SUSPICIOUS_TERMS as $term) {
            if (str_contains($text, $term)) {
                return true;
            }
        }

        return false;
    }

    private function resolveUserName(?User $user): string
    {
        if ($user === null) {
            return '—';
        }

        $profile = $user->getPersonalProfile();

        if ($profile !== null && method_exists($profile, 'getFullName')) {
            $fullName = $profile->getFullName();
            if (is_string($fullName) && trim($fullName) !== '') {
                return $fullName;
            }
        }

        return $user->getEmail() ?? ('User #' . $user->getId());
    }

    private function extractEntityLabel(object|null $entity): ?string
    {
        if ($entity === null) {
            return null;
        }

        foreach (['getNom', 'getName', 'getLabel', 'getLibelle', '__toString'] as $method) {
            if (method_exists($entity, $method)) {
                $value = $entity->{$method}();
                if (is_string($value) && trim($value) !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    private function excerpt(?string $text, int $max = 120): string
    {
        $text = trim((string) $text);

        if ($text === '') {
            return '—';
        }

        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        if (mb_strlen($text) <= $max) {
            return $text;
        }

        return mb_substr($text, 0, $max - 1) . '…';
    }
}