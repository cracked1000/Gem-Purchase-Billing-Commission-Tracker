<?php

namespace App\Http\Controllers;

use App\Services\WipeMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(WipeMode $wipe): View
    {
        return view('settings.index', ['enabled' => $wipe->enabled()]);
    }

    public function update(Request $request, WipeMode $wipe): RedirectResponse
    {
        $validated = $request->validate(['wipe' => ['nullable', 'boolean']]);
        $wipe->set((bool) ($validated['wipe'] ?? false));

        return back()->with('success', 'Wipe setting updated.');
    }
}
