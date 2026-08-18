<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\ConversationRepository;
use App\Repository\JobRepository;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class PlanningService
{
    public function __construct(
        private readonly JobRepository $jobRepository,
        private readonly ConversationRepository $conversationRepository,
    ) {
    }

    public function requireUser(?object $user): User
    {
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('Accès refusé.');
        }

        return $user;
    }

    public function getPlanningData(User $user): array
    {
        $now = new \DateTimeImmutable();
        $todayStart = (new \DateTimeImmutable('today'))->setTime(0, 0, 0);
        $todayEnd = (new \DateTimeImmutable('today'))->setTime(23, 59, 59);
        $weekEnd = $todayEnd->modify('+7 days');
        $relanceDays = 3;
        $relanceCutoff = $now->modify(sprintf('-%d days', $relanceDays));

        $jobsToday = $this->jobRepository->findExpiringToday($user, $todayStart, $todayEnd);
        $jobsUpcoming = $this->jobRepository->findUpcoming($user, $todayEnd, $weekEnd, 20);
        $jobsOverdue = $this->jobRepository->findOverdue($user, $todayStart, 20);
        $recentConversations = $this->conversationRepository->findRecentForUser($user, 12);
        $relanceConversations = $this->conversationRepository->findToRelance($user, $relanceCutoff, 10);

        $agenda = $this->buildAgenda(
            $jobsToday,
            $jobsUpcoming,
            $relanceConversations,
            $user,
            $now
        );

        $stats = [
            'deadlinesToday' => count($jobsToday),
            'deadlinesWeek' => count($jobsUpcoming),
            'overdueJobs' => count($jobsOverdue),
            'relances' => count($relanceConversations),
        ];

        return [
            'now' => $now,
            'relanceDays' => $relanceDays,
            'stats' => $stats,
            'agenda' => array_slice($agenda, 0, 20),
            'jobsToday' => $jobsToday,
            'jobsUpcoming' => $jobsUpcoming,
            'jobsOverdue' => $jobsOverdue,
            'recentConversations' => $recentConversations,
            'relanceConversations' => $relanceConversations,
            'me' => $user,
        ];
    }

    public function getApiPlanningPayload(User $user): array
    {
        $data = $this->getPlanningData($user);

        return [
            'ok' => true,
            'now' => $data['now']->format(\DateTimeInterface::ATOM),
            'relanceDays' => $data['relanceDays'],
            'stats' => $data['stats'],
            'agenda' => array_map(
                fn (array $item) => $this->formatAgendaItem($item),
                $data['agenda']
            ),
            'jobsToday' => array_map(
                fn ($job) => $this->formatJob($job),
                $data['jobsToday']
            ),
            'jobsUpcoming' => array_map(
                fn ($job) => $this->formatJob($job),
                $data['jobsUpcoming']
            ),
            'jobsOverdue' => array_map(
                fn ($job) => $this->formatJob($job),
                $data['jobsOverdue']
            ),
            'recentConversations' => array_map(
                fn ($conversation) => $this->formatConversation($conversation, $user),
                $data['recentConversations']
            ),
            'relanceConversations' => array_map(
                fn ($conversation) => $this->formatConversation($conversation, $user),
                $data['relanceConversations']
            ),
            'me' => [
                'id' => $user->getId(),
                'email' => $user->getUserIdentifier(),
            ],
        ];
    }

    private function buildAgenda(
        array $jobsToday,
        array $jobsUpcoming,
        array $relanceConversations,
        User $user,
        \DateTimeImmutable $now
    ): array {
        $agenda = [];

        foreach ($jobsToday as $job) {
            $agenda[] = [
                'type' => 'job_deadline_today',
                'date' => $job->getDateExpirationAt(),
                'title' => $job->getTitle() ?: ('Job #' . $job->getId()),
                'subtitle' => 'Deadline aujourd’hui',
                'city' => $job->getCity(),
                'slug' => $job->getSlug(),
                'reference' => $job->getReference(),
                'url' => '/particulier/projects/' . $job->getId(),
                'icon' => '🔥',
                'tone' => 'danger',
            ];
        }

        foreach ($jobsUpcoming as $job) {
            $agenda[] = [
                'type' => 'job_deadline_upcoming',
                'date' => $job->getDateExpirationAt(),
                'title' => $job->getTitle() ?: ('Job #' . $job->getId()),
                'subtitle' => 'Deadline à venir',
                'city' => $job->getCity(),
                'slug' => $job->getSlug(),
                'reference' => $job->getReference(),
                'url' => '/particulier/projects/' . $job->getId(),
                'icon' => '🗂️',
                'tone' => 'primary',
            ];
        }

        foreach ($relanceConversations as $conversation) {
            $lastActivity = $conversation->getLastMessageAt() ?? $conversation->getCreatedAt();
            $other = $conversation->getOtherParticipant($user);

            $agenda[] = [
                'type' => 'conversation_relance',
                'date' => $lastActivity instanceof \DateTimeInterface
                    ? \DateTimeImmutable::createFromInterface($lastActivity)
                    : $now,
                'title' => 'Relancer ' . ($other?->getUserIdentifier() ?? 'un contact'),
                'subtitle' => 'Conversation inactive',
                'city' => null,
                'slug' => null,
                'reference' => null,
                'url' => '/messages/' . $conversation->getId(),
                'icon' => '💬',
                'tone' => 'warning',
            ];
        }

        usort($agenda, static function (array $a, array $b): int {
            $aTs = $a['date'] instanceof \DateTimeInterface ? $a['date']->getTimestamp() : 0;
            $bTs = $b['date'] instanceof \DateTimeInterface ? $b['date']->getTimestamp() : 0;

            return $aTs <=> $bTs;
        });

        return $agenda;
    }

    private function formatAgendaItem(array $item): array
    {
        return [
            'type' => $item['type'],
            'date' => $item['date'] instanceof \DateTimeInterface
                ? $item['date']->format(\DateTimeInterface::ATOM)
                : null,
            'title' => $item['title'],
            'subtitle' => $item['subtitle'],
            'city' => $item['city'],
            'slug' => $item['slug'],
            'reference' => $item['reference'],
            'url' => $item['url'],
            'icon' => $item['icon'],
            'tone' => $item['tone'],
        ];
    }

    private function formatJob(object $job): array
    {
        return [
            'id' => method_exists($job, 'getId') ? $job->getId() : null,
            'title' => method_exists($job, 'getTitle') ? $job->getTitle() : null,
            'city' => method_exists($job, 'getCity') ? $job->getCity() : null,
            'slug' => method_exists($job, 'getSlug') ? $job->getSlug() : null,
            'reference' => method_exists($job, 'getReference') ? $job->getReference() : null,
            'dateExpirationAt' => method_exists($job, 'getDateExpirationAt') && $job->getDateExpirationAt()
                ? $job->getDateExpirationAt()->format(\DateTimeInterface::ATOM)
                : null,
        ];
    }

    private function formatConversation(object $conversation, User $user): array
    {
        $other = method_exists($conversation, 'getOtherParticipant')
            ? $conversation->getOtherParticipant($user)
            : null;

        return [
            'id' => method_exists($conversation, 'getId') ? $conversation->getId() : null,
            'lastMessageAt' => method_exists($conversation, 'getLastMessageAt') && $conversation->getLastMessageAt()
                ? $conversation->getLastMessageAt()->format(\DateTimeInterface::ATOM)
                : null,
            'createdAt' => method_exists($conversation, 'getCreatedAt') && $conversation->getCreatedAt()
                ? $conversation->getCreatedAt()->format(\DateTimeInterface::ATOM)
                : null,
            'otherParticipant' => $other ? [
                'id' => method_exists($other, 'getId') ? $other->getId() : null,
                'identifier' => method_exists($other, 'getUserIdentifier') ? $other->getUserIdentifier() : null,
            ] : null,
            'url' => method_exists($conversation, 'getId') ? '/messages/' . $conversation->getId() : null,
        ];
    }
}