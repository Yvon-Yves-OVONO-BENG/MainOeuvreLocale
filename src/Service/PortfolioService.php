<?php

namespace App\Service;

use App\Entity\ProfessionalMedia;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class PortfolioService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PortfolioUploader $portfolioUploader,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly string $portfolioDirectory,
    ) {
    }

    public function requireTalentUser(?object $user): User
    {
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('Profil talent introuvable.');
        }

        if (!$user->getProfessionalProfile()) {
            throw new AccessDeniedHttpException('Profil talent introuvable.');
        }

        return $user;
    }

    public function getPortfolioPageData(User $user): array
    {
        $user = $this->requireTalentUser($user);

        return [
            'pro' => $user->getProfessionalProfile(),
        ];
    }

    /**
     * @param UploadedFile[] $files
     */
    public function uploadFiles(User $user, array $files): array
    {
        $user = $this->requireTalentUser($user);
        $professionalProfile = $user->getProfessionalProfile();

        $talentName = $user->getPersonalProfile()?->getFullName()
            ?: ($user->getEmail() ?: 'talent');

        foreach ($files as $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }

            $filename = $this->portfolioUploader->upload($file, $talentName, $user->getId());

            $media = new ProfessionalMedia();
            $media->setProfessionalProfile($professionalProfile);
            $media->setFile($filename);
            $media->setCreatedAt(new \DateTime());

            $this->entityManager->persist($media);
        }

        $this->entityManager->flush();

        return [
            'ok' => true,
            'message' => 'Photos ajoutées au portfolio.',
        ];
    }

    public function deleteMedia(User $user, ProfessionalMedia $media, ?string $csrfToken = null, bool $checkCsrf = true): array
    {
        $user = $this->requireTalentUser($user);
        $professionalProfile = $user->getProfessionalProfile();

        if ($media->getProfessionalProfile()?->getId() !== $professionalProfile?->getId()) {
            throw new AccessDeniedHttpException('Suppression interdite.');
        }

        if ($checkCsrf && !$this->isValidDeleteToken($media, $csrfToken)) {
            return [
                'ok' => false,
                'message' => 'Token invalide.',
            ];
        }

        $path = rtrim($this->portfolioDirectory, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . $media->getFile();

        $filesystem = new Filesystem();

        if ($filesystem->exists($path)) {
            $filesystem->remove($path);
        }

        $this->entityManager->remove($media);
        $this->entityManager->flush();

        return [
            'ok' => true,
            'message' => 'Photo supprimée.',
        ];
    }

    public function getApiIndexPayload(User $user): array
    {
        $user = $this->requireTalentUser($user);
        $professionalProfile = $user->getProfessionalProfile();

        $mediaItems = [];
        foreach ($professionalProfile?->getProfessionalMedia() ?? [] as $media) {
            if ($media instanceof ProfessionalMedia) {
                $mediaItems[] = $this->formatMedia($media);
            }
        }

        return [
            'ok' => true,
            'profileId' => $professionalProfile?->getId(),
            'media' => $mediaItems,
        ];
    }

    public function getApiDeletePayload(User $user, ProfessionalMedia $media): array
    {
        return $this->deleteMedia($user, $media, null, false);
    }

    private function isValidDeleteToken(ProfessionalMedia $media, ?string $csrfToken): bool
    {
        return $this->csrfTokenManager->isTokenValid(
            new CsrfToken('del_media_' . $media->getId(), (string) $csrfToken)
        );
    }

    private function formatMedia(ProfessionalMedia $media): array
    {
        return [
            'id' => $media->getId(),
            'file' => $media->getFile(),
            'createdAt' => $media->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}