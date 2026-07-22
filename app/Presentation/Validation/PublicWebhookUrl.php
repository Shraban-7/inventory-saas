<?php

namespace App\Presentation\Validation;

use App\Application\Services\WebhookDestinationValidator;
use App\Domain\Exceptions\UnsafeWebhookDestinationException;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PublicWebhookUrl implements ValidationRule
{
    public function __construct(
        private readonly WebhookDestinationValidator $destinationValidator,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute must be a valid URL.');

            return;
        }

        try {
            $this->destinationValidator->validate($value, app()->isProduction());
        } catch (UnsafeWebhookDestinationException $exception) {
            $fail($exception->getMessage());
        }
    }
}
