<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'seller_id'          => ['required', 'exists:sellers,id'],
            'sold_at'            => ['required', 'date'],
            'commission_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'status'             => ['nullable', 'in:pending,paid'],
            'notes'              => ['nullable', 'string', 'max:1000'],
            'items'              => ['required', 'array', 'min:1'],
            'items.*.serial_number' => ['required', 'string', 'max:100'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.verification_actions' => ['nullable', 'array'],
            'items.*.verification_actions.*' => ['in:recheck,repolish,recut'],
            'items.*.stone_notes' => ['nullable', 'string', 'max:1000'],
            'items.*.carats'     => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'items.*.pieces'     => ['required', 'integer', 'min:1', 'max:1000000'],
            'items.*.price_mode' => ['nullable', 'in:rate,total'],
            'items.*.unit_price' => ['nullable', 'required_without:items.*.total_price', 'numeric', 'min:0', 'max:999999999999'],
            'items.*.total_price' => ['nullable', 'required_without:items.*.unit_price', 'numeric', 'min:0', 'max:999999999999'], 
        ];
    }

    protected function prepareForValidation(): void
    {
        $items = collect($this->input('items', []))->map(function ($item) {
            if (isset($item['serial_number'])) {
                $item['serial_number'] = mb_strtoupper(trim((string) $item['serial_number']));
            }
            if (isset($item['description'])) {
                $item['description'] = mb_strtoupper(trim((string) $item['description']));
            }
            if (isset($item['stone_notes'])) {
                $item['stone_notes'] = mb_strtoupper(trim((string) $item['stone_notes']));
            }

            return $item;
        })->all();

        $this->merge([
            'notes' => $this->filled('notes') ? mb_strtoupper(trim((string) $this->input('notes'))) : null,
            'items' => $items,
        ]);
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Add at least one gem to this bill.',
            'items.min'      => 'Add at least one gem to this bill.',
            'items.*.serial_number.required' => 'Every gem row needs a serial number.',
            'items.*.description.required' => 'Every gem row needs a description (e.g. "Blue Sapphire 2.5ct").',
            'items.*.pieces.required' => 'Every gem row needs a piece count.',
            'items.*.unit_price.required_without' => 'Every gem row needs either a rate or a total price.',
            'items.*.total_price.required_without' => 'Every gem row needs either a rate or a total price.',
        ];
    }
}
