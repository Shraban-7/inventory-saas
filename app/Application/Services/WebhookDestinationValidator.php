<?php

namespace App\Application\Services;

use App\Application\Contracts\DnsResolver;
use App\Application\DTOs\ResolvedWebhookDestination;
use App\Domain\Exceptions\UnsafeWebhookDestinationException;
use Illuminate\Support\Str;
use ValueError;

class WebhookDestinationValidator
{
    /** @var list<string> */
    private const METADATA_HOSTS = [
        'metadata.google.internal',
        'metadata.aws.internal',
        'metadata.azure.internal',
        'instance-data.ec2.internal',
    ];

    /** @var list<string> */
    private const METADATA_ADDRESSES = [
        '169.254.169.254',
        '169.254.170.2',
        '100.100.100.200',
        '168.63.129.16',
        'fd00:ec2::254',
    ];

    public function __construct(private readonly DnsResolver $dnsResolver) {}

    public function validate(string $url, bool $requireHttps): ResolvedWebhookDestination
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new UnsafeWebhookDestinationException('The URL is invalid.');
        }

        try {
            $parts = parse_url($url);
        } catch (ValueError) {
            throw new UnsafeWebhookDestinationException('The URL is invalid.');
        }

        if (! is_array($parts)) {
            throw new UnsafeWebhookDestinationException('The URL is invalid.');
        }

        $scheme = Str::lower((string) ($parts['scheme'] ?? ''));
        $host = Str::lower(trim((string) ($parts['host'] ?? ''), '[]'));

        if (! in_array($scheme, ['http', 'https'], true)
            || ($requireHttps && $scheme !== 'https')
            || $host === ''
            || isset($parts['user'])
            || isset($parts['pass'])) {
            throw new UnsafeWebhookDestinationException(
                'The URL must use an allowed public destination.',
            );
        }

        if ($host === 'localhost'
            || Str::endsWith($host, ['.localhost', '.local', '.internal'])
            || in_array($host, self::METADATA_HOSTS, true)) {
            throw new UnsafeWebhookDestinationException(
                'The URL host is not a public destination.',
            );
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP) !== false
            ? [$host]
            : $this->dnsResolver->resolve($host);

        if ($addresses === []
            || collect($addresses)->contains(
                fn (string $address): bool => ! $this->isPublicAddress($address),
            )) {
            throw new UnsafeWebhookDestinationException(
                'The URL must resolve only to public addresses.',
            );
        }

        $port = isset($parts['port'])
            ? (int) $parts['port']
            : ($scheme === 'https' ? 443 : 80);

        return new ResolvedWebhookDestination(
            $url,
            $host,
            $port,
            $addresses[0],
        );
    }

    private function isPublicAddress(string $address): bool
    {
        if (in_array(Str::lower($address), self::METADATA_ADDRESSES, true)) {
            return false;
        }

        if (Str::startsWith(Str::lower($address), '::ffff:')) {
            $address = Str::afterLast($address, ':');
        }

        $packedAddress = inet_pton($address);

        if ($packedAddress === false
            || (strlen($packedAddress) === 4 && ord($packedAddress[0]) >= 224)
            || (strlen($packedAddress) === 16 && ord($packedAddress[0]) === 255)) {
            return false;
        }

        return filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE
                | FILTER_FLAG_NO_RES_RANGE
                | FILTER_FLAG_GLOBAL_RANGE,
        ) !== false;
    }
}
