<?php

namespace App\Services;

use DateTimeImmutable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use SimpleXMLElement;
use ZipArchive;

class WorkerSpreadsheet
{
    public const HEADERS = ['DNI', 'Nombres', 'Apellidos', 'Puesto', 'Fecha de ingreso'];

    public function read(string $path, array $headers = self::HEADERS): array
    {
        $zip = new ZipArchive;
        $this->check($zip->open($path) === true, 'El archivo no es un Excel .xlsx válido.');
        try {
            $size = 0;
            $this->check($zip->numFiles <= 256, 'El Excel contiene demasiadas partes. Usa la plantilla.');
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->statIndex($i);
                $size += $entry['size'];
                $this->check($entry['size'] <= 10 * 1024 * 1024 && $size <= 20 * 1024 * 1024, 'El contenido del Excel supera el límite permitido.');
                $this->check(! str_contains(strtolower($entry['name']), 'vbaproject'), 'No se permiten macros.');
            }
            $workbook = $this->xml($zip, 'xl/workbook.xml');
            $sheets = $workbook->xpath('//*[local-name()="sheet"]');
            $this->check(count($sheets) > 0, 'No se encontró una hoja de trabajadores.');
            $sheetId = (string) $sheets[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            $relations = $this->xml($zip, 'xl/_rels/workbook.xml.rels');
            $target = null;
            foreach ($relations->children() as $relation) {
                if ((string) $relation['Id'] === $sheetId && (string) $relation['TargetMode'] !== 'External') {
                    $target = (string) $relation['Target'];
                }
            }
            $this->check($target !== null && ! str_contains($target, '..') && ! str_contains($target, '\\'), 'La hoja de datos no es válida.');
            $sheetPath = str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/'.$target;
            $sheet = $this->xml($zip, $sheetPath);
            $shared = [];
            if ($zip->locateName('xl/sharedStrings.xml') !== false) {
                foreach ($this->xml($zip, 'xl/sharedStrings.xml')->children() as $item) {
                    $shared[] = $this->text($item);
                }
            }
            $properties = $workbook->xpath('//*[local-name()="workbookPr"]');
            $date1904 = isset($properties[0]) && in_array((string) $properties[0]['date1904'], ['1', 'true'], true);
            $rows = [];
            $seen = [];
            $header = null;
            foreach ($sheet->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]') as $row) {
                $number = (int) $row['r'];
                $this->check($number >= 1 && $number <= 2001 && ! isset($seen[$number]), 'Usa la primera fila para los encabezados y hasta 2000 filas de trabajadores.');
                $seen[$number] = true;
                $values = array_fill(0, count($headers), '');
                $errors = [];
                $cells = [];
                foreach ($row->children() as $cell) {
                    $reference = (string) $cell['r'];
                    $this->check((bool) preg_match('/^([A-Z]{1,3})([0-9]{1,7})$/', $reference, $match) && (int) $match[2] === $number && ! isset($cells[$reference]), 'El Excel contiene referencias de celda inválidas.');
                    $cells[$reference] = true;
                    $column = 0;
                    foreach (str_split($match[1]) as $letter) {
                        $column = $column * 26 + ord($letter) - 64;
                    }
                    $type = (string) $cell['t'];
                    $value = match ($type) {
                        'inlineStr' => $this->text($cell->is),
                        's' => $shared[(int) $cell->v] ?? '',
                        default => (string) $cell->v,
                    };
                    $value = trim($value);
                    if (isset($cell->f)) {
                        $errors[] = 'La celda '.$reference.' contiene una fórmula. Reemplázala por su valor.';
                    }
                    if ($type === 'e') {
                        $errors[] = 'La celda '.$reference.' contiene un error de Excel.';
                    }
                    if ($column > count($headers)) {
                        $this->check($value === '' && ! isset($cell->f), 'La plantilla admite solo las columnas indicadas.');

                        continue;
                    }
                    if ($headers === self::HEADERS && $column === 5 && $number > 1 && in_array($type, ['', 'n'], true) && is_numeric($value)) {
                        $serial = (float) $value;
                        if ($serial >= 1 && $serial <= 2958465 && ($date1904 || floor($serial) !== 60.0)) {
                            $base = $date1904 ? '1904-01-01' : ($serial < 60 ? '1899-12-31' : '1899-12-30');
                            $value = (new DateTimeImmutable($base))->modify('+'.(int) $serial.' days')->format('Y-m-d');
                        }
                    }
                    if ($headers === self::HEADERS && $column === 5 && preg_match('/^([0-9]{2})\/([0-9]{2})\/([0-9]{4})$/', $value, $date)) {
                        $value = $date[3].'-'.$date[2].'-'.$date[1];
                    }
                    $values[$column - 1] = $value;
                }
                if ($number === 1) {
                    $header = $values;
                    $this->check($errors === [], 'Los encabezados no pueden contener fórmulas ni errores.');
                } elseif (implode('', $values) !== '' || $errors !== []) {
                    $rows[] = ['fila' => $number, 'values' => $values, 'errores' => $errors];
                }
            }
            $normalize = fn ($value) => Str::lower(Str::ascii(trim($value)));
            $this->check($header !== null && array_map($normalize, $header) === array_map($normalize, $headers), 'Los encabezados deben ser: '.implode(', ', $headers).'. Descarga la plantilla.');
            $this->check($rows !== [], 'El Excel no contiene trabajadores. Completa la plantilla antes de importarla.');

            return $rows;
        } finally {
            $zip->close();
        }
    }

    private function xml(ZipArchive $zip, string $path): SimpleXMLElement
    {
        $content = $zip->getFromName($path);
        $this->check(is_string($content) && ! str_contains($content, "\0") && ! preg_match('/<!DOCTYPE|<!ENTITY/i', $content), 'El contenido del Excel no es válido.');
        $previous = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_string($content, SimpleXMLElement::class, LIBXML_NONET);
            $this->check($xml !== false, 'No se pudo leer el contenido del Excel.');

            return $xml;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function text(SimpleXMLElement $element): string
    {
        return implode('', array_map(fn ($node) => (string) $node, $element->xpath('.//*[local-name()="t"]')));
    }

    private function check(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['archivo' => $message]);
        }
    }
}
