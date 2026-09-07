<?php

namespace App\Service;

/**
 * Chiffrement authentifié prêt pour les nouveaux stockages documentaires.
 * Il est volontairement sans effet tant que MOL_DOCUMENT_ENCRYPTION_KEY
 * n'est pas configurée, afin de préserver les documents historiques.
 */
final class SensitiveDocumentCipher
{
    private readonly string $key;

    public function __construct()
    {
        $this->key = (string) ($_ENV['MOL_DOCUMENT_ENCRYPTION_KEY'] ?? $_SERVER['MOL_DOCUMENT_ENCRYPTION_KEY'] ?? '');
    }

    public function isEnabled(): bool
    {
        return function_exists('sodium_crypto_secretbox') && $this->decodedKey() !== null;
    }

    public function encrypt(string $plain): string
    {
        $key = $this->decodedKey();
        if ($key === null) throw new \RuntimeException('La clé de chiffrement documentaire n’est pas configurée.');
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return 'MOL1'.$nonce.sodium_crypto_secretbox($plain, $nonce, $key);
    }

    public function decrypt(string $encrypted): string
    {
        $key = $this->decodedKey();
        if ($key === null || !str_starts_with($encrypted, 'MOL1')) throw new \RuntimeException('Document chiffré invalide.');
        $nonce = substr($encrypted, 4, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = sodium_crypto_secretbox_open(substr($encrypted, 4 + SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, $key);
        if ($plain === false) throw new \RuntimeException('Impossible de déchiffrer le document.');
        return $plain;
    }

    private function decodedKey(): ?string
    {
        if ($this->key === '') return null;
        $decoded = base64_decode($this->key, true);
        return is_string($decoded) && strlen($decoded) === SODIUM_CRYPTO_SECRETBOX_KEYBYTES ? $decoded : null;
    }
}
