<?php

namespace App\Application\Jobs;

use App\Application\Jobs\Concerns\RestoresTenantContext;
use App\Application\Services\WebhookDestinationValidator;
use App\Application\Services\WebhookSigner;
use App\Domain\Entities\WebhookDeliveryStatus;
use App\Domain\Exceptions\UnsafeWebhookDestinationException;
use App\Infrastructure\Models\WebhookDelivery;
use App\Infrastructure\Models\WebhookEndpoint;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class DeliverWebhookJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable, RestoresTenantContext;

    public const LEASE_SECONDS = 30;

    public int $tries = 5;

    public int $timeout = 15;

    public int $uniqueFor = 60;

    /** @var list<int> */
    public array $backoff = [60, 300, 900, 3600];

    public function __construct(
        public readonly int $tenantId,
        public readonly string $deliveryId,
    ) {
        $this->onQueue('transactions');
    }

    public function handle(
        WebhookSigner $signer,
        WebhookDestinationValidator $destinationValidator,
    ): void {
        $this->withinTenant(function () use ($destinationValidator, $signer): void {
            $claimed = $this->claimDelivery();

            if ($claimed === null) {
                return;
            }

            [$delivery, $attempt] = $claimed;
            $endpoint = $delivery->endpoint;

            if (! $endpoint instanceof WebhookEndpoint || ! $endpoint->is_active) {
                $this->markFailed($delivery, null, 'The webhook endpoint is inactive.');

                return;
            }

            $signature = 'sha256='.$signer->sign(
                $delivery->payload,
                $endpoint->secret,
            );

            try {
                $destination = $destinationValidator->validate(
                    $endpoint->url,
                    app()->isProduction(),
                );
            } catch (UnsafeWebhookDestinationException) {
                $this->markFailed(
                    $delivery,
                    null,
                    'The webhook destination is not a public address.',
                );

                return;
            }

            $curlResolveOption = defined('CURLOPT_RESOLVE')
                ? constant('CURLOPT_RESOLVE')
                : null;
            $curlNoProxyOption = defined('CURLOPT_NOPROXY')
                ? constant('CURLOPT_NOPROXY')
                : null;

            if (! is_int($curlResolveOption) || ! is_int($curlNoProxyOption)) {
                $this->markFailed(
                    $delivery,
                    null,
                    'Secure webhook DNS pinning is unavailable.',
                );

                return;
            }

            try {
                $response = Http::withOptions([
                    'curl' => [
                        $curlResolveOption => [$destination->curlResolveEntry()],
                        $curlNoProxyOption => '*',
                    ],
                ])
                    ->connectTimeout(3)
                    ->timeout(10)
                    ->withoutRedirecting()
                    ->withHeaders([
                        'X-Inventory-Delivery' => (string) $delivery->getKey(),
                        'X-Inventory-Signature' => $signature,
                    ])
                    ->withBody($delivery->payload, 'application/json')
                    ->post($endpoint->url);
            } catch (ConnectionException $exception) {
                $this->retryOrFail($delivery, null, $exception->getMessage(), $attempt);

                return;
            }

            if ($response->successful()) {
                $delivery->forceFill([
                    'status' => WebhookDeliveryStatus::Delivered,
                    'response_code' => $response->status(),
                    'next_retry_at' => null,
                    'error_detail' => null,
                    'delivered_at' => now(),
                ])->save();

                return;
            }

            $detail = 'Webhook endpoint returned HTTP '.$response->status().'.';

            if ($response->status() === 408
                || $response->status() === 429
                || $response->serverError()) {
                $this->retryOrFail($delivery, $response->status(), $detail, $attempt);

                return;
            }

            $this->markFailed($delivery, $response->status(), $detail);
        });
    }

    public function uniqueId(): string
    {
        return "{$this->tenantId}:{$this->deliveryId}";
    }

    public function failed(?Throwable $exception): void
    {
        $this->withinTenant(function () use ($exception): void {
            $delivery = WebhookDelivery::query()->find($this->deliveryId);

            if (! $delivery instanceof WebhookDelivery
                || $delivery->getRawOriginal('status') === WebhookDeliveryStatus::Delivered->value) {
                return;
            }

            $this->markFailed(
                $delivery,
                $delivery->response_code,
                $exception?->getMessage() ?? 'Webhook delivery exhausted its retries.',
            );
        });
    }

    /**
     * @return array{0: WebhookDelivery, 1: int}|null
     */
    private function claimDelivery(): ?array
    {
        return DB::transaction(function (): ?array {
            $delivery = WebhookDelivery::query()
                ->with('endpoint')
                ->whereKey($this->deliveryId)
                ->lockForUpdate()
                ->first();

            if (! $delivery instanceof WebhookDelivery) {
                return null;
            }

            if ($delivery->getRawOriginal('status') !== WebhookDeliveryStatus::Pending->value) {
                return null;
            }

            $nextRetryAt = $delivery->getAttribute('next_retry_at');

            if ($nextRetryAt instanceof DateTimeInterface
                && $nextRetryAt->getTimestamp() > now()->getTimestamp()) {
                return null;
            }

            if ((int) $delivery->attempts >= $this->tries) {
                $this->markFailed(
                    $delivery,
                    $delivery->response_code,
                    $delivery->error_detail ?? 'Webhook delivery exhausted its retries.',
                );

                return null;
            }

            $attempt = (int) $delivery->attempts + 1;
            $delivery->forceFill([
                'attempts' => $attempt,
                'next_retry_at' => now()->addSeconds(self::LEASE_SECONDS),
                'error_detail' => null,
            ])->save();

            return [$delivery, $attempt];
        });
    }

    private function retryOrFail(
        WebhookDelivery $delivery,
        ?int $responseCode,
        string $detail,
        int $attempt,
    ): void {
        if ($attempt >= $this->tries) {
            $this->markFailed($delivery, $responseCode, $detail);

            return;
        }

        $delay = $this->backoff[$attempt - 1];
        $delivery->forceFill([
            'status' => WebhookDeliveryStatus::Pending,
            'response_code' => $responseCode,
            'next_retry_at' => now()->addSeconds($delay),
            'error_detail' => Str::limit($detail, 1000, ''),
        ])->save();
        $this->release($delay);
    }

    private function markFailed(
        WebhookDelivery $delivery,
        ?int $responseCode,
        string $detail,
    ): void {
        $delivery->forceFill([
            'status' => WebhookDeliveryStatus::Failed,
            'response_code' => $responseCode,
            'next_retry_at' => null,
            'error_detail' => Str::limit($detail, 1000, ''),
        ])->save();
    }
}
