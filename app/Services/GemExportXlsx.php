<?php

namespace App\Services;

use Illuminate\Support\Collection;
use RuntimeException;
use ZipArchive;

/**
 * Writes a multi-sheet Excel workbook from the gem export data.
 *
 * Modes:
 *   all / day  -> Summary sheet (by category) + one worksheet per category.
 *   daily      -> Summary sheet (by date) + one "All Days" detail sheet
 *                 (Day / Category / Serial / Name / Weight / Pieces).
 */
class GemExportXlsx
{
    private const CATEGORY_COLUMNS = [
        ['key' => 'serial', 'label' => 'Serial',      'type' => 'text'],
        ['key' => 'name',   'label' => 'Name',        'type' => 'text'],
        ['key' => 'weight', 'label' => 'Weight (ct)', 'type' => 'decimal'],
        ['key' => 'pieces', 'label' => 'Pieces',      'type' => 'integer'],
    ];

    /**
     * @param  array<string, mixed>  $data  output of GemExportService::data()
     * @return string  absolute path to the temporary workbook
     */
    public function create(array $data): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('The PHP ZIP extension is required to create Excel files.');
        }

        $folder = storage_path('app/temp');
        if (! is_dir($folder) && ! mkdir($folder, 0775, true) && ! is_dir($folder)) {
            throw new RuntimeException('Could not create the temporary export directory.');
        }

        $path = tempnam($folder, 'gem-export-');
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the Excel workbook.');
        }

        $sheets = $this->sheets($data);
        $zip->addFromString('[Content_Types].xml', $this->contentTypes($sheets));
        $zip->addFromString('_rels/.rels', $this->rootRelationships());
        $zip->addFromString('xl/workbook.xml', $this->workbook($sheets));
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationships($sheets));
        $zip->addFromString('xl/styles.xml', $this->styles());
        foreach ($sheets as $index => $sheet) {
            $zip->addFromString('xl/worksheets/sheet'.($index + 1).'.xml', $sheet['xml']);
        }
        $zip->close();

        return $path;
    }

    /** @return array<string, mixed>[] */
    private function sheets(array $data): array
    {
        $mode = $data['mode']; // all | day | daily
        $layout = $data['layout'] ?? 'category'; // category | serial
        $sheets = [];

        if ($layout === 'serial') {
            return $this->serialSheets($data);
        }

        if ($mode === 'daily') {
            $sheets[] = $this->summaryByDaySheet($data['daily'], $data['grand']);
            $sheets[] = $this->allDaysDetailSheet($data['daily']);
            return $sheets;
        }

        // all / day: summary by category + one sheet per category.
        $summaryHeader = [
            ['key' => 'category', 'label' => 'Category',    'type' => 'text'],
            ['key' => 'count',    'label' => 'Gems',        'type' => 'integer'],
            ['key' => 'pieces',   'label' => 'Pieces',      'type' => 'integer'],
            ['key' => 'weight',   'label' => 'Weight (ct)', 'type' => 'decimal'],
        ];
        $summaryRows = collect($data['sections'])
            ->filter(fn (object $s) => $s->rows->isNotEmpty())
            ->map(fn (object $s) => [
                'category' => $s->title,
                'count'    => $s->count,
                'pieces'   => $s->pieces,
                'weight'   => $s->weight,
            ]);
        $summaryTotals = [
            'category' => 'TOTAL',
            'count'    => $data['grand']->gems,
            'pieces'   => $data['grand']->pieces,
            'weight'   => $data['grand']->weight,
        ];
        $sheets[] = ['name' => 'Summary', 'xml' => $this->tableXml($summaryHeader, $summaryRows, $summaryTotals)];

        foreach ($data['sections'] as $section) {
            if ($section->rows->isEmpty()) {
                continue;
            }
            $sheets[] = [
                'name' => $this->sheetName($section->title),
                'xml'  => $this->tableXml(self::CATEGORY_COLUMNS, $section->rows, [
                    'serial' => '',
                    'name'   => 'SECTION TOTAL',
                    'weight' => $section->weight,
                    'pieces' => $section->pieces,
                ]),
            ];
        }

        return $sheets;
    }

    /**
     * Sheets for the "Sort by serial" layout: one continuous ascending-serial
     * list with the category as a column (per day when in daily mode).
     *
     * @return array<string, mixed>[]
     */
    private function serialSheets(array $data): array
    {
        $mode = $data['mode'];
        $sheets = [];

        if ($mode === 'daily') {
            // Summary by day + one flat sheet with a Day column.
            $sheets[] = $this->summaryByDaySheet($data['daily'], $data['grand']);

            $header = [
                ['key' => 'day',      'label' => 'Day',        'type' => 'text'],
                ['key' => 'serial',   'label' => 'Serial',     'type' => 'text'],
                ['key' => 'name',     'label' => 'Name',       'type' => 'text'],
                ['key' => 'category', 'label' => 'Category',   'type' => 'text'],
                ['key' => 'weight',   'label' => 'Weight (ct)','type' => 'decimal'],
                ['key' => 'pieces',   'label' => 'Pieces',     'type' => 'integer'],
            ];
            $rows = collect();
            foreach ($data['daily'] as $day) {
                foreach ($day->flat as $row) {
                    $rows->push([
                        'day'      => $day->date->format('d M Y'),
                        'serial'   => $row['serial'],
                        'name'     => $row['name'],
                        'category' => $row['category'],
                        'weight'   => $row['weight'],
                        'pieces'   => $row['pieces'],
                    ]);
                }
            }
            $sheets[] = [
                'name' => 'All Days by Serial',
                'xml'  => $this->tableXml($header, $rows, [
                    'day' => 'TOTAL', 'serial' => '', 'name' => '', 'category' => '',
                    'weight' => $data['grand']->weight, 'pieces' => $data['grand']->pieces,
                ]),
            ];

            return $sheets;
        }

        // all / day: single flat sheet.
        $header = [
            ['key' => 'serial',   'label' => 'Serial',     'type' => 'text'],
            ['key' => 'name',     'label' => 'Name',       'type' => 'text'],
            ['key' => 'category', 'label' => 'Category',   'type' => 'text'],
            ['key' => 'weight',   'label' => 'Weight (ct)','type' => 'decimal'],
            ['key' => 'pieces',   'label' => 'Pieces',     'type' => 'integer'],
        ];
        $sheets[] = [
            'name' => 'By Serial',
            'xml'  => $this->tableXml($header, collect($data['flat']), [
                'serial' => '', 'name' => 'TOTAL', 'category' => '',
                'weight' => $data['grand']->weight, 'pieces' => $data['grand']->pieces,
            ]),
        ];

        return $sheets;
    }

    /** @return array<string, mixed> */
    private function summaryByDaySheet(Collection $daily, object $grand): array
    {
        $header = [
            ['key' => 'date',   'label' => 'Date',       'type' => 'text'],
            ['key' => 'gems',   'label' => 'Gems',       'type' => 'integer'],
            ['key' => 'pieces', 'label' => 'Pieces',     'type' => 'integer'],
            ['key' => 'weight', 'label' => 'Weight (ct)', 'type' => 'decimal'],
        ];

        $rows = $daily->map(fn (object $day) => [
            'date'   => $day->date->format('d M Y'),
            'gems'   => $day->grand->gems,
            'pieces' => $day->grand->pieces,
            'weight' => $day->grand->weight,
        ]);

        return [
            'name' => 'Summary by Day',
            'xml'  => $this->tableXml($header, $rows, [
                'date'   => 'TOTAL',
                'gems'   => $grand->gems,
                'pieces' => $grand->pieces,
                'weight' => $grand->weight,
            ]),
        ];
    }

    /** @return array<string, mixed> */
    private function allDaysDetailSheet(Collection $daily): array
    {
        $header = [
            ['key' => 'day',      'label' => 'Day',        'type' => 'text'],
            ['key' => 'category', 'label' => 'Category',   'type' => 'text'],
            ['key' => 'serial',   'label' => 'Serial',     'type' => 'text'],
            ['key' => 'name',     'label' => 'Name',       'type' => 'text'],
            ['key' => 'weight',   'label' => 'Weight (ct)','type' => 'decimal'],
            ['key' => 'pieces',   'label' => 'Pieces',     'type' => 'integer'],
        ];

        $rows = collect();
        foreach ($daily as $day) {
            foreach ($day->sections as $section) {
                foreach ($section->rows as $row) {
                    $rows->push([
                        'day'      => $day->date->format('d M Y'),
                        'category' => $section->title,
                        'serial'   => $row['serial'],
                        'name'     => $row['name'],
                        'weight'   => $row['weight'],
                        'pieces'   => $row['pieces'],
                    ]);
                }
            }
        }

        return [
            'name' => 'All Days',
            'xml'  => $this->tableXml($header, $rows, [
                'day' => 'TOTAL', 'category' => '', 'serial' => '', 'name' => '',
                'weight' => null, 'pieces' => null,
            ]),
        ];
    }

    private function tableXml(array $columns, iterable $rows, array $totals): string
    {
        $xmlRows = [];
        $rowNumber = 1;

        $headerCells = [];
        foreach ($columns as $index => $column) {
            $headerCells[] = $this->textCell($this->column($index + 1).$rowNumber, $column['label'], 1);
        }
        $xmlRows[] = '<row r="1">'.implode('', $headerCells).'</row>';

        foreach ($rows as $row) {
            $rowNumber++;
            $cells = [];
            foreach ($columns as $index => $column) {
                $reference = $this->column($index + 1).$rowNumber;
                $value = $row[$column['key']] ?? '';
                $type = $column['type'];
                $cells[] = in_array($type, ['integer', 'decimal'], true) && $value !== null && $value !== ''
                    ? $this->numberCell($reference, $value, $type === 'decimal' ? 3 : 0)
                    : $this->textCell($reference, $value === null || $value === '' ? '—' : (string) $value);
            }
            $xmlRows[] = '<row r="'.$rowNumber.'">'.implode('', $cells).'</row>';
        }

        $rowNumber++;
        $totalCells = [];
        foreach ($columns as $index => $column) {
            $reference = $this->column($index + 1).$rowNumber;
            $value = $totals[$column['key']] ?? '';
            $type = $column['type'];
            $totalCells[] = is_numeric($value) && $value !== ''
                ? $this->numberCell($reference, $value, $type === 'decimal' ? 3 : 1)
                : $this->textCell($reference, (string) $value, 1);
        }
        $xmlRows[] = '<row r="'.$rowNumber.'">'.implode('', $totalCells).'</row>';

        $lastColumn = $this->column(count($columns));
        $columnXml = '';
        foreach ($columns as $index => $column) {
            $width = match ($column['type']) {
                'decimal' => 14,
                'integer' => 12,
                default   => 30,
            };
            $n = $index + 1;
            $columnXml .= '<col min="'.$n.'" max="'.$n.'" width="'.$width.'" customWidth="1"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<dimension ref="A1:'.$lastColumn.$rowNumber.'"/>'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<cols>'.$columnXml.'</cols><sheetData>'.implode('', $xmlRows).'</sheetData>'
            .'<autoFilter ref="A1:'.$lastColumn.max(1, $rowNumber - 1).'"/>'
            .'</worksheet>';
    }

    private function sheetName(string $title): string
    {
        // Excel worksheet names: max 31 chars, no \ / ? * [ ] : — and we also
        // strip emoji / symbols (e.g. ⚠️, 🌙) so they never break the file.
        $name = preg_replace('/[^A-Za-z0-9_]+/', ' ', $title);
        $name = preg_replace('/\s+/', ' ', trim((string) $name));
        $name = trim((string) $name, ' ');
        return substr($name, 0, 31) ?: 'Section';
    }

    private function textCell(string $reference, string $value, int $style = 0): string
    {
        return '<c r="'.$reference.'" t="inlineStr" s="'.$style.'"><is><t xml:space="preserve">'
            .htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</t></is></c>';
    }

    private function numberCell(string $reference, int|float|string $value, int $style): string
    {
        return '<c r="'.$reference.'" t="n" s="'.$style.'"><v>'.(float) $value.'</v></c>';
    }

    private function column(int $number): string
    {
        $name = '';
        while ($number > 0) {
            $number--;
            $name = chr(65 + ($number % 26)).$name;
            $number = intdiv($number, 26);
        }
        return $name;
    }

    private function contentTypes(array $sheets): string
    {
        $overrides = '';
        foreach ($sheets as $index => $sheet) {
            $overrides .= '<Override PartName="/xl/worksheets/sheet'.($index + 1).'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .$overrides
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>';
    }

    private function rootRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function workbook(array $sheets): string
    {
        $sheetTags = '';
        foreach ($sheets as $index => $sheet) {
            $sheetTags .= '<sheet name="'.$this->escape($sheet['name']).'" sheetId="'.($index + 1).'" r:id="rId'.($index + 1).'"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'.$sheetTags.'</sheets></workbook>';
    }

    private function workbookRelationships(array $sheets): string
    {
        $rels = '';
        $id = 1;
        foreach ($sheets as $index => $sheet) {
            $rels .= '<Relationship Id="rId'.$id.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.($index + 1).'.xml"/>';
            $id++;
        }
        $rels .= '<Relationship Id="rId'.$id.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$rels.'</Relationships>';
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="2"><numFmt numFmtId="164" formatCode="#,##0.00"/><numFmt numFmtId="165" formatCode="0.00"/></numFmts>'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF1F4E3D"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="4"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFill="1" applyFont="1"/><xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/><xf numFmtId="165" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
