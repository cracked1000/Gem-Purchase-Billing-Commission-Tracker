<?php

namespace App\Services;

use RuntimeException;
use ZipArchive;

/** Minimal OOXML writer for the Gem Register; no external spreadsheet package required. */
class SimpleXlsx
{
    public function create(array $columns, iterable $rows, object $totals): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('The PHP ZIP extension is required to create Excel files.');
        }

        $folder = storage_path('app/temp');
        if (! is_dir($folder) && ! mkdir($folder, 0775, true) && ! is_dir($folder)) {
            throw new RuntimeException('Could not create the temporary export directory.');
        }

        $path = tempnam($folder, 'gem-register-');
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the Excel workbook.');
        }

        $sheet = $this->sheetXml($columns, collect($rows), $totals);
        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rootRelationships());
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationships());
        $zip->addFromString('xl/styles.xml', $this->styles());
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->close();

        return $path;
    }

    private function sheetXml(array $columns, $rows, object $totals): string
    {
        $xmlRows = [];
        $rowNumber = 1;
        $headerCells = [];
        foreach (array_values($columns) as $index => $definition) {
            $headerCells[] = $this->textCell($this->column($index + 1).$rowNumber, $definition['label'], 1);
        }
        $xmlRows[] = '<row r="1">'.implode('', $headerCells).'</row>';

        foreach ($rows as $row) {
            $rowNumber++;
            $cells = [];
            foreach (array_keys($columns) as $index => $key) {
                $reference = $this->column($index + 1).$rowNumber;
                $value = $row[$key] ?? '';
                $type = $columns[$key]['type'];
                $cells[] = in_array($type, ['integer', 'decimal', 'money'], true) && $value !== null && $value !== ''
                    ? $this->numberCell($reference, $value, $type === 'money' ? 2 : ($type === 'decimal' ? 3 : 0))
                    : $this->textCell($reference, $value === null ? '—' : (string) $value);
            }
            $xmlRows[] = '<row r="'.$rowNumber.'">'.implode('', $cells).'</row>';
        }

        $rowNumber++;
        $totalCells = [];
        foreach (array_keys($columns) as $index => $key) {
            $reference = $this->column($index + 1).$rowNumber;
            $value = match ($key) {
                'pieces' => $totals->pieces,
                'weight' => $totals->weight,
                'bought_price' => $totals->bought_price,
                default => $key === array_key_first($columns) ? 'ALL-TIME TOTAL' : '',
            };
            $type = $columns[$key]['type'];
            $totalCells[] = is_numeric($value) && $value !== ''
                ? $this->numberCell($reference, $value, $type === 'money' ? 2 : ($type === 'decimal' ? 3 : 1))
                : $this->textCell($reference, (string) $value, 1);
        }
        $xmlRows[] = '<row r="'.$rowNumber.'">'.implode('', $totalCells).'</row>';

        $lastColumn = $this->column(count($columns));
        $columnXml = '';
        foreach (array_values($columns) as $index => $definition) {
            $width = match ($definition['type']) {
                'money' => 18,
                'date' => 14,
                'integer', 'decimal' => 12,
                default => 22,
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

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
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

    private function workbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Gem Register" sheetId="1" r:id="rId1"/></sheets></workbook>';
    }

    private function workbookRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
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
}
