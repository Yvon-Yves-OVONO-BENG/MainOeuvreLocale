<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\SanctionRepository;

class TalentSanctionService
{
    public function __construct(
        private readonly SanctionRepository $sanctionRepository
    ) {
    }

    public function getIndexData(User $user): array
    {
        $sanctions = $this->sanctionRepository->findVisibleForUser($user);

        $activeCount = 0;
        $temporaryCount = 0;
        $permanentCount = 0;

        foreach ($sanctions as $sanction) {
            if ($sanction->isActive()) {
                ++$activeCount;
            }

            if ($sanction->isTemporary()) {
                ++$temporaryCount;
            }

            if ($sanction->isPermanent()) {
                ++$permanentCount;
            }
        }

        return [
            'sanctions' => $sanctions,
            'activeCount' => $activeCount,
            'temporaryCount' => $temporaryCount,
            'permanentCount' => $permanentCount,
            'totalCount' => count($sanctions),
        ];
    }

    public function getApiIndexPayload(User $user): array
    {
        $data = $this->getIndexData($user);

        return [
            'ok' => true,
            'sanctions' => array_map(
                fn ($sanction) => $this->formatSanction($sanction),
                $data['sanctions']
            ),
            'stats' => [
                'activeCount' => $data['activeCount'],
                'temporaryCount' => $data['temporaryCount'],
                'permanentCount' => $data['permanentCount'],
                'totalCount' => $data['totalCount'],
            ],
        ];
    }

    private function formatSanction(object $sanction): array
    {
        return [
            'id' => method_exists($sanction, 'getId') ? $sanction->getId() : null,
            'reason' => method_exists($sanction, 'getReason') ? $sanction->getReason() : null,
            'type' => method_exists($sanction, 'getType') ? $sanction->getType() : null,
            'isActive' => method_exists($sanction, 'isActive') ? $sanction->isActive() : null,
            'isTemporary' => method_exists($sanction, 'isTemporary') ? $sanction->isTemporary() : null,
            'isPermanent' => method_exists($sanction, 'isPermanent') ? $sanction->isPermanent() : null,
            'startsAt' => method_exists($sanction, 'getStartsAt') && $sanction->getStartsAt()
                ? $sanction->getStartsAt()->format(\DateTimeInterface::ATOM)
                : null,
            'endsAt' => method_exists($sanction, 'getEndsAt') && $sanction->getEndsAt()
                ? $sanction->getEndsAt()->format(\DateTimeInterface::ATOM)
                : null,
            'createdAt' => method_exists($sanction, 'getCreatedAt') && $sanction->getCreatedAt()
                ? $sanction->getCreatedAt()->format(\DateTimeInterface::ATOM)
                : null,
        ];
    }
}