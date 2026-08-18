<?php

namespace App\Service\Search;

final class SearchInputSanitizer
{
    private const MAX_LENGTH = 160;

    public function sanitizeKeyword(mixed $value, int $maxLength = self::MAX_LENGTH): string
    {
        $raw = (string) $value;
        $value = trim(function_exists('mb_substr')
            ? mb_substr($raw, 0, max(1, $maxLength), 'UTF-8')
            : substr($raw, 0, max(1, $maxLength)));
        if ($value === '') {
            return '';
        }

        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        // Un métier, un poste ou une compétence peut contenir des lettres,
        // espaces, apostrophes, +, - et /. Les chiffres sont autorisés dans
        // un intitulé mixte (ex. « Support N1 »), mais jamais seuls.
        if (!preg_match("/^[\\p{L}\\p{M}0-9 .,'’+\\-\\/()]+$/u", $value)) {
            throw new \InvalidArgumentException(
                'Utilisez uniquement des lettres, espaces, apostrophes, +, - ou /. '
                .'Les chiffres seuls ne sont pas acceptés.'
            );
        }

        if (preg_match('/^\d+$/', preg_replace("/[\s.,'’()\-+\/]/u", '', $value) ?? $value)) {
            throw new \InvalidArgumentException(
                'Saisissez un métier, un poste ou une compétence, pas uniquement des chiffres.'
            );
        }

        return $value;
    }


    public function sanitizeLocation(mixed $value, int $maxLength = 120): string
    {
        $raw = (string) $value;
        $value = trim(function_exists('mb_substr')
            ? mb_substr($raw, 0, max(1, $maxLength), 'UTF-8')
            : substr($raw, 0, max(1, $maxLength)));

        if ($value === '') {
            return '';
        }

        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        if (!preg_match("/^[\p{L}\p{M}0-9 .,'’+\-\/()]+$/u", $value)) {
            throw new \InvalidArgumentException(
                'Utilisez uniquement des lettres, espaces, apostrophes, virgules, points, tirets ou barres obliques.'
            );
        }

        if (preg_match('/^\d+$/', preg_replace("/[\s.,'’()\-+\/]/u", '', $value) ?? $value)) {
            throw new \InvalidArgumentException(
                'Saisissez une ville, une région ou un pays, pas uniquement des chiffres.'
            );
        }

        return $value;
    }

    public function length(string $value): int
    {
        return function_exists('mb_strlen')
            ? mb_strlen($value, 'UTF-8')
            : strlen($value);
    }

    public function normalize(string $value): string
    {
        $value = trim($value);
        $value = function_exists('mb_strtolower')
            ? mb_strtolower($value, 'UTF-8')
            : strtolower($value);

        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($value, \Normalizer::FORM_D);
            if (is_string($normalized)) {
                $value = preg_replace('/\p{Mn}+/u', '', $normalized) ?? $normalized;
            }
        } else {
            $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if (is_string($ascii)) {
                $value = $ascii;
            }
        }

        return preg_replace('/\s+/u', ' ', $value) ?? $value;
    }
}
