<?php

namespace App\Util;

final class HashedSlugGenerator
{
    private function __construct()
    {
    }

    /**
     * Génère un identifiant public opaque de 64 caractères.
     *
     * Le sel aléatoire demandé (random_bytes(4)) est combiné à uniqid(),
     * puis l'ensemble est condensé avec SHA-256.
     */
    public static function generate(): string
    {
        return hash(
            'sha256',
            bin2hex(random_bytes(64)) . uniqid('', true)
        );
    }
}
