<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class EppDeliveryPdf
{
    private array $objects = [];

    private array $images = [];

    private function object(string $value): int
    {
        $this->objects[] = $value;

        return count($this->objects);
    }

    private function stream(string $data, string $properties = ''): int
    {
        return $this->object('<< /Length '.strlen($data).' '.$properties.">>\nstream\n".$data."\nendstream");
    }

    private function text(string $text, float $x, float $y, int $size = 9, bool $bold = false): string
    {
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', str_replace(["\r", "\n", "\0"], ' ', $text));
        $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $encoded);

        return 'BT /'.($bold ? 'FB' : 'F').' '.$size.' Tf 0.12 0.18 0.15 rg 1 0 0 1 '.$x.' '.(595 - $y).' Tm ('.$escaped.") Tj ET\n";
    }

    private function lines(string $text, int $length): array
    {
        $lines = [];
        $line = '';
        foreach (preg_split('/\s+/u', trim($text)) as $word) {
            while (mb_strlen($word) > $length) {
                if ($line !== '') {
                    $lines[] = $line;
                    $line = '';
                }
                $lines[] = mb_substr($word, 0, $length);
                $word = mb_substr($word, $length);
            }
            if (mb_strlen($line.' '.$word) > $length && $line !== '') {
                $lines[] = $line;
                $line = '';
            }
            $line = trim($line.' '.$word);
        }
        $lines[] = $line;

        return $lines;
    }

    private function signature(?string $path): ?array
    {
        if (! $path) {
            return null;
        }
        if (array_key_exists($path, $this->images)) {
            return $this->images[$path];
        }
        if (! Storage::disk('firmas')->exists($path)) {
            return $this->images[$path] = null;
        }
        $source = @imagecreatefromstring(Storage::disk('firmas')->get($path));
        if (! $source) {
            return $this->images[$path] = null;
        }
        $scale = min(1, 600 / imagesx($source), 200 / imagesy($source));
        $width = max(1, (int) (imagesx($source) * $scale));
        $height = max(1, (int) (imagesy($source) * $scale));
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imagecopyresampled($image, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));
        ob_start();
        imagejpeg($image, null, 90);
        $bytes = ob_get_clean();
        imagedestroy($image);
        imagedestroy($source);
        $id = $this->stream($bytes, '/Type /XObject /Subtype /Image /Width '.$width.' /Height '.$height.' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode');

        return $this->images[$path] = ['id' => $id, 'width' => $width, 'height' => $height];
    }

    public function render(string $company, array $filters, array $rows): string
    {
        $this->objects = [];
        $this->images = [];
        $catalog = $this->object('');
        $pages = $this->object('');
        $font = $this->object('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
        $bold = $this->object('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>');
        $header = '';
        $y = 32;
        foreach ($this->lines($company, 90) as $line) {
            $header .= $this->text($line, 36, $y, 11, true);
            $y += 14;
        }
        $header .= $this->text('INFORME DE ENTREGAS DE EPP', 36, $y + 8, 15, true);
        $y += 30;
        foreach ($filters as $label => $value) {
            foreach ($this->lines($label.': '.$value, 100) as $line) {
                $header .= $this->text($line, 36, $y, 9);
                $y += 12;
            }
        }
        $header .= $this->text(count($rows).' registros de entrega confirmada', 36, $y + 2, 9);
        $tableTop = $y + 14;
        $positions = [36, 118, 328, 538, 614, 806];
        $header .= '0.91 0.95 0.92 rg 36 '.(595 - $tableTop - 25).' 770 25 re f'."\n";
        foreach (['DNI', 'TRABAJADOR', 'EPP', 'CANTIDAD', 'FIRMA'] as $i => $label) {
            $header .= $this->text($label, $positions[$i] + 8, $tableTop + 16, 9, true);
        }
        $streams = [];
        $content = $header;
        $y = $tableTop + 25;
        if ($rows === []) {
            $content .= $this->text('No hay entregas confirmadas para los filtros seleccionados.', 44, $y + 30, 11);
        }
        foreach ($rows as $row) {
            $cells = [$this->lines($row->dni, 12), $this->lines($row->trabajador, 23), $this->lines($row->epp, 23), $this->lines($row->cantidad, 9)];
            $height = max(66, max(array_map('count', $cells)) * 12 + 18);
            if ($y + $height > 535) {
                $streams[] = $content;
                $content = $header;
                $y = $tableTop + 25;
            }
            foreach ($cells as $i => $lines) {
                foreach ($lines as $j => $line) {
                    $content .= $this->text($line, $positions[$i] + 8, $y + 18 + $j * 12);
                }
            }
            $signature = $this->signature($row->firma_ruta ?? null);
            if ($signature) {
                $scale = min(170 / $signature['width'], 48 / $signature['height']);
                $w = $signature['width'] * $scale;
                $h = $signature['height'] * $scale;
                $content .= 'q '.$w.' 0 0 '.$h.' '.(622 + (176 - $w) / 2).' '.(595 - $y - ($height + $h) / 2).' cm /I'.$signature['id']." Do Q\n";
            } else {
                $content .= $this->text('Sin firma almacenada', 632, $y + 35, 9);
            }
            $content .= '0.82 0.86 0.83 RG 0.5 w 36 '.(595 - $y - $height).' m 806 '.(595 - $y - $height)." l S\n";
            $y += $height;
        }
        $streams[] = $content;
        $resources = '/Font << /F '.$font.' 0 R /FB '.$bold.' 0 R >> /XObject << ';
        foreach ($this->images as $image) {
            if ($image) {
                $resources .= '/I'.$image['id'].' '.$image['id'].' 0 R ';
            }
        }
        $resources .= '>>';
        $pageIds = [];
        foreach ($streams as $index => $stream) {
            $stream .= $this->text('Firmas registradas en la ficha del trabajador. Cantidades entregadas; no se restan devoluciones.', 36, 558, 8);
            $stream .= $this->text('Página '.($index + 1).' de '.count($streams), 720, 575, 8);
            $contentId = $this->stream($stream);
            $pageIds[] = $this->object('<< /Type /Page /Parent '.$pages.' 0 R /MediaBox [0 0 842 595] /Resources << '.$resources.' >> /Contents '.$contentId.' 0 R >>');
        }
        $this->objects[$catalog - 1] = '<< /Type /Catalog /Pages '.$pages.' 0 R >>';
        $this->objects[$pages - 1] = '<< /Type /Pages /Count '.count($pageIds).' /Kids ['.implode(' ', array_map(fn ($id) => $id.' 0 R', $pageIds)).'] >>';
        $pdf = "%PDF-1.4\n%\xe2\xe3\xcf\xd3\n";
        $offsets = [0];
        foreach ($this->objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n".$object."\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".count($offsets)."\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf.'trailer << /Size '.count($offsets).' /Root '.$catalog." 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
    }
}
