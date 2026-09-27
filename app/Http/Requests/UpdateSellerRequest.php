<?php

namespace App\Http\Requests;

use App\Models\Seller;
use App\Rules\UniqueSellerPhone;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSellerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => mb_strtoupper(trim((string) $this->input('name', ''))),
            'notes' => $this->filled('notes') ? mb_strtoupper(trim((string) $this->input('notes'))) : null,
        ]);
    }

    public function rules(): array
    {
        $seller = $this->route('seller');
        $ignoreId = $seller instanceof Seller ? $seller->id : (is_numeric($seller) ? (int) $seller : null);

        return [
            'name'  => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:32', new UniqueSellerPhone($ignoreId)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
