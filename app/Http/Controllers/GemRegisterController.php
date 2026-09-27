<?php

namespace App\Http\Controllers;

use App\Services\GemRegisterService;
use App\Services\SimpleXlsx;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GemRegisterController extends Controller
{
    public function __construct(private GemRegisterService $register)
    {
    }

    public function index(Request $request): View
    {
        $selected = $this->selectedColumns($request);
        $data = $this->register->data($selected);
        $available = $this->register->availableColumns();

        return view('gems.index', [...$data, 'available' => $available, 'selected' => $selected]);
    }

    public function pdf(Request $request): Response
    {
        $selected = $this->selectedColumns($request);
        $data = $this->register->data($selected);

        return Pdf::loadView('gems.pdf', $data)
            ->setPaper('a4', 'landscape')
            ->download('gem-register-'.now()->format('Ymd-His').'.pdf');
    }

    public function excel(Request $request, SimpleXlsx $xlsx): BinaryFileResponse
    {
        $selected = $this->selectedColumns($request);
        $data = $this->register->data($selected);
        $path = $xlsx->create($data['columns'], $data['rows'], $data['totals']);

        return response()->download(
            $path,
            'gem-register-'.now()->format('Ymd-His').'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }

    private function selectedColumns(Request $request): array
    {
        $available = implode(',', array_keys($this->register->availableColumns()));
        $validated = $request->validate([
            'columns' => ['nullable', 'array'],
            'columns.*' => ['string', 'in:'.$available],
        ]);

        return $this->register->normalizeColumns($validated['columns'] ?? null);
    }
}
