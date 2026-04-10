<?php

namespace App\Http\Requests\Api;

use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWhatsappLogsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof Student;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'device_id' => ['required', 'string', 'max:128'],
            'consent_acknowledged' => ['required', Rule::in([true, 1, '1', 'true', 'on'])],
            'consent_version' => ['nullable', 'string', 'max:32'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.client_record_id' => ['required', 'string', 'max:64'],
            'items.*.source_app' => ['nullable', Rule::in(['whatsapp', 'whatsapp_business'])],
            'items.*.sender_hint' => ['nullable', 'string', 'max:120'],
            'items.*.body_preview' => ['required', 'string', 'max:4000'],
            'items.*.occurred_at' => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'consent_acknowledged.required' => 'User consent is required before logs can be uploaded.',
            'consent_acknowledged.in' => 'User consent must be explicitly acknowledged.',
        ];
    }
}
