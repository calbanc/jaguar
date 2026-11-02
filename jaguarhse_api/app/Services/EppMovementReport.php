<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EppMovementReport
{
    public function query(object $context, Request $request): Builder
    {
        $input = $request->validate(['mes' => 'nullable|date_format:Y-m', 'tipo_cambio_consulta' => 'nullable|numeric|decimal:0,6|gt:0|max:1000']);
        $rate = $input['tipo_cambio_consulta'] ?? null;
        $source = DB::table('v_epp_movimiento_detalle')->where('empresa_id', $context->empresa_id)->select('*');
        if (! empty($input['mes'])) {
            $start = CarbonImmutable::createFromFormat('!Y-m', $input['mes'], $context->zona_horaria);
            $source->where('fecha', '>=', $start->utc()->format('Y-m-d H:i:s'))
                ->where('fecha', '<', $start->addMonth()->utc()->format('Y-m-d H:i:s'));
        }
        $source->selectRaw('ROUND(costo_unitario / NULLIF(? * 1.0, 0), 6) as costo_unitario_usd, ROUND(costo_total / NULLIF(? * 1.0, 0), 6) as costo_total_usd, ? as tipo_cambio_consulta', [$rate, $rate, $rate]);

        return DB::query()->fromSub($source, 'movimientos');
    }

    public function present(object $row, object $context): object
    {
        $row = clone $row;
        $row->fecha = CarbonImmutable::parse($row->fecha, 'UTC')->setTimezone($context->zona_horaria)->format('Y-m-d H:i:s');
        unset($row->clave_operacion);

        return $row;
    }
}
