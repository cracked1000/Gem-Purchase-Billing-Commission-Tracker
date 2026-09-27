<?php

namespace App\Services;

use App\Models\InvoiceItem;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Scans the entire gem registry (every InvoiceItem) and groups each gem into
 * the export buckets the business uses for an export house / buyer.
 *
 * Three view modes:
 *   all   — every gem, grouped by category.
 *   day   — gems bought on one chosen day, grouped by category.
 *   daily — every purchase day as its own block, categories nested inside.
 *
 * Read-only unless the user explicitly assigns a category from the review
 * panel (which stores a small export-only override — the bill is untouched).
 *
 * Blue sapphire rules (only blue gets the 3ct split):
 *   - 1 piece, >3ct      -> Above 3ct
 *   - 1 piece, <=3ct     -> Below 3ct
 *   - 2 pieces           -> Below 3ct (always)
 *   - 3+ pieces          -> Blue Sapphire Lot
 *   - lot/parcel/mixed in name or stone notes -> Blue Sapphire Lot
 */
class GemExportService
{
    /** Ordered display of every export bucket + the catch-all & review buckets. */
    public const BUCKETS = [
        'tsv'                 => ['title' => 'TSV (Tsavorite)'],
        'purple_sapphire'     => ['title' => 'Purple Sapphire'],
        'teal_sapphire'       => ['title' => 'Teal Sapphire'],
        'peach_sapphire'      => ['title' => 'Peach Sapphire'],
        'green_sapphire'      => ['title' => 'Green Sapphire'],
        'yellow_sapphire'     => ['title' => 'Yellow Sapphire'],
        'pink_sapphire'       => ['title' => 'Pink Sapphire'],
        'white_sapphire'      => ['title' => 'White Sapphire'],
        'blue_sapphire_above' => ['title' => 'Blue Sapphire Above 3 ct'],
        'blue_sapphire_below' => ['title' => 'Blue Sapphire Below 3 ct'],
        'sapphire'            => ['title' => 'Sapphire'],
        'white_lot'           => ['title' => 'White Sapphire Lot'],
        'blue_lot'            => ['title' => 'Blue Sapphire Lot'],
        'mix_sapphire_lot'    => ['title' => 'Mix Sapphire Lot'],
        'alexandrite'         => ['title' => 'Alexandrite'],
        'tourmaline'          => ['title' => 'Tourmaline / Kornarupine'],
        'spinel'              => ['title' => 'Spinel'],
        'catseye'             => ['title' => "Cat's Eye"],
        'moonstone_assign'    => ['title' => '🌙 Moonstone — Assign'],
        'moonstone_faceted'   => ['title' => 'Moonstone Faceted'],
        'moonstone_nonfac'    => ['title' => 'Moonstone Non-Faceted'],
        'padparadscha'        => ['title' => 'Padparadscha (PR)'],
        'star_sapphire'       => ['title' => 'Star Sapphire'],
        'semi'                => ['title' => 'Semi-Precious'],
        'review'              => ['title' => '⚠️ Needs Review'],
    ];

    /**
     * Which categories a section's "Assign" dropdown may offer.
     *
     * - The review section offers every bucket (assign a gem anywhere).
     * - The moonstone assign section offers only faceted / non-faceted.
     *
     * @return array<string, string>  bucket key => title
     */
    public static function assignOptionsFor(string $key): array
    {
        if ($key === 'moonstone_assign') {
            return [
                'moonstone_faceted' => self::BUCKETS['moonstone_faceted']['title'],
                'moonstone_nonfac'  => self::BUCKETS['moonstone_nonfac']['title'],
            ];
        }

        return collect(self::BUCKETS)
            ->mapWithKeys(fn (array $bucket, string $k) => [$k => $bucket['title']])
            ->all();
    }

    /**
     * Build the export for a mode.
     *
     * @param  string  $mode  'all' | 'day' | 'daily'
     * @param  string|null  $date  Y-m-d for 'day' mode
     * @param  string  $layout  'category' (grouped sections) | 'serial' (flat, ascending by serial)
     * @return array<string, mixed>
     */
    public function data(string $mode = 'all', ?string $date = null, string $layout = 'category'): array
    {
        $mode = in_array($mode, ['all', 'day', 'daily'], true) ? $mode : 'all';
        $layout = $layout === 'serial' ? 'serial' : 'category';

        $items = InvoiceItem::with(['invoice.seller'])
            ->whereHas('invoice')
            ->get();

        // Resolve a valid date for 'day' mode.
        if ($mode === 'day') {
            $availableDates = $this->availableDates($items);
            if ($date === null || ! $availableDates->has($date)) {
                $date = $availableDates->keys()->last();
            }
        }

        $overall = $this->sectionsFromItems($items);

        $reviewCount = fn (array $d) => (int) $d['sections']['review']->count;

        if ($mode === 'all') {
            return [
                'mode'         => 'all',
                'date'         => null,
                'grand'        => $overall['grand'],
                'sections'     => $overall['sections'],
                'flat'         => $this->flatRows($items),
                'daily'        => collect(),
                'days'         => $this->availableDates($items),
                'buckets'      => self::BUCKETS,
                'layout'       => $layout,
                'review_count' => $reviewCount($overall),
            ];
        }

        if ($mode === 'day') {
            $dayItems = $items->filter(fn (InvoiceItem $item) => (string) $item->invoice->sold_at->format('Y-m-d') === $date);
            $day = $this->sectionsFromItems($dayItems->values());

            return [
                'mode'         => 'day',
                'date'         => $date,
                'grand'        => $day['grand'],
                'sections'     => $day['sections'],
                'flat'         => $this->flatRows($dayItems->values()),
                'daily'        => collect(),
                'days'         => $this->availableDates($items),
                'buckets'      => self::BUCKETS,
                'layout'       => $layout,
                'review_count' => $reviewCount($day),
            ];
        }

        // daily — every purchase day grouped.
        $daily = $items
            ->groupBy(fn (InvoiceItem $item) => (string) $item->invoice->sold_at->format('Y-m-d'))
            ->map(function (Collection $group, string $dayDate) {
                $built = $this->sectionsFromItems($group->values());
                return (object) [
                    'date'     => Carbon::parse($dayDate),
                    'grand'    => $built['grand'],
                    'sections' => $built['sections'],
                    'flat'     => $this->flatRows($group->values()),
                ];
            })
            ->sortKeys()
            ->values();

        $totalReview = $daily->sum(fn (object $day) => (int) $day->sections['review']->count);

        return [
            'mode'         => 'daily',
            'date'         => null,
            'grand'        => $overall['grand'],
            'sections'     => $overall['sections'],
            'flat'         => $this->flatRows($items),
            'daily'        => $daily,
            'days'         => $this->availableDates($items),
            'buckets'      => self::BUCKETS,
            'layout'       => $layout,
            'review_count' => $totalReview,
        ];
    }

    /** List of every purchase day that has gems, newest first. */
    private function availableDates(Collection $items): Collection
    {
        return $items
            ->groupBy(fn (InvoiceItem $item) => (string) $item->invoice->sold_at->format('Y-m-d'))
            ->map(fn (Collection $group) => (object) [
                'date'   => (string) $group->first()->invoice->sold_at->format('Y-m-d'),
                'gems'   => $group->count(),
                'pieces' => (int) $group->sum('pieces'),
                'weight' => (float) $group->sum(fn (InvoiceItem $item) => (float) ($item->carats ?? 0)),
            ])
            ->sortByDesc(fn (object $d) => $d->date)
            ->keyBy(fn (object $d) => $d->date);
    }

    /**
     * Group a set of items into the ordered buckets.
     *
     * @return array{sections: Collection, grand: object}
     */
    private function sectionsFromItems(Collection $items): array
    {
        $buckets = collect(self::BUCKETS)
            ->keys()
            ->mapWithKeys(fn (string $key): array => [$key => collect()]);

        foreach ($items as $item) {
            $key = $this->classify($item);
            $buckets[$key]->push([
                'id'       => $item->id,
                'serial'   => $item->serial_number ?: '—',
                'name'     => $item->description ?: '—',
                'weight'   => $item->carats !== null ? (float) $item->carats : null,
                'pieces'   => (int) $item->pieces,
                'override' => $item->export_category,
            ]);
        }

        $sections = $buckets->map(function (Collection $rows, string $key) {
            // Ascending by serial number, natural order (S2 before S10),
            // rows with no serial pushed to the end.
            $rows = $rows->sort(function (array $a, array $b): int {
                $as = (string) $a['serial'];
                $bs = (string) $b['serial'];
                $aEmpty = in_array($as, ['', '—'], true);
                $bEmpty = in_array($bs, ['', '—'], true);
                if ($aEmpty || $bEmpty) {
                    return $aEmpty === $bEmpty ? 0 : ($aEmpty ? 1 : -1);
                }
                return strnatcasecmp($as, $bs);
            })->values();

            return (object) [
                'key'    => $key,
                'title'  => self::BUCKETS[$key]['title'],
                'rows'   => $rows,
                'count'  => $rows->count(),
                'pieces' => (int) $rows->sum('pieces'),
                'weight' => (float) $rows->sum(fn (array $row) => (float) ($row['weight'] ?? 0)),
            ];
        });

        $grand = (object) [
            'gems'   => $items->count(),
            'pieces' => (int) $items->sum('pieces'),
            'weight' => (float) $items->sum(fn (InvoiceItem $item) => (float) ($item->carats ?? 0)),
        ];

        return compact('sections', 'grand');
    }

    /**
     * A single flat list ordered by serial number (ascending), used when the
     * "Sort by serial" toggle is ON. Each row carries its category label too.
     *
     * @return Collection<int, array{id:int, serial:string, name:string, weight:?float, pieces:int, category:string, override:?string}>
     */
    private function flatRows(Collection $items): Collection
    {
        $rows = $items->map(function (InvoiceItem $item) {
            $key = $this->classify($item);

            return [
                'id'           => $item->id,
                'serial'       => $item->serial_number ?: '—',
                'name'         => $item->description ?: '—',
                'weight'       => $item->carats !== null ? (float) $item->carats : null,
                'pieces'       => (int) $item->pieces,
                'category'     => self::BUCKETS[$key]['title'],
                'category_key' => $key,
                'override'     => $item->export_category,
            ];
        });

        return $rows->sort(function (array $a, array $b): int {
            $as = (string) $a['serial'];
            $bs = (string) $b['serial'];
            $aEmpty = in_array($as, ['', '—'], true);
            $bEmpty = in_array($bs, ['', '—'], true);
            if ($aEmpty || $bEmpty) {
                return $aEmpty === $bEmpty ? 0 : ($aEmpty ? 1 : -1);
            }
            $cmp = strnatcasecmp($as, $bs);

            return $cmp !== 0 ? $cmp : ($a['id'] <=> $b['id']);
        })->values();
    }

    /**
     * The full gem registry for the export control panel: every gem in the
     * system, with its current (auto or override) category, so the user can
     * review and re-assign any gem. Read-only + the export override only.
     *
     * @return array{rows: Collection, buckets: array, grand: object}
     */
    public function registry(): array
    {
        $items = InvoiceItem::with(['invoice.seller'])
            ->whereHas('invoice')
            ->get();

        $rows = $items->map(function (InvoiceItem $item) {
            $key = $this->classify($item);

            return [
                'id'            => $item->id,
                'serial'        => $item->serial_number ?: '—',
                'name'          => $item->description ?: '—',
                'weight'        => $item->carats !== null ? (float) $item->carats : null,
                'pieces'        => (int) $item->pieces,
                'sold_date'     => (string) $item->invoice->sold_at->format('Y-m-d'),
                'sold_display'  => $item->invoice->sold_at->format('d M Y'),
                'receipt'       => $item->invoice->invoice_number,
                'category_key'  => $key,
                'category'      => self::BUCKETS[$key]['title'],
                'override'      => $item->export_category,
                'has_override'  => ! empty($item->export_category),
            ];
        });

        $rows = $rows->sort(function (array $a, array $b): int {
            $as = (string) $a['serial'];
            $bs = (string) $b['serial'];
            $aEmpty = in_array($as, ['', '—'], true);
            $bEmpty = in_array($bs, ['', '—'], true);
            if ($aEmpty || $bEmpty) {
                return $aEmpty === $bEmpty ? 0 : ($aEmpty ? 1 : -1);
            }
            $cmp = strnatcasecmp($as, $bs);

            return $cmp !== 0 ? $cmp : ($a['id'] <=> $b['id']);
        })->values();

        $grand = (object) [
            'gems'   => $items->count(),
            'pieces' => (int) $items->sum('pieces'),
            'weight' => (float) $items->sum(fn (InvoiceItem $item) => (float) ($item->carats ?? 0)),
        ];

        return compact('rows', 'grand') + ['buckets' => self::BUCKETS];
    }

    /**
     * Serial-number integrity check across the whole registry.
     *
     * Scans every gem row, then:
     *  - lists MISSING serial numbers in the range [1, top] (top defaults to the
     *    highest numeric serial present, or you can pass a target like 672),
     *  - lists DUPLICATE serials (same number used by 2+ rows) with the rows,
     *  - lists non-numeric serials (can't be range-checked, e.g. "S304", "—"),
     *  - re-scans a second time and compares, so a row-by-row double check
     *    confirms the results (bool $consistent).
     *
     * @param  int|null  $top  target highest serial to check up to
     * @return array<string, mixed>
     */
    public function serialCheck(?int $top = null): array
    {
        // Two independent passes — the second confirms the first.
        $first = $this->scanSerials();
        $second = $this->scanSerials();

        $consistent = $first['rows'] === $second['rows'];

        $numCounts = $first['numCounts'];
        $textCounts = $first['textCounts'];
        $maxNum = $first['maxNum'];

        $target = $top !== null ? max(1, $top) : max(1, $maxNum);
        $target = max($target, $maxNum); // never check below the highest we have

        $presentSet = array_keys($numCounts);

        $missing = [];
        for ($n = 1; $n <= $target; $n++) {
            if (! in_array($n, $presentSet, true)) {
                $missing[] = $n;
            }
        }

        $duplicates = collect($numCounts)
            ->filter(fn (array $rowsOfNum) => count($rowsOfNum) > 1)
            ->map(function (array $rowsOfNum, string $numKey) use ($first) {
                return [
                    'serial' => $numKey,
                    'count'  => count($rowsOfNum),
                    'rows'   => $rowsOfNum,
                ];
            })
            ->values()
            ->sortBy('serial')
            ->values();

        // Text (non-numeric) duplicates: same non-numeric serial repeated.
        $textDupes = collect($textCounts)
            ->filter(fn (array $rowsOfKey) => count($rowsOfKey) > 1)
            ->map(fn (array $rowsOfKey, string $key) => [
                'serial' => $key,
                'count'  => count($rowsOfKey),
                'rows'   => $rowsOfKey,
            ])
            ->values();

        $nonNumeric = collect($textCounts)
            ->flatMap(fn (array $rowsOfKey) => $rowsOfKey)
            ->sort(fn (array $a, array $b) => strnatcasecmp((string) $a['serial'], (string) $b['serial']))
            ->values();

        return [
            'total_rows'      => $first['totalRows'],
            'unique_numeric'  => count($presentSet),
            'max_serial'      => $maxNum,
            'top'             => $target,
            'missing'         => $missing,
            'missing_count'   => count($missing),
            'duplicates'      => $duplicates,
            'non_numeric'     => $nonNumeric,
            'non_numeric_count' => $nonNumeric->count(),
            'text_duplicates' => $textDupes,
            'consistent'      => $consistent,
        ];
    }

    /**
     * One full pass over the registry built for the serial check.
     *
     * @return array{totalRows:int, maxNum:int, numCounts:array<string, list<array>>, textCounts:array<string, list<array>>, rows:array}
     */
    private function scanSerials(): array
    {
        $items = InvoiceItem::with(['invoice.seller'])->whereHas('invoice')->get();

        $numCounts = [];
        $textCounts = [];
        $maxNum = 0;
        $flat = [];

        foreach ($items as $item) {
            $serialRaw = trim((string) $item->serial_number ?: '');
            $serial = $serialRaw !== '' ? mb_strtoupper($serialRaw) : '—';
            $numeric = preg_match('/^\d+$/', $serial) ? (int) $serial : null;

            $row = [
                'id'           => $item->id,
                'serial'       => $serial,
                'name'         => $item->description ?: '—',
                'receipt'      => $item->invoice->invoice_number,
                'sold_display' => $item->invoice->sold_at->format('d M Y'),
                'pieces'       => (int) $item->pieces,
            ];

            if ($numeric !== null) {
                $key = (string) $numeric;
                $numCounts[$key][] = $row;
                $maxNum = max($maxNum, $numeric);
            } else {
                $textCounts[$serial][] = $row;
            }

            $flat[] = $row;
        }

        return [
            'totalRows'  => count($flat),
            'maxNum'     => $maxNum,
            'numCounts'  => $numCounts,
            'textCounts' => $textCounts,
            'rows'       => $flat,
        ];
    }

    /** Decide which bucket a single gem row belongs to (override-aware). */
    private function classify(InvoiceItem $item): string
    {
        // An explicit export-only override wins.
        if (! empty($item->export_category) && array_key_exists($item->export_category, self::BUCKETS)) {
            return $item->export_category;
        }

        $name = $this->normalize($item->description);
        $notes = $this->normalize($item->stone_notes);
        $pieces = (int) $item->pieces;
        $weight = $item->carats !== null ? (float) $item->carats : 0;

        if ($name === '') {
            return 'review';
        }

        if ($this->matchAnyWord($name, self::$tsv)) {
            return 'tsv';
        }

        if ($this->matchAnyWord($name, self::$padparadscha)) {
            return 'padparadscha';
        }

        if ($this->isStarSapphire($name)) {
            return 'star_sapphire';
        }

        if ($this->matchAnyWord($name, self::$alexandrite)) {
            return 'alexandrite';
        }

        if ($this->isCatseye($name)) {
            return 'catseye';
        }

        if ($this->matchAnyWord($name, self::$spinel)) {
            return 'spinel';
        }

        if ($this->matchAnyWord($name, self::$tourmaline) || $this->matchAnyWord($name, self::$kornerupine)) {
            return 'tourmaline';
        }

        if ($this->matchAnyWord($name, self::$moonstone)) {
            // Moonstones are NEVER auto-split. Every moonstone lands in the
            // dedicated "Moonstone — Assign" section so the user assigns it to
            // Faceted / Non-Faceted manually (via the export_category override).
            return 'moonstone_assign';
        }

        if ($this->isSapphire($name)) {
            return $this->sapphireBucket($name, $notes, $pieces, $weight);
        }

        if ($this->matchAnyWord($name, self::$semiPrecious)) {
            return 'semi';
        }

        // A garbled version of a named stone (typo) is worth reviewing, not
        // burying in semi-precious. Truly unknown stones go to semi-precious.
        // We also catch bare colour names (e.g. "HOT PINK", "GREEN") here so a
        // gem that is really a coloured sapphire doesn't silently end up in
        // Semi-Precious — the user assigns it to the right place.
        if ($this->looksLikeNamedStone($name)) {
            return 'review';
        }

        return 'semi';
    }

    /** True when the name contains a fragment of a high-value named stone. */
    private function looksLikeNamedStone(string $name): bool
    {
        $fragments = [
            'sapph', 'safir', 'saphir', // sapphire
            'tsav', 'tsvor', 'tsavar', 'tavor', // tsavorite
            'padparad', 'padpar', // padparadscha
            'alexand', // alexandrite
            'spinell', 'spinl', 'spynl', // spinel
            'moonst', // moonstone
            'tourmal', 'turnalin', // tourmaline
            'korner', 'kornar', // kornerupine
            'star', // star sapphire
            // Bare colour words — probably a coloured sapphire the user typed
            // without the word "sapphire". Route to review so it can be assigned.
            'pink', 'blue', 'white', 'green', 'yellow', 'peach', 'purple',
            'violet', 'teal', 'orange', 'red', 'black',
        ];

        foreach ($fragments as $fragment) {
            if (str_contains($name, $fragment)) {
                return true;
            }
        }
        return false;
    }

    private function sapphireBucket(string $name, string $notes, int $pieces, float $weight): string
    {
        $color = $this->sapphireColor($name);
        $isLotText = $this->isLotText($name, $notes);
        $isLot = $isLotText || $pieces >= 3;

        // Any "mix / mixed" sapphire (colour-mix, mixed parcel) -> Mix Sapphire Lot.
        if ($this->matchAnyWord($name.' '.$notes, ['mix', 'mixed', 'mixture', 'assorted'])) {
            return 'mix_sapphire_lot';
        }

        // Only BLUE and WHITE sapphires get their own extra "Lot" section.
        // Every other colour (green, pink, yellow, teal, purple, peach, …)
        // stays in its own named-colour section even when it is a multi-piece
        // lot, as the user wants.
        if ($isLot && in_array($color, ['blue', 'white'], true)) {
            return match ($color) {
                'blue'  => 'blue_lot',
                'white' => 'white_lot',
            };
        }

        // A sapphire with other/unknown words (e.g. "CEYLON SAPPHIRE", "FANCY
        // SAPPHIRE") that don't name a recognised colour goes to the generic
        // Sapphire section, not Needs Review.
        if ($color === null) {
            return 'sapphire';
        }

        return match ($color) {
            'blue' => $this->blueWeightBucket($pieces, $weight, $isLotText, $notes),
            'white' => 'white_sapphire',
            'green' => 'green_sapphire',
            'yellow' => 'yellow_sapphire',
            'pink' => 'pink_sapphire',
            'peach' => 'peach_sapphire',
            'purple' => 'purple_sapphire',
            'teal' => 'teal_sapphire',
            default => 'sapphire',
        };
    }

    private function blueWeightBucket(int $pieces, float $weight, bool $isLotText, string $notes): string
    {
        // "Lot/parcel" in name or notes always goes to the blue lot bucket.
        if ($isLotText || $pieces >= 3) {
            return 'blue_lot';
        }

        // 2 pieces -> always below 3 ct (per business rule), regardless of weight.
        if ($pieces >= 2) {
            return 'blue_sapphire_below';
        }

        // Single stone.
        return $weight > 3 ? 'blue_sapphire_above' : 'blue_sapphire_below';
    }

    private function isSapphire(string $name): bool
    {
        return $this->matchAnyWord($name, self::$sapphire);
    }

    private function isStarSapphire(string $name): bool
    {
        return $this->matchAnyWord($name, ['star'])
            && $this->matchAnyWord($name, self::$sapphire);
    }

    /** True for names like CAT'S EYE / CATSEYE / CATS EYE / CAT EYE. */
    private function isCatseye(string $name): bool
    {
        if (str_contains($name, 'catseye') || str_contains($name, 'cats eye') || str_contains($name, 'cat s eye')) {
            return true;
        }
        // "cat" somewhere followed by "eye" (handles "CAT'S EYE" -> "cat s eye").
        return preg_match('/\bcat\b.*?\beye\b/', $name) === 1;
    }

    private function sapphireColor(string $name): ?string
    {
        $map = [
            'blue'   => ['blue'],
            'white'  => ['white'],
            'yellow' => ['yellow'],
            'pink'   => ['pink'],
            'peach'  => ['peach'],
            'purple' => ['purple', 'violet', 'vilolet'],
            'green'  => ['green'],
            'teal'   => ['teal'],
        ];

        foreach ($map as $color => $words) {
            if ($this->matchAnyWord($name, $words)) {
                return $color;
            }
        }

        return null;
    }

    private function isLotText(string $name, string $notes): bool
    {
        return preg_match('/\b(lot|parcel|parcial|mixed|bundle|collection|assorted)\b/', $name.' '.$notes) === 1;
    }

    private function normalize(?string $value): string
    {
        $value = mb_strtolower(trim((string) $value));
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value);
        return trim((string) preg_replace('/\s+/', ' ', $value));
    }

    /** True when any whole word in $words appears in $name. */
    private function matchAnyWord(string $name, array $words): bool
    {
        foreach ($words as $word) {
            if ($word === '') {
                continue;
            }
            if (preg_match('/\b'.preg_quote($word, '/').'\b/', $name)) {
                return true;
            }
        }
        return false;
    }

    /** Recognised words per category, including the typos the user maps here. */
    private static array $tsv = ['tsv', 'tv', 'tvs', 'tsavorite', 'tsav', 'tsvor', 'tavor', 'tsavar', 'tsavorite'];
    private static array $padparadscha = ['pr', 'padparadscha', 'padparad', 'padpar', 'pad'];
    private static array $alexandrite = ['alexandrite', 'alexandrit', 'alexand'];
    private static array $spinel = ['spinel', 'spinels', 'spinnel', 'spinal', 'spynel'];
    private static array $tourmaline = ['tourmaline', 'tourmalines', 'tourmalin', 'turnaline'];
    private static array $kornerupine = ['kornerupine', 'kornarupine', 'kornerupin', 'kornarupin', 'karnerupine', 'kornerupines'];
    private static array $moonstone = ['moonstone', 'moonstones', 'moon stone'];
    private static array $sapphire = ['sapphire', 'sapphires', 'saphire', 'saphires', 'safire', 'safires', 'sapphir'];

    /** Broad list of known stones the user wants under Semi-Precious. */
    private static array $semiPrecious = [
        'amethyst', 'ametist', 'garnet', 'garnit', 'quartz', 'citrine', 'citrin', 'topaz',
        'peridot', 'zircon', 'zirconia', 'tanzanite', 'aquamarine', 'aquamar', 'onyx', 'jade',
        'beryl', 'opal', 'kyanite', 'cyanite', 'chrysoberyl', 'heliodor', 'morganite', 'emerald',
        'ruby', 'rubellite', 'rhodolite', 'sunstone', 'iolite', 'danburite', 'apatite', 'fluorite',
        'scapolite', 'thulite', 'unakite', 'aventurine', 'lapis', 'malachite', 'sodalite', 'agate',
        'carnelian', 'chrysoprase', 'bloodstone', 'jasper', 'howlite', 'turquoise', 'obsidian',
        'labradorite', 'tektite', 'ametrine', 'rutilated', 'zultanite', 'diopside', 'sphene',
        'grossular', 'demantoid', 'uvarovite', 'pyrope', 'almandine', 'spessartine',
        'rhodochrosite', 'dumortierite', 'catseye', 'cat eye', 'chrysoberyl',
    ];
}
