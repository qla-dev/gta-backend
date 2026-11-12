<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class AppleIdentity
{
    private const JWKS_URL = 'https://appleid.apple.com/auth/keys';
    private const ISSUER = 'https://appleid.apple.com';

    public function verify(string $identityToken, string $nonce, array $audiences): array
    {
        $parts = explode('.', $identityToken);
        if (count($parts) !== 3) {
            throw new \InvalidArgumentException('Malformed identity token.');
        }

        $header = json_decode(base64_decode(strtr($parts[0], '-_', '+/')), true);
        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);

        if (! $header || ! $payload) {
            throw new \InvalidArgumentException('Cannot decode token.');
        }

        if (($payload['iss'] ?? '') !== self::ISSUER) {
            throw new \RuntimeException('Invalid issuer.');
        }

        if (! in_array($payload['aud'] ?? '', $audiences, true)) {
            throw new \RuntimeException('Invalid audience.');
        }

        if (($payload['exp'] ?? 0) < time()) {
            throw new \RuntimeException('Token expired.');
        }

        if (! hash_equals($nonce, $payload['nonce'] ?? '')) {
            throw new \RuntimeException('Nonce mismatch.');
        }

        $this->verifySignature($identityToken, $header['kid'] ?? '');

        return $payload;
    }

    private function verifySignature(string $token, string $kid): void
    {
        $keys = Cache::remember('apple_jwks', 3600, function () {
            $response = Http::get(self::JWKS_URL);
            return $response->json('keys', []);
        });

        $key = collect($keys)->firstWhere('kid', $kid);
        if (! $key) {
            Cache::forget('apple_jwks');
            throw new \RuntimeException('Unknown signing key.');
        }

        $pem = $this->jwkToPem($key);

        $segments = explode('.', $token);
        $data = $segments[0].'.'.$segments[1];
        $signature = base64_decode(strtr($segments[2], '-_', '+/'));

        $pubKey = openssl_pkey_get_public($pem);
        if (! $pubKey) {
            throw new \RuntimeException('Invalid public key.');
        }

        $alg = match ($key['alg'] ?? 'RS256') {
            'RS256' => OPENSSL_ALGO_SHA256,
            'ES256' => OPENSSL_ALGO_SHA256,
            default => OPENSSL_ALGO_SHA256,
        };

        if (($key['kty'] ?? '') === 'EC') {
            $signature = $this->derEncode($signature);
        }

        if (openssl_verify($data, $signature, $pubKey, $alg) !== 1) {
            throw new \RuntimeException('Signature verification failed.');
        }
    }

    private function jwkToPem(array $key): string
    {
        if (($key['kty'] ?? '') === 'RSA') {
            $n = base64_decode(strtr($key['n'], '-_', '+/'));
            $e = base64_decode(strtr($key['e'], '-_', '+/'));
            $der = $this->rsaDer($n, $e);
            return "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END PUBLIC KEY-----\n";
        }

        if (($key['kty'] ?? '') === 'EC') {
            $x = base64_decode(strtr($key['x'], '-_', '+/'));
            $y = base64_decode(strtr($key['y'], '-_', '+/'));
            $der = $this->ecDer($x, $y);
            return "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END PUBLIC KEY-----\n";
        }

        throw new \RuntimeException('Unsupported key type.');
    }

    private function rsaDer(string $n, string $e): string
    {
        $n = ltrim($n, "\x00");
        if (ord($n[0]) > 0x7f) {
            $n = "\x00".$n;
        }
        $e = ltrim($e, "\x00");
        if (ord($e[0]) > 0x7f) {
            $e = "\x00".$e;
        }

        $nEnc = "\x02".$this->derLength(strlen($n)).$n;
        $eEnc = "\x02".$this->derLength(strlen($e)).$e;
        $seq = "\x30".$this->derLength(strlen($nEnc.$eEnc)).$nEnc.$eEnc;
        $bitString = "\x03".$this->derLength(strlen($seq) + 1)."\x00".$seq;

        $oid = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00";

        return "\x30".$this->derLength(strlen($oid.$bitString)).$oid.$bitString;
    }

    private function ecDer(string $x, string $y): string
    {
        $x = str_pad($x, 32, "\x00", STR_PAD_LEFT);
        $y = str_pad($y, 32, "\x00", STR_PAD_LEFT);
        $point = "\x04".$x.$y;
        $bitString = "\x03".$this->derLength(strlen($point) + 1)."\x00".$point;

        $oid = "\x30\x13\x06\x07\x2a\x86\x48\xce\x3d\x02\x01\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07";

        return "\x30".$this->derLength(strlen($oid.$bitString)).$oid.$bitString;
    }

    private function derEncode(string $raw): string
    {
        if (strlen($raw) !== 64) {
            return $raw;
        }

        $r = ltrim(substr($raw, 0, 32), "\x00");
        $s = ltrim(substr($raw, 32, 32), "\x00");
        if ($r === '') $r = "\x00";
        if ($s === '') $s = "\x00";
        if (ord($r[0]) > 0x7f) $r = "\x00".$r;
        if (ord($s[0]) > 0x7f) $s = "\x00".$s;

        $rEnc = "\x02".chr(strlen($r)).$r;
        $sEnc = "\x02".chr(strlen($s)).$s;

        return "\x30".chr(strlen($rEnc.$sEnc)).$rEnc.$sEnc;
    }

    private function derLength(int $length): string
    {
        if ($length < 0x80) {
            return chr($length);
        }
        $bytes = ltrim(pack('N', $length), "\x00");
        return chr(0x80 | strlen($bytes)).$bytes;
    }
}
