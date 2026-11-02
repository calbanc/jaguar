<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class HseContract extends Command
{
    protected $signature = 'hse:contrato {empresa} {numero} {inicio} {fin} {--usuarios=} {--almacenamiento-mb=}';

    protected $description = 'Renueva el contrato desde consola administrativa, terminando los anteriores en una transacción.';

    public function handle(): int
    {
        $input = ['numero' => $this->argument('numero'), 'fecha_inicio' => $this->argument('inicio'), 'fecha_fin' => $this->argument('fin'),
            'limite_usuarios' => $this->option('usuarios'), 'limite_almacenamiento_mb' => $this->option('almacenamiento-mb')];
        Validator::make($input, ['numero' => 'required|string|max:60', 'fecha_inicio' => 'required|date_format:Y-m-d',
            'fecha_fin' => 'required|date_format:Y-m-d|after_or_equal:fecha_inicio', 'limite_usuarios' => 'nullable|integer|min:1',
            'limite_almacenamiento_mb' => 'nullable|integer|min:1'])->validate();
        DB::transaction(function () use ($input) {
            $company = DB::table('empresas')->where('codigo', $this->argument('empresa'))->lockForUpdate()->first();
            if (! $company) {
                throw new \RuntimeException('Empresa inexistente.');
            }
            if ($input['limite_usuarios'] !== null && DB::table('miembros_empresa')->where('empresa_id', $company->id)->where('activo', true)->count() > $input['limite_usuarios']) {
                throw new \RuntimeException('El límite solicitado es menor que la cantidad de miembros activos.');
            }
            DB::table('contratos')->where('empresa_id', $company->id)->whereIn('estado', ['prueba', 'activo'])->update(['estado' => 'terminado', 'updated_at' => now()]);
            DB::table('contratos')->insert($input + ['empresa_id' => $company->id, 'estado' => 'activo']);
        });
        $this->info('Contrato registrado. Los contratos previos se conservaron como terminados.');

        return self::SUCCESS;
    }
}
