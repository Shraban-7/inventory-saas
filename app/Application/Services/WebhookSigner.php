<?php

namespace App\Application\Services;

use Illuminate\Support\Str;

class WebhookSigner
{
    public function sign(string $canonicalPayload, string $secret): string
    {
        return hash_hmac('sha256', $canonicalPayload, $secret);
    }

    public function verify(string $canonicalPayload, string $signature, string $secret): bool
    {
        $expected = $this->sign($canonicalPayload, $secret);
        $provided = Str::startsWith(Str::lower($signature), 'sha256=')
            ? Str::after($signature, '=')
            : $signature;

        return hash_equals($expected, $provided);
    }
}
