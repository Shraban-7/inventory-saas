<?php

namespace App\Presentation\Requests;

use App\Application\Services\BranchAuthorizationService;
use App\Application\Services\WebhookDestinationValidator;
use App\Domain\Entities\WebhookEvent;
use App\Infrastructure\Models\User;
use App\Presentation\Validation\PublicWebhookUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreWebhookEndpointRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User
            && app(BranchAuthorizationService::class)->hasTenantWideRole($user, 'Admin');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'url' => [
                'required',
                'string',
                'max:2048',
                new PublicWebhookUrl(app(WebhookDestinationValidator::class)),
            ],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['required', 'distinct', new Enum(WebhookEvent::class)],
        ];
    }
}
