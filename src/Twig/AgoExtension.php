<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class AgoExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('ago', [$this, 'agoFilter']),
        ];
    }

    public function agoFilter(?\DateTimeInterface $date): string
    {
        if (!$date) {
            return '';
        }

        $now = new \DateTime();
        $diff = $now->diff($date);

        if ($diff->y > 0) {
            return 'il y a ' . $diff->y . ' an(s)';
        }

        if ($diff->m > 0) {
            return 'il y a ' . $diff->m . ' mois';
        }

        if ($diff->d > 0) {
            return 'il y a ' . $diff->d . ' jour(s)';
        }

        if ($diff->h > 0) {
            return 'il y a ' . $diff->h . ' heure(s)';
        }

        if ($diff->i > 0) {
            return 'il y a ' . $diff->i . ' minute(s)';
        }

        return 'à l’instant';
    }
}
