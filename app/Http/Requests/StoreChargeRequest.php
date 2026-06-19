<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreChargeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'order_id' => ['required', 'string', 'max:100'],
            'gross_amount' => ['required', 'integer', 'min:1'],
            'currency' => ['nullable', 'string', 'size:3'],
            'customer_details' => ['required', 'array'],
            'customer_details.first_name' => ['required', 'string', 'max:100'],
            'customer_details.last_name' => ['nullable', 'string', 'max:100'],
            'customer_details.email' => ['nullable', 'email', 'max:150'],
            'customer_details.phone' => ['nullable', 'string', 'max:30'],
            'item_details' => ['nullable', 'array'],
            'item_details.*.id' => ['required_with:item_details', 'string', 'max:100'],
            'item_details.*.price' => ['required_with:item_details', 'integer', 'min:1'],
            'item_details.*.quantity' => ['required_with:item_details', 'integer', 'min:1'],
            'item_details.*.name' => ['required_with:item_details', 'string', 'max:191'],
            'custom_callback_url' => ['nullable', 'url', 'max:2048'],
            'metadata' => ['nullable', 'array'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('currency')) {
            $this->merge([
                'currency' => strtoupper((string) $this->input('currency')),
            ]);
        }
    }
}
