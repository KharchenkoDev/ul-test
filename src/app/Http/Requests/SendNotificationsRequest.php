<?php

namespace App\Http\Requests;

use App\Enums\NotificationChannel;
use App\Enums\NotificationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class SendNotificationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'channel'         => ['required', new Enum(NotificationChannel::class)],
            'type'            => ['required', new Enum(NotificationType::class)],
            'message'         => ['required', 'string', 'max:1000'],
            'subscriber_ids'  => ['required', 'array', 'min:1', 'max:1000'],
            'subscriber_ids.*' => ['required', 'string', 'max:255'],
            'idempotency_key' => ['required', 'string', 'max:255'],
        ];
    }
}
