<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Seller;
use App\Support\WaNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Finds seller profiles that share the same phone number and merges them
 * manually. Merging is non-destructive:
 *
 *  - every bill (including archived ones) is re-pointed at the primary profile,
 *  - the duplicate profiles are ARCHIVED (soft-deleted) — never erased,
 *  - receipt PDFs for the moved bills are regenerated with the primary
 *    profile's name/number,
 *  - backup snapshots are taken before and after by the controller.
 */
class SellerDuplicateService
{
    /** Digits-only identity for a phone number (null when unusable). */
    public function phoneKey(?string $phone): ?string
    {
        return WaNumber::normalize($phone);
    }

    /**
     * Scan the entire database for active profiles sharing a phone number.
     *
     * @return array<int, array{key: string, display_phone: string, sellers: list<Seller>, primary: Seller, bill_count: int, total_sold: float}>
     */
    public function scan(): array
    {
        $sellers = Seller::query()
            ->whereNotNull('phone')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $byKey = [];

        foreach ($sellers as $seller) {
            $key = $this->phoneKey($seller->phone);

            if ($key === null) {
                continue;
            }

            $byKey[$key]['key'] = $key;
            $byKey[$key]['display_phone'] ??= $seller->phone;
            $byKey[$key]['sellers'][] = $seller;
        }

        $groups = [];

        foreach ($byKey as $key => $group) {
            if (count($group['sellers']) < 2) {
                continue;
            }

            $ids = collect($group['sellers'])->pluck('id')->all();

            // Per-profile stats so the panel can suggest a sensible primary.
            foreach ($group['sellers'] as $seller) {
                $seller->bill_count = (int) Invoice::where('seller_id', $seller->id)->count();
                $seller->total_sold = (float) Invoice::where('seller_id', $seller->id)->sum('subtotal');
            }

            // Suggested primary: most bills, ties broken by oldest profile.
            $group['sellers'] = collect($group['sellers'])
                ->sortBy(fn (Seller $seller) => (int) $seller->created_at?->timestamp) // oldest first
                ->sortByDesc('bill_count')                                            // most bills first
                ->values()
                ->all();

            $group['primary'] = $group['sellers'][0];
            $group['bill_count'] = (int) Invoice::whereIn('seller_id', $ids)->count();
            $group['total_sold'] = (float) Invoice::whereIn('seller_id', $ids)->sum('subtotal');

            $groups[] = $group;
        }

        return $groups;
    }

    /**
     * Merge $duplicateIds into $primaryId. Throws RuntimeException when the
     * requested merge is unsafe; never deletes anything on any code path.
     *
     * @param  list<int>  $duplicateIds
     * @return array{archived: int, moved: int, primary_name: string}
     */
    public function merge(int $primaryId, array $duplicateIds): array
    {
        $primary = Seller::findOrFail($primaryId);
        $primaryKey = $this->phoneKey($primary->phone);

        if ($primaryKey === null) {
            throw new RuntimeException('The primary profile must have a phone number before profiles can be merged by number.');
        }

        $dupes = Seller::query()
            ->whereIn('id', $duplicateIds)
            ->where('id', '!=', $primaryId)
            ->get();

        if ($dupes->isEmpty()) {
            throw new RuntimeException('Choose at least one other profile to merge into the primary.');
        }

        foreach ($dupes as $dupe) {
            if ($this->phoneKey($dupe->phone) !== $primaryKey) {
                throw new RuntimeException(sprintf('“%s” has a different phone number and cannot be merged into “%s”.', $dupe->name, $primary->name));
            }
        }

        $dupeIds = $dupes->pluck('id')->all();

        // Snapshot the bills so their receipt PDFs can be regenerated under
        // the primary profile (archived bills included, so a restored bill
        // already carries the right profile).
        $invoices = Invoice::withTrashed()
            ->with('items')
            ->whereIn('seller_id', $dupeIds)
            ->get();

        $oldFilenames = $invoices->mapWithKeys(
            fn (Invoice $invoice) => [$invoice->id => ReceiptStorage::filenameFor($invoice)]
        );

        $movedActive = (int) $invoices->where('deleted_at', null)->count();

        DB::transaction(function () use ($primaryId, $primary, $dupeIds, $invoices, $oldFilenames) {
            Invoice::withTrashed()
                ->whereIn('seller_id', $dupeIds)
                ->update(['seller_id' => $primaryId]);

            // Archive the merged profiles — soft delete only, nothing is erased.
            Seller::whereIn('id', $dupeIds)->delete();

            foreach ($invoices as $invoice) {
                $invoice->setRelation('seller', $primary);

                try {
                    $result = ReceiptStorage::save($invoice);
                    $old = $oldFilenames->get($invoice->id);

                    if ($result && $old && $old !== $result['filename']) {
                        ReceiptStorage::deleteFilename($old);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Could not refresh receipt PDF after seller merge', [
                        'invoice' => $invoice->invoice_number,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

        return [
            'archived' => $dupes->count(),
            'moved' => $movedActive,
            'primary_name' => $primary->name,
        ];
    }
}
