<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class TrainingReportPdf
{
    public function generate(string $html): string
    {
        $binary = config('hse.pdf_chrome_path');
        if (! $binary) {
            foreach (['/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', '/usr/bin/chromium', '/usr/bin/chromium-browser', '/usr/bin/google-chrome'] as $candidate) {
                if (is_executable($candidate)) {
                    $binary = $candidate;
                    break;
                }
            }
        }
        abort_unless($binary && is_executable($binary), 503, 'El generador PDF requiere configurar HSE_PDF_CHROME_PATH en el servidor.');
        $directory = storage_path('app/private/reportes-temporales/'.Str::uuid());
        File::makeDirectory($directory, 0700, true);
        try {
            File::put($directory.'/informe.html', $html);
            chmod($directory.'/informe.html', 0600);
            $process = new Process([$binary, '--headless', '--disable-gpu', '--disable-extensions', '--disable-background-networking', '--disable-sync', '--no-first-run', '--no-default-browser-check', '--user-data-dir='.$directory.'/perfil', '--no-pdf-header-footer', '--print-to-pdf='.$directory.'/informe.pdf', 'file://'.$directory.'/informe.html']);
            $process->setTimeout(45);
            try {
                $process->run();
            } catch (ProcessTimedOutException) {
                abort(503, 'La generación del PDF tardó demasiado. Intenta nuevamente.');
            }
            abort_unless($process->isSuccessful() && File::exists($directory.'/informe.pdf'), 503, 'No se pudo generar el PDF. Revisa la configuración del generador en el servidor.');
            $pdf = File::get($directory.'/informe.pdf');
            abort_unless(str_starts_with($pdf, '%PDF-'), 503, 'El generador devolvió un archivo no válido.');

            return $pdf;
        } finally {
            File::deleteDirectory($directory);
        }
    }
}
