<?php

namespace App\Services;

use RuntimeException;
use ZipArchive;

class ExcelExport
{
    public function create(string $title, array $headers, iterable $rows, array $textColumns = []): string
    {
        $path = tempnam(sys_get_temp_dir(), 'hse-xlsx-');
        $sheetPath = tempnam(sys_get_temp_dir(), 'hse-sheet-');
        if ($path === false || $sheetPath === false) {
            throw new RuntimeException('No se pudo preparar la descarga.');
        }
        $sheet = fopen($sheetPath, 'wb');
        $zip = new ZipArchive;
        try {
            fwrite($sheet, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols>');
            foreach ($headers as $index => $header) {
                $column = $index + 1;
                fwrite($sheet, '<col min="'.$column.'" max="'.$column.'" width="38" customWidth="1"'.(in_array($column, $textColumns, true) ? ' style="2"' : '').'/>');
            }
            fwrite($sheet, '</cols><sheetData>');
            $number = 1;
            $this->row($sheet, $number, $headers, true);
            foreach ($rows as $row) {
                if (++$number > 1048576) {
                    throw new RuntimeException('La descarga supera el límite de filas de Excel. Ajusta la búsqueda.');
                }
                $this->row($sheet, $number, $row, textColumns: $textColumns);
            }
            fwrite($sheet, '</sheetData><autoFilter ref="A1:'.$this->column(count($headers)).$number.'"/></worksheet>');
            fclose($sheet);
            if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('No se pudo generar el archivo Excel.');
            }
            $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
            $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
            $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="'.$this->xml(mb_substr($title, 0, 31)).'" sheetId="1" r:id="rId1"/></sheets></workbook>');
            $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
            $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF245E49"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf><xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>');
            $zip->addFile($sheetPath, 'xl/worksheets/sheet1.xml');
            if (! $zip->close()) {
                throw new RuntimeException('No se pudo finalizar el archivo Excel.');
            }
            chmod($path, 0600);

            return $path;
        } catch (\Throwable $error) {
            if (is_resource($sheet)) {
                fclose($sheet);
            }
            @unlink($path);
            throw $error;
        } finally {
            @unlink($sheetPath);
        }
    }

    private function row(mixed $stream, int $number, array $values, bool $header = false, array $textColumns = []): void
    {
        fwrite($stream, '<row r="'.$number.'"'.($header ? ' ht="30" customHeight="1"' : '').'>');
        foreach (array_values($values) as $index => $value) {
            fwrite($stream, '<c r="'.$this->column($index + 1).$number.'" s="'.($header ? 1 : (in_array($index + 1, $textColumns, true) ? 2 : 0)).'" t="inlineStr"><is><t xml:space="preserve">'.$this->xml(mb_substr((string) $value, 0, 32767)).'</t></is></c>');
        }
        fwrite($stream, '</row>');
    }

    private function column(int $number): string
    {
        $name = '';
        do {
            $number--;
            $name = chr(65 + $number % 26).$name;
            $number = intdiv($number, 26);
        } while ($number > 0);

        return $name;
    }

    private function xml(string $value): string
    {
        return htmlspecialchars(preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $value), ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
