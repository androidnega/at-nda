<?php

namespace App\Http\Requests\Api;

use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCallLogsRequest extends FormRequest
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
            'items.*.direction' => ['required', Rule::in(['inbound', 'outbound'])],
            'items.*.call_outcome' => ['nullable', Rule::in(['missed', 'answered', 'rejected', 'failed', 'unknown', 'in_progress'])],
            'items.*.duration_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'items.*.peer_number' => ['nullable', 'string', 'max:48'],
            'items.*.occurred_at' => ['required', 'date'],
            'items.*.ended_at' => ['nullable', 'date'],
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
