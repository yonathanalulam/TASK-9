<?php

declare(strict_types=1);

namespace App\Service\Governance;

use App\Entity\EncryptionKey;
use App\Repository\EncryptionKeyRepository;
use Doctrine\ORM\EntityManagerInterface;

class EncryptionService
{
    private const string CIPHER = 'aes-256-gcm';
    private const int IV_LENGTH = 16;
    private const int TAG_LENGTH = 16;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EncryptionKeyRepository $encryptionKeyRepository,
        private readonly string $masterKey,
    ) {
    }

    /**
     * Encrypt plaintext using AES-256-GCM.
     *
     * @return array{encryptedValue: string, iv: string, authTag: string, keyId: string}
     */
    public function encrypt(string $plaintext): array
    {
        $key = $this->getActiveKey();
        $dataKey = $this->decryptKeyMaterial($key);

        $iv = random_bytes(self::IV_LENGTH);
        $tag = '';

        $encrypted = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $dataKey,
            \OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH,
        );

        if ($encrypted === false) {
            throw new \RuntimeException('Encryption failed: ' . openssl_error_string());
        }

        return [
            'encryptedValue' => $encrypted,
            'iv' => $iv,
            'authTag' => $tag,
            'keyId' => $key->getId()->toRfc4122(),
        ];
    }

    /**
     * Decrypt ciphertext using AES-256-GCM.
     */
    public function decrypt(string $encrypted, string $iv, string $authTag, EncryptionKey $key): string
    {
        $dataKey = $this->decryptKeyMaterial($key);

        $plaintext = openssl_decrypt(
            $encrypted,
            self::CIPHER,
            $dataKey,
            \OPENSSL_RAW_DATA,
            $iv,
            $authTag,
        );

        if ($plaintext === false) {
            throw new \RuntimeException('Decryption failed: ' . openssl_error_string());
        }

        return $plaintext;
    }

    /**
     * Get the currently active encryption key.
     */
    public function getActiveKey(): EncryptionKey
    {
        $key = $this->encryptionKeyRepository->findOneBy(['status' => 'ACTIVE']);

        if ($key === null) {
            throw new \RuntimeException('No active encryption key found.');
        }

        return $key;
    }

    /**
     * Generate a new data encryption key, encrypted with the master key.
     */
    public function generateEncryptedKeyMaterial(): string
    {
        $dataKey = random_bytes(32); // 256 bits for AES-256
        $iv = random_bytes(self::IV_LENGTH);
        $tag = '';

        $encrypted = openssl_encrypt(
            $dataKey,
            self::CIPHER,
            $this->getMasterKeyBytes(),
            \OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH,
        );

        if ($encrypted === false) {
            throw new \RuntimeException('Key material encryption failed: ' . openssl_error_string());
        }

        // Pack iv + tag + encrypted key together
        return $iv . $tag . $encrypted;
    }

    /**
     * Decrypt the key material using the master key.
     */
    private function decryptKeyMaterial(EncryptionKey $key): string
    {
        $packed = $key->getEncryptedKeyMaterial();

        // Handle binary from database (resource type)
        if (\is_resource($packed)) {
            $packed = stream_get_contents($packed);
        }

        $iv = substr($packed, 0, self::IV_LENGTH);
        $tag = substr($packed, self::IV_LENGTH, self::TAG_LENGTH);
        $encrypted = substr($packed, self::IV_LENGTH + self::TAG_LENGTH);

        $dataKey = openssl_decrypt(
            $encrypted,
            self::CIPHER,
            $this->getMasterKeyBytes(),
            \OPENSSL_RAW_DATA,
            $iv,
            $tag,
        );

        if ($dataKey === false) {
            throw new \RuntimeException('Key material decryption failed: ' . openssl_error_string());
        }

        return $dataKey;
    }

    /**
     * Derive 32-byte master key from the environment variable.
     */
    private function getMasterKeyBytes(): string
    {
        return hash('sha256', $this->masterKey, true);
    }
}
