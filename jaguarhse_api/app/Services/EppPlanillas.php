<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class EppPlanillas
{
    public function nextNumber(object $context): int
    {
        return (int) DB::table('epp_planillas')->where('empresa_id', $context->empresa_id)->max('numero') + 1;
    }

    public function register(object $context, string $uuid, array $ids): object
    {
        return DB::transaction(function () use ($context, $uuid, $ids) {
            app(HseData::class)->lock($context);
            $existing = DB::table('epp_planillas')->where('empresa_id', $context->empresa_id)->where('uuid_cliente', $uuid)->first();
            if ($existing) {
                $priorIds = json_decode($existing->entregas, true, flags: JSON_THROW_ON_ERROR);
                sort($priorIds);
                $requestedIds = $ids;
                sort($requestedIds);
                abort_unless($priorIds === $requestedIds, 409, 'La referencia ya pertenece a otra planilla.');

                return $existing;
            }
            $deliveries = DB::table('epp_entregas')->where('empresa_id', $context->empresa_id)->whereIn('id', $ids)->orderBy('id')->get();
            abort_unless($deliveries->count() === count($ids) && count($ids) > 0, 422, 'Las entregas de la planilla no son válidas.');
            $date = CarbonImmutable::parse($deliveries->first()->fecha, 'UTC')->setTimezone($context->zona_horaria)->toDateString();
            $assignments = DB::table('trabajador_asignaciones as h')
                ->leftJoin('zonas as z', fn ($join) => $join->on('z.id', '=', 'h.zona_id')->on('z.empresa_id', '=', 'h.empresa_id'))
                ->where('h.empresa_id', $context->empresa_id)->whereIn('h.trabajador_id', $deliveries->pluck('trabajador_id'));
            $workerZones = app(WorkerAssignments::class)->atDate($assignments, $date, 'h.')->pluck('z.nombre', 'h.trabajador_id');
            $zones = $deliveries->map(fn ($delivery) => $workerZones[$delivery->trabajador_id] ?? 'Sin zona asignada')->all();
            $zones = array_unique($zones);
            sort($zones);
            $responsible = DB::table('miembros_empresa as m')->join('usuarios as u', 'u.id', '=', 'm.usuario_id')
                ->where('m.empresa_id', $context->empresa_id)->where('m.id', $context->miembro_id)->value('u.nombre');
            $id = DB::table('epp_planillas')->insertGetId([
                'empresa_id' => $context->empresa_id,
                'numero' => $this->nextNumber($context),
                'uuid_cliente' => $uuid, 'fecha' => $date, 'zona' => implode(', ', $zones),
                'registrado_por' => $context->miembro_id, 'responsable' => $responsible ?? 'No registrado',
                'trabajadores' => $deliveries->pluck('trabajador_id')->unique()->count(),
                'entregas' => json_encode(array_values($ids), JSON_THROW_ON_ERROR), 'created_at' => now(),
            ]);

            return DB::table('epp_planillas')->where('id', $id)->first();
        });
    }
}
