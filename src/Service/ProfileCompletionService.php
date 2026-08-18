<?php

namespace App\Service;

use App\Entity\User;

class ProfileCompletionService
{
    /**
     * Calcule le pourcentage de complétion adapté au type de compte.
     */
    public function calculate(User $user): int
    {
        if ($this->isCompany($user)) {
            return min(100, $this->scoreCompany($user));
        }

        if ($this->isParticulier($user)) {
            return $this->normalizeTo100($this->scorePersonal($user), 35);
        }

        if ($this->isTalent($user)) {
            return min(100, $this->scorePersonal($user) + $this->scoreProfessional($user));
        }

        return $this->normalizeTo100($this->scorePersonal($user), 35);
    }

    /**
     * Retourne le libellé de profil affiché dans les tableaux de bord.
     */
    public function label(User $user): string
    {
        if ($this->isCompany($user)) {
            return 'Profil entreprise';
        }

        if ($this->isParticulier($user)) {
            return 'Profil particulier (infos personnelles)';
        }

        if ($this->isTalent($user)) {
            return 'Profil talent (personnel + pro)';
        }

        return 'Profil (infos générales)';
    }

    /**
     * Explique les données prises en compte dans la complétion.
     */
    public function hint(User $user): string
    {
        if ($this->isCompany($user)) {
            return "Complétion basée sur les informations de l’entreprise : identité légale, contact, adresse et présentation.";
        }

        if ($this->isParticulier($user)) {
            return 'Complétion basée sur votre profil personnel.';
        }

        if ($this->isTalent($user)) {
            return 'Complétion basée sur vos infos personnelles et professionnelles.';
        }

        return 'Complétion basée sur les informations disponibles.';
    }

    /**
     * Indique si le profil contient toutes les informations minimales de visibilité.
     */
    public function isReadyForPublicVisibility(User $user): bool
    {
        return $this->getMissingVisibilityParts($user) === [];
    }

    /**
     * Liste les éléments qui empêchent encore la publication du profil.
     *
     * @return list<string>
     */
    public function getMissingVisibilityParts(User $user): array
    {
        $profile = $user->getPersonalProfile();
        $missing = [];

        if ($profile === null) {
            $missing[] = 'profil personnel';
        } else {
            if (!$user->hasPublicDisplayName()) {
                $missing[] = $this->isCompany($user) ? 'nom de la compagnie' : 'nom complet';
            }

            if (!$this->hasRealPhoto($profile->getPhoto())) {
                $missing[] = $this->isCompany($user) ? 'logo de la compagnie' : 'photo de profil';
            }
        }

        if ($this->isTalent($user)) {
            $professional = $user->getProfessionalProfile();

            if ($professional === null) {
                $missing[] = 'profil professionnel';
            } else {
                if ($professional->getProfession() === null) {
                    $missing[] = 'profession';
                }
                if (!$this->hasText($professional->getBio())) {
                    $missing[] = 'présentation professionnelle';
                }
                if (!$this->hasText($professional->getExperience())) {
                    $missing[] = 'expérience professionnelle';
                }
                if (!$this->hasText($professional->getCv())) {
                    $missing[] = 'CV';
                }
            }
        }

        return array_values(array_unique($missing));
    }

    /**
     * Vérifie la présence d'un rôle précis sur le compte.
     */
    private function hasRole(User $user, string $role): bool
    {
        return in_array($role, $user->getRoles(), true);
    }

    /**
     * Détermine si le compte représente une compagnie.
     */
    private function isCompany(User $user): bool
    {
        return $this->hasRole($user, 'ROLE_COMPANY')
            || $this->hasRole($user, 'ROLE_ENTREPRISE');
    }

    /**
     * Détermine si le compte représente un talent.
     */
    private function isTalent(User $user): bool
    {
        return $this->hasRole($user, 'ROLE_TALENT');
    }

    /**
     * Détermine si le compte représente un particulier.
     */
    private function isParticulier(User $user): bool
    {
        return $this->hasRole($user, 'ROLE_PARTICULIER');
    }

    /**
     * Calcule la complétion des informations personnelles.
     */
    private function scorePersonal(User $user): int
    {
        $profile = $user->getPersonalProfile();
        $score = 0;

        if ($profile) {
            if ($user->hasPublicDisplayName()) {
                $score += 10;
            }
            if ($this->hasRealPhoto($profile->getPhoto())) {
                $score += 10;
            }
            if ($this->hasText($profile->getAdress())) {
                $score += 5;
            }
            if ($this->hasText($profile->getCity())) {
                $score += 5;
            }
            if ($profile->getSexe()) {
                $score += 5;
            }
        }

        return $score;
    }

    /**
     * Calcule la complétion des informations professionnelles du talent.
     */
    private function scoreProfessional(User $user): int
    {
        $professional = $user->getProfessionalProfile();
        $score = 0;

        if ($professional) {
            if ($professional->getProfession()) {
                $score += 10;
            }
            if ($this->hasText($professional->getBio())) {
                $score += 10;
            }
            if ($this->hasText($professional->getExperience())) {
                $score += 10;
            }
            if ($this->hasText($professional->getExperienceYears())) {
                $score += 5;
            }

            $skillsCount = $professional->getSkills()?->count() ?? 0;
            if ($skillsCount >= 3) {
                $score += 10;
            } elseif ($skillsCount >= 1) {
                $score += 5;
            }

            if ($this->hasText($professional->getCv())) {
                $score += 5;
            }
            if ($professional->getLatitude() && $professional->getLongitude()) {
                $score += 5;
            }

            $mediaCount = $professional->getProfessionalMedia()?->count() ?? 0;
            if ($mediaCount >= 3) {
                $score += 5;
            } elseif ($mediaCount === 2) {
                $score += 4;
            } elseif ($mediaCount === 1) {
                $score += 2;
            }

            if ($professional->isVerified()) {
                $score += 10;
            }
        }

        return $score;
    }

    /**
     * Calcule la complétion du profil compagnie stocké dans PersonalProfile.
     */
    private function scoreCompany(User $user): int
    {
        $profile = $user->getPersonalProfile();
        $score = 0;

        if (!$profile) {
            return 0;
        }

        if ($this->hasText($profile->getCompanyLegalName())) {
            $score += 15;
        }
        if ($this->hasText($profile->getCompanyTradeName())) {
            $score += 10;
        }
        if ($this->hasText($profile->getCompanyRegistrationNumber())) {
            $score += 15;
        }
        if ($this->hasText($profile->getCompanyTaxNumber())) {
            $score += 10;
        }
        if ($this->hasText($profile->getCompanyContactName())) {
            $score += 10;
        }
        if ($this->hasText($profile->getCompanyContactPhone())) {
            $score += 10;
        }
        if ($this->hasText($profile->getCompanyWebsite())) {
            $score += 5;
        }
        if ($this->hasText($profile->getCompanyDescription())) {
            $score += 10;
        }
        if ($this->hasRealPhoto($profile->getPhoto())) {
            $score += 5;
        }
        if ($this->hasText($profile->getAdress())) {
            $score += 5;
        }
        if ($this->hasText($profile->getCity())) {
            $score += 5;
        }

        return min(100, $score);
    }

    /**
     * Convertit un score partiel en pourcentage borné entre 0 et 100.
     */
    private function normalizeTo100(int $score, int $max): int
    {
        if ($max <= 0 || $score <= 0) {
            return 0;
        }

        return (int) min(100, round(($score / $max) * 100));
    }

    /**
     * Vérifie qu'une valeur texte contient une information réelle.
     */
    private function hasText(mixed $value): bool
    {
        return trim((string) $value) !== '';
    }

    /**
     * Refuse les avatars techniques afin qu'ils ne rendent pas un profil visible.
     */
    private function hasRealPhoto(?string $photo): bool
    {
        $value = trim((string) $photo);
        $basename = mb_strtolower((string) pathinfo(str_replace('\\', '/', $value), PATHINFO_BASENAME));

        return $value !== ''
            && !in_array($basename, ['avatar.png', 'default-avatar.png', 'default.png'], true);
    }
}
