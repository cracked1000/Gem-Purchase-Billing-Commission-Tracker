<?php

namespace App\Http\Controllers;

use App\Models\InvoiceItem;
use App\Services\GemExportService;
use App\Services\GemExportXlsx;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GemExportController extends Controller
{
    public function __construct(private GemExportService $export)
    {
    }

    public function index(Request $request): View
    {
        $mode = $this->mode($request);
        $date = $request->query('date');
        $layout = $this->layout($request);
        $data = $this->export->data($mode, $date, $layout);

        return view('export.index', $data);
    }

    /** The export control panel: every gem with an assign dropdown. */
    public function registry(Request $request): View
    {
        $data = $this->export->registry();

        $filter = (string) $request->query('category', '');
        if ($filter !== '' && array_key_exists($filter, GemExportService::BUCKETS)) {
            $data['rows'] = $data['rows']->where('category_key', $filter)->values();
        }
        $data['filter'] = $filter;

        // Serial integrity check (optional "check up to ___" number).
        $checkTop = $request->query('checktop');
        $checkTopInt = ($checkTop !== null && $checkTop !== '' && is_numeric($checkTop))
            ? (int) $checkTop
            : null;
        $data['check'] = $this->export->serialCheck($checkTopInt);
        $data['checktop'] = $checkTopInt;

        return view('export.registry', $data);
    }

    public function pdf(Request $request): Response
    {
        $mode = $this->mode($request);
        $date = $request->query('date');
        $layout = $this->layout($request);
        $category = (string) $request->query('category', '');
        $data = $this->export->data($mode, $date, $layout);

        // Per-category download: keep only the requested section.
        if ($category !== '' && array_key_exists($category, GemExportService::BUCKETS)) {
            $data['sections'] = $data['sections']->filter(fn ($s) => $s->key === $category)->values();
            $data['category'] = $category;
            $data['category_title'] = GemExportService::BUCKETS[$category]['title'];
        }

        $this->raiseMemory();
        $copy = $mode === 'daily' ? 'by-day' : ($mode === 'day' ? ($data['date'] ?? 'day') : 'all');
        $name = $mode === 'day'
            ? $this->safeDownloadFilename('gem-export-'.($data['date'] ?? 'day')).'.pdf'
            : $this->safeDownloadFilename('gem-export-'.$copy.'-'.now()->format('Ymd-His')).'.pdf';
        if ($category !== '') {
            // Titles like "Tourmaline / Kornarupine" cannot go into a download
            // filename — Symfony forbids "/" and "\" in Content-Disposition.
            $title = GemExportService::BUCKETS[$category]['title'] ?? $category;
            $name = $this->safeDownloadFilename('gem-export-'.$title).'.pdf';
        }

        return Pdf::loadView('export.pdf', $data)
            ->setPaper('a4', 'portrait')
            ->download($name);
    }

    public function excel(Request $request, GemExportXlsx $xlsx): BinaryFileResponse
    {
        $mode = $this->mode($request);
        $date = $request->query('date');
        $layout = $this->layout($request);
        $data = $this->export->data($mode, $date, $layout);
        $path = $xlsx->create($data);

        $copy = $mode === 'daily' ? 'by-day' : ($mode === 'day' ? ($data['date'] ?? 'day') : 'all');
        $name = $mode === 'day'
            ? $this->safeDownloadFilename('gem-export-'.($data['date'] ?? 'day')).'.xlsx'
            : $this->safeDownloadFilename('gem-export-'.$copy.'-'.now()->format('Ymd-His')).'.xlsx';

        return response()->download(
            $path,
            $name,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }

    /** Assign a gem to a category from the review panel (export-only override). */
    public function assign(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'item_id'  => ['required', 'integer'],
            'category' => ['required', 'string', 'max:40'],
        ]);

        $item = InvoiceItem::findOrFail((int) $data['item_id']);

        $category = $data['category'];
        if ($category === 'auto') {
            $item->export_category = null;
            $item->save();
            return back()->with('success', 'Returned to automatic categorization.');
        }

        if (! array_key_exists($category, GemExportService::BUCKETS)) {
            return back()->with('error', 'That is not a valid export category.');
        }

        $item->export_category = $category;
        $item->save();

        return back()->with('success', 'Assigned to '.GemExportService::BUCKETS[$category]['title'].'.');
    }

    private function mode(Request $request): string
    {
        $mode = (string) $request->query('mode', 'all');
        return in_array($mode, ['all', 'day', 'daily'], true) ? $mode : 'all';
    }

    private function layout(Request $request): string
    {
        $layout = (string) $request->query('layout', 'category');
        return $layout === 'serial' ? 'serial' : 'category';
    }

    /**
     * Content-Disposition filenames cannot contain "/" or "\". Category
     * titles such as "Tourmaline / Kornarupine" (and emoji labels) must be
     * turned into a plain ASCII slug before download.
     */
    private function safeDownloadFilename(string $basename): string
    {
        $basename = str_replace(['/', '\\'], '-', $basename);
        $basename = preg_replace('/[^\x20-\x7E]/', '', $basename) ?? '';
        $basename = preg_replace('/[^A-Za-z0-9._-]+/', '-', $basename) ?? '';
        $basename = preg_replace('/-+/', '-', $basename) ?? '';
        $basename = trim($basename, '.-_');

        return $basename !== '' ? $basename : 'gem-export';
    }

    private function raiseMemory(): void
    {
        $current = $this->memoryLimitBytes();
        if ($current >= 0 && $current < 1024 * 1024 * 1024) {
            @ini_set('memory_limit', '1024M');
        }
        @set_time_limit(0);
    }

    private function memoryLimitBytes(): int
    {
        $raw = (string) ini_get('memory_limit');
        if ($raw === '' || $raw === '-1') {
            return -1;
        }
        $value = (int) $raw;
        switch (strtoupper(substr($raw, -1))) {
            case 'G': $value *= 1024; // no break
            case 'M': $value *= 1024; // no break
            case 'K': $value *= 1024; // no break
        }
        return $value;
    }
}
