<?php

namespace App;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use JsonException;
use RuntimeException;

class GoogleIdTokenVerifier
{
    /** @return array{email: string, sub: string} */
    public function verify(string $idToken, string $expectedNonce): array
    {
        if (strlen($idToken) > 16384 || count($parts = explode('.', $idToken)) !== 3) {
            throw new RuntimeException('Token identitas Google tidak valid.');
        }

        [$headerSegment, $payloadSegment, $signatureSegment] = $parts;
        $header = $this->decodeJsonSegment($headerSegment);
        $claims = $this->decodeJsonSegment($payloadSegment);
        $signature = $this->decodeSegment($signatureSegment);
        $keyId = $header['kid'] ?? null;

        if (($header['alg'] ?? null) !== 'RS256' || ! is_string($keyId) || $keyId === '') {
            throw new RuntimeException('Tanda tangan token Google tidak valid.');
        }

        $certificates = Cache::remember('google-login-signing-certificates', 1800, fn (): array => $this->fetchCertificates());
        if (! isset($certificates[$keyId])) {
            $certificates = $this->fetchCertificates();
            Cache::put('google-login-signing-certificates', $certificates, 1800);
        }

        $certificate = $certificates[$keyId] ?? null;
        if (! is_string($certificate)
            || openssl_verify($headerSegment.'.'.$payloadSegment, $signature, $certificate, OPENSSL_ALGO_SHA256) !== 1) {
            throw new RuntimeException('Tanda tangan token Google tidak valid.');
        }

        $clientId = config('services.google_login.client_id');
        $email = $claims['email'] ?? null;
        $subject = $claims['sub'] ?? null;
        $now = time();

        if (! in_array($claims['iss'] ?? null, ['https://accounts.google.com', 'accounts.google.com'], true)
            || ($claims['aud'] ?? null) !== $clientId
            || (isset($claims['azp']) && $claims['azp'] !== $clientId)
            || ! is_int($claims['exp'] ?? null) || $claims['exp'] <= $now
            || ! is_int($claims['iat'] ?? null) || $claims['iat'] > $now + 300
            || ! is_string($claims['nonce'] ?? null) || ! hash_equals($expectedNonce, $claims['nonce'])
            || ! in_array($claims['email_verified'] ?? null, [true, 'true'], true)
            || ! is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false
            || ! is_string($subject) || $subject === '' || strlen($subject) > 255) {
            throw new RuntimeException('Identitas Google tidak memenuhi syarat login.');
        }

        return ['email' => $email, 'sub' => $subject];
    }

    /** @return array<string, mixed> */
    private function decodeJsonSegment(string $segment): array
    {
        try {
            $value = json_decode($this->decodeSegment($segment), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Token identitas Google tidak valid.', previous: $exception);
        }

        if (! is_array($value)) {
            throw new RuntimeException('Token identitas Google tidak valid.');
        }

        return $value;
    }

    private function decodeSegment(string $segment): string
    {
        if ($segment === '' || ! preg_match('/^[A-Za-z0-9_-]+$/', $segment)) {
            throw new RuntimeException('Token identitas Google tidak valid.');
        }

        $value = base64_decode(strtr($segment, '-_', '+/'), true);
        if ($value === false) {
            throw new RuntimeException('Token identitas Google tidak valid.');
        }

        return $value;
    }

    /** @return array<string, string> */
    private function fetchCertificates(): array
    {
        $response = Http::connectTimeout(5)->timeout(15)
            ->get('https://www.googleapis.com/oauth2/v1/certs');

        if (! $response->successful() || ! is_array($response->json())) {
            throw new RuntimeException('Sertifikat Google tidak dapat diperoleh.');
        }

        return $response->json();
    }
}
