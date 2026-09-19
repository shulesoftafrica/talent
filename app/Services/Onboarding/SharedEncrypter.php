<?php

namespace App\Services\Onboarding;

use Illuminate\Encryption\Encrypter;

/**
 * Encrypts sensitive onboarding values (identity number, TIN, pension
 * number) with a dedicated key shared with the HR app (shulesoft_newversion),
 * because each app's own APP_KEY differs and both must decrypt the same data.
 *
 * The cipher and payload format are fixed (AES-256-CBC, JSON via
 * encryptString) and MUST stay identical to the HR app's class of the same
 * name: a fixed ciphertext produced there is decrypted in this app's tests.
 * A wrong key throws DecryptException -- it never yields garbage.
 */
class SharedEncrypter
{
    private Encrypter $encrypter;

    public function __construct(string $key, string $cipher = 'AES-256-CBC')
    {
        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7), true) ?: '';
        }

        if (! Encrypter::supported($key, $cipher)) {
            throw new \RuntimeException('HR_ONBOARDING_ENCRYPTION_KEY must be a base64 key of the correct length for '.$cipher.'.');
        }

        $this->encrypter = new Encrypter($key, $cipher);
    }

    public static function fromConfig(): self
    {
        $key = (string) config('hr_onboarding.encryption_key');
        if ($key === '') {
            throw new \RuntimeException('HR_ONBOARDING_ENCRYPTION_KEY is not set.');
        }

        return new self($key, (string) config('hr_onboarding.cipher', 'AES-256-CBC'));
    }

    public function encryptString(string $plain): string
    {
        return $this->encrypter->encryptString($plain);
    }

    public function decryptString(string $cipherText): string
    {
        return $this->encrypter->decryptString($cipherText);
    }

    public function encryptJson(array $data): string
    {
        return $this->encryptString(json_encode($data, JSON_THROW_ON_ERROR));
    }

    public function decryptJson(string $cipherText): array
    {
        return json_decode($this->decryptString($cipherText), true, 512, JSON_THROW_ON_ERROR);
    }
}
