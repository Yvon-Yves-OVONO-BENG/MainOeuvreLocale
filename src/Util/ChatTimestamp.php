<?php

namespace App\Util;

/**
 * Centralise les horodatages du chat.
 *
 * Le serveur échange des instants ISO normalisés en UTC. Le navigateur peut
 * ensuite les afficher dans le fuseau local configuré sur l'appareil, sans
 * imposer de fuseau géographique à tous les utilisateurs.
 */
final class ChatTimestamp
{
    /** Fuseau des anciennes colonnes DATETIME sans information de fuseau. */
    private const LEGACY_STORAGE_TIMEZONE = 'Africa/Douala';
    private const MAX_CLIENT_CLOCK_DRIFT_SECONDS = 86400;

    public static function sanitizeClientSentAt(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            $instant = new \DateTimeImmutable(trim($value));
        } catch (\Throwable) {
            return null;
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        if (abs($instant->getTimestamp() - $now->getTimestamp()) > self::MAX_CLIENT_CLOCK_DRIFT_SECONDS) {
            return null;
        }

        return $instant
            ->setTimezone(new \DateTimeZone('UTC'))
            ->format('Y-m-d\TH:i:s.v\Z');
    }

    public static function iso(\DateTimeInterface $storedAt, array $meta = []): string
    {
        return self::resolve($storedAt, $meta)->format(\DATE_ATOM);
    }

    public static function clock(\DateTimeInterface $storedAt, array $meta = []): string
    {
        return self::resolve($storedAt, $meta)->format('H:i');
    }

    private static function resolve(\DateTimeInterface $storedAt, array $meta): \DateTimeImmutable
    {
        $utc = new \DateTimeZone('UTC');
        $clientValue = $meta['clientSentAt'] ?? null;

        if (is_string($clientValue) && trim($clientValue) !== '') {
            try {
                return (new \DateTimeImmutable(trim($clientValue)))->setTimezone($utc);
            } catch (\Throwable) {
                // Repli ci-dessous pour les données anciennes ou invalides.
            }
        }

        // Les anciens messages ont ete stockes comme heure murale, sans offset.
        // On reconstruit leur instant historique puis on l'envoie en UTC ; seul
        // le navigateur choisit ensuite le fuseau d'affichage de l'utilisateur.
        $legacyTimezone = new \DateTimeZone(self::LEGACY_STORAGE_TIMEZONE);
        $wallClock = $storedAt->format('Y-m-d H:i:s.u');
        $legacyInstant = \DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s.u',
            $wallClock,
            $legacyTimezone
        );

        if ($legacyInstant instanceof \DateTimeImmutable) {
            return $legacyInstant->setTimezone($utc);
        }

        return \DateTimeImmutable::createFromInterface($storedAt)->setTimezone($utc);
    }
}
