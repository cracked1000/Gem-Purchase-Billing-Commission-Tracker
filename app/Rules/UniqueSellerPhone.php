<?php

namespace App\Rules;

use App\Models\Seller;
use App\Support\WaNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A seller's phone number must be unique across all active profiles.
 *
 * Comparison is done on normalised digits only, so these are treated as
 * the same number and blocked as duplicates:
 *   "077 123 4567", "0771234567", "+94 77 123 4567"
 *
 * Archived (merged) profiles are ignored, so a number that only belongs to
 * archived profiles can be used again.
 */
class UniqueSellerPhone implements ValidationRule
{
    public function __construct(private ?int $ignoreId = null)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $key = WaNumber::normalize((string) $value);

        // No usable digits — nothing to clash with.
        if ($key === null) {
            return;
        }

        $existing = Seller::query()
            ->whereNotNull('phone')
            ->when($this->ignoreId !== null, fn ($query) => $query->where('id', '!=', $this->ignoreId))
            ->get(['id', 'name', 'phone']);

        foreach ($existing as $seller) {
            if (WaNumber::normalize((string) $seller->phone) === $key) {
                $fail('A seller with this phone number already exists — every seller keeps one unique number. Use that profile instead of creating a duplicate, or open the Duplicates panel to merge profiles.');
                return;
            }
        }
    }
}
