<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WorkerSignatures
{
    private array $created = [];

    private array $retired = [];

    public function transaction(callable $callback): mixed
    {
        try {
            $result = DB::transaction($callback);
        } catch (\Throwable $error) {
            $this->cleanup($this->created);
            throw $error;
        }
        $this->cleanup($this->retired);

        return $result;
    }

    private function cleanup(array $paths): void
    {
        foreach ($paths as $path) {
            try {
                Storage::disk('firmas')->delete($path);
            } catch (\Throwable $error) {
                report($error);
            }
        }
    }

    public function path(object $worker, string $extension): string
    {
        $document = str_replace('.', '%2E', rawurlencode($worker->numero_documento));
        $type = strtoupper(trim($worker->tipo_documento));
        $folder = $type === 'DNI' ? $document : str_replace('.', '%2E', rawurlencode($type)).'/'.$document;

        return 'empresas/'.$worker->empresa_id.'/'.$folder.'/'.$worker->id.'/'.Str::uuid().'.'.$extension;
    }

    public function track(string $new, ?string $old): void
    {
        $this->created[] = $new;
        if ($old) {
            $this->retired[] = $old;
        }
    }

    public function relocate(object $before, array $values): array
    {
        if (! ($before->firma_almacenada ?? false) || (($values['numero_documento'] ?? $before->numero_documento) === $before->numero_documento && ($values['tipo_documento'] ?? $before->tipo_documento) === $before->tipo_documento)) {
            return $values;
        }
        $worker = (object) array_merge((array) $before, $values);
        $path = $this->path($worker, pathinfo($before->firma_ruta, PATHINFO_EXTENSION));
        $this->track($path, $before->firma_ruta);
        Storage::disk('firmas')->copy($before->firma_ruta, $path);
        $values['firma_ruta'] = $path;

        return $values;
    }
}
