<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WebhookDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'notification_id' => ['required', 'uuid'],
            'status'          => ['required', 'in:delivered,rejected'],
            'reason'          => ['nullable', 'string', 'max:500'],
        ];
    }
}
