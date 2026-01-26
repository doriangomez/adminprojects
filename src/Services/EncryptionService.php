<?php

declare(strict_types=1);

class EncryptionService
{
    private const CIPHER = 'AES-256-CBC';
    private const PREFIX = 'enc:';

    public function encrypt(string $plainText): string
    {
        if ($plainText === '') {
            return '';
        }

        $key = $this->key();
        $iv = random_bytes(openssl_cipher_iv_length(self::CIPHER));
        $cipherText = openssl_encrypt($plainText, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);

        if ($cipherText === false) {
            return '';
        }

        return self::PREFIX . base64_encode($iv . $cipherText);
    }

    public function decrypt(string $payload): string
    {
        if ($payload === '') {
            return '';
        }

        if (!str_starts_with($payload, self::PREFIX)) {
            return $payload;
        }

        $decoded = base64_decode(substr($payload, strlen(self::PREFIX)), true);
        if ($decoded === false) {
            return '';
        }

        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        $iv = substr($decoded, 0, $ivLength);
        $cipherText = substr($decoded, $ivLength);
        $plainText = openssl_decrypt($cipherText, self::CIPHER, $this->key(), OPENSSL_RAW_DATA, $iv);

        return $plainText === false ? '' : $plainText;
    }

    private function key(): string
    {
        $appKey = getenv('APP_KEY') ?: 'default-app-key';

        return hash('sha256', $appKey, true);
    }
}
