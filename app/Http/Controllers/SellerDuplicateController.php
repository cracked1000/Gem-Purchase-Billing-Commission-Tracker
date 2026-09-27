<?php

namespace App\Http\Controllers;

use App\Services\BackupService;
use App\Services\SellerDuplicateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SellerDuplicateController extends Controller
{
    public function __construct(
        private SellerDuplicateService $duplicates,
        private BackupService $backups,
    ) {
    }

    /** Panel listing every phone number that is shared by 2+ seller profiles. */
    public function index(): View
    {
        $groups = $this->duplicates->scan();

        $profileCount = collect($groups)->sum(fn (array $group) => count($group['sellers']));
        $billCount = collect($groups)->sum(fn (array $group) => $group['bill_count']);

        return view('sellers.duplicates', compact('groups', 'profileCount', 'billCount'));
    }

    /** Merge the chosen profiles into the primary — archives, never deletes. */
    public function merge(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'primary_id' => ['required', 'integer'],
            'all_ids' => ['required', 'array', 'min:2'],
            'all_ids.*' => ['integer', 'distinct'],
        ]);

        $primaryId = (int) $data['primary_id'];
        $allIds = array_map('intval', $data['all_ids']);

        if (! in_array($primaryId, $allIds, true)) {
            return redirect()->route('sellers.duplicates')
                ->with('error', 'The primary profile must be one of the profiles in the group.');
        }

        $duplicateIds = array_values(array_diff($allIds, [$primaryId]));

        try {
            $this->backups->snapshot('before-seller-merge');
            $result = $this->duplicates->merge($primaryId, $duplicateIds);
            $this->backups->snapshot('after-seller-merge');
        } catch (\Throwable $e) {
            return redirect()->route('sellers.duplicates')
                ->with('error', $e->getMessage());
        }

        return redirect()->route('sellers.duplicates')
            ->with('success', sprintf(
                'Merged %d profile(s) into “%s”. %d bill(s) now sit under that profile. The merged profiles were archived — nothing was deleted.',
                $result['archived'],
                $result['primary_name'],
                $result['moved']
            ));
    }
}
