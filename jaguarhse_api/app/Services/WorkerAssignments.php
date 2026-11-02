<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WorkerAssignments
{
    public function referenceDate(): string
    {
        $request = request();
        if ($date = $request->attributes->get('worker_reference_date')) {
            return $date;
        }
        $values = Validator::make(['fecha_ubicacion' => $request->query('fecha_ubicacion')], ['fecha_ubicacion' => 'nullable|date_format:Y-m-d'])->validate();
        $date = $values['fecha_ubicacion'] ?? CarbonImmutable::now($request->attributes->get('hse')?->zona_horaria ?? config('app.timezone'))->toDateString();
        $request->attributes->set('worker_reference_date', $date);

        return $date;
    }

    public function atDate(Builder $query, string $date, string $prefix = ''): Builder
    {
        return $query->where($prefix.'fecha_inicio', '<=', $date)->where(fn (Builder $end) => $end->whereNull($prefix.'fecha_fin')->orWhere($prefix.'fecha_fin', '>=', $date));
    }

    public function query(object $context, int $workerId): Builder
    {
        return DB::table('trabajador_asignaciones')->where('empresa_id', $context->empresa_id)->where('trabajador_id', $workerId);
    }

    public function present(object $assignment): object
    {
        $result = clone $assignment;
        $result->sede_nombre = DB::table('sedes')->where('empresa_id', $result->empresa_id)->where('id', $result->sede_id)->value('nombre');
        $result->zona_nombre = DB::table('zonas')->where('empresa_id', $result->empresa_id)->where('id', $result->zona_id)->value('nombre');
        $result->area_nombre = $result->area_id ? DB::table('areas')->where('empresa_id', $result->empresa_id)->where('id', $result->area_id)->value('nombre') : null;
        $result->pais = DB::table('sedes')->where('empresa_id', $result->empresa_id)->where('id', $result->sede_id)->value('pais');
        $date = $this->referenceDate();
        $result->estado_periodo = $result->fecha_inicio > $date ? 'Programada' : ($result->fecha_fin && $result->fecha_fin < $date ? 'Finalizada' : 'Vigente');

        return $result;
    }

    public function locate(object $worker): object
    {
        $assignment = $this->atDate($this->query($worker, $worker->id), $this->referenceDate())->first();
        $location = $assignment ? $this->present($assignment) : null;
        // The dated history is authoritative; worker location columns retain the original registration.
        foreach (['sede_id', 'zona_id', 'area_id', 'sede_nombre', 'zona_nombre', 'area_nombre', 'pais'] as $field) {
            $worker->$field = $location?->$field;
        }
        $worker->asignacion_inicio = $location?->fecha_inicio;
        $worker->asignacion_fin = $location?->fecha_fin;
        $worker->fecha_ubicacion = $this->referenceDate();

        return $worker;
    }

    public function initial(Request $request, object $worker, ?array $period = null): void
    {
        $context = $request->attributes->get('hse');
        $start = $period['fecha_inicio'] ?? $worker->fecha_ingreso;
        $end = $period === null ? $worker->fecha_cese : ($period['fecha_fin'] ?? null);
        $this->validatePeriod($worker, $start, $end);
        $site = DB::table('zonas')->where('empresa_id', $worker->empresa_id)->where('id', $worker->zona_id)->value('sede_id');
        $assignment = app(HseData::class)->insert('trabajador_asignaciones', $context, [
            'trabajador_id' => $worker->id, 'sede_id' => $site, 'zona_id' => $worker->zona_id,
            'area_id' => $worker->area_id, 'fecha_inicio' => $start, 'fecha_fin' => $end,
        ]);
        app(HseData::class)->audit($request, 'trabajador_asignaciones', null, $assignment, 'crear');
    }

    private function validatePeriod(object $worker, string $start, ?string $end): void
    {
        if ($worker->fecha_ingreso && $start < $worker->fecha_ingreso) {
            throw ValidationException::withMessages(['fecha_inicio' => 'No puede ser anterior al ingreso del trabajador.']);
        }
        if ($worker->fecha_cese && (! $end || $end > $worker->fecha_cese)) {
            throw ValidationException::withMessages(['fecha_fin' => 'La asignación debe finalizar como máximo en la fecha de cese.']);
        }
    }

    public function validateEmployment(array $worker): void
    {
        $query = $this->query((object) $worker, $worker['id']);
        if (! empty($worker['fecha_ingreso']) && (clone $query)->where('fecha_inicio', '<', $worker['fecha_ingreso'])->exists()) {
            throw ValidationException::withMessages(['fecha_ingreso' => 'Hay asignaciones anteriores a esta fecha de ingreso. Corrige primero sus períodos.']);
        }
        if (! empty($worker['fecha_cese']) && (clone $query)->where(fn (Builder $end) => $end->whereNull('fecha_fin')->orWhere('fecha_fin', '>', $worker['fecha_cese']))->exists()) {
            throw ValidationException::withMessages(['fecha_cese' => 'Cierra primero las asignaciones que superan la fecha de cese.']);
        }
    }

    public function save(Request $request, object $worker, ?object $existing = null): object
    {
        $context = $request->attributes->get('hse');
        $allowed = ['zona_id', 'area_id', 'fecha_inicio', 'fecha_fin', 'observaciones', 'cerrar_anterior'];
        foreach (array_keys($request->all()) as $key) {
            if (! in_array($key, $allowed, true) || ($existing && $key === 'cerrar_anterior')) {
                throw ValidationException::withMessages([$key => 'Campo no permitido en esta asignación.']);
            }
        }
        $input = array_merge((array) $existing, $request->all());
        $values = Validator::make($input, [
            'zona_id' => ['required', 'integer', Rule::exists('zonas', 'id')->where('empresa_id', $context->empresa_id)],
            'area_id' => ['nullable', 'integer', Rule::exists('areas', 'id')->where('empresa_id', $context->empresa_id)->where('zona_id', $input['zona_id'] ?? null)],
            'fecha_inicio' => ['required', 'date_format:Y-m-d'],
            'fecha_fin' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:fecha_inicio'],
            'observaciones' => ['nullable', 'string', 'max:1000'], 'cerrar_anterior' => ['sometimes', 'boolean'],
        ])->validate();
        $start = $values['fecha_inicio'];
        $end = $values['fecha_fin'] ?? null;
        $this->validatePeriod($worker, $start, $end);
        $data = app(HseData::class);
        if (! $existing && ! empty($values['cerrar_anterior'])) {
            $previous = $this->query($context, $worker->id)->whereNull('fecha_fin')->where('fecha_inicio', '<', $start)->first();
            if ($previous) {
                $closed = $data->update('trabajador_asignaciones', $context, $previous->id, ['fecha_fin' => CarbonImmutable::parse($start)->subDay()->toDateString()]);
                $data->audit($request, 'trabajador_asignaciones', $previous, $closed, 'cerrar_por_traslado');
            }
        }
        $overlap = $this->query($context, $worker->id)->where('fecha_inicio', '<=', $end ?? '9999-12-31')->where(fn (Builder $query) => $query->whereNull('fecha_fin')->orWhere('fecha_fin', '>=', $start));
        if ($existing) {
            $overlap->where('id', '!=', $existing->id);
        }
        if ($overlap->exists()) {
            throw ValidationException::withMessages(['fecha_inicio' => 'El período se superpone con otra asignación. Cierra o corrige el período anterior.']);
        }
        unset($values['cerrar_anterior']);
        $values['area_id'] = $values['area_id'] ?? null;
        $values['fecha_fin'] = $end;
        $values['trabajador_id'] = $worker->id;
        $values['sede_id'] = DB::table('zonas')->where('empresa_id', $context->empresa_id)->where('id', $values['zona_id'])->value('sede_id');
        $after = $existing ? $data->update('trabajador_asignaciones', $context, $existing->id, $values) : $data->insert('trabajador_asignaciones', $context, $values);
        $data->audit($request, 'trabajador_asignaciones', $existing, $after, $existing ? 'editar' : 'crear');

        return $this->present($after);
    }

    public function filterSelection(Builder $query, Request $request): void
    {
        $values = Validator::make($request->query(), ['sede_id' => 'nullable|integer|min:1', 'zona_id' => 'nullable|integer|min:1'])->validate();
        foreach ($values as $field => $value) {
            if ($value !== null) {
                $this->locationFilter($query, $field, (string) $value, exact: true);
            }
        }
    }

    public function locationFilter(Builder $query, string $field, string $value, bool $or = false, bool $exact = false): bool
    {
        $columns = ['sede_nombre' => 's.nombre', 'zona_nombre' => 'z.nombre', 'area_nombre' => 'a.nombre', 'pais' => 's.pais', 'sede_id' => 'h.sede_id', 'zona_id' => 'h.zona_id', 'area_id' => 'h.area_id'];
        if (! isset($columns[$field])) {
            return false;
        }
        $related = DB::table('trabajador_asignaciones as h')->selectRaw('1')->whereColumn('h.empresa_id', 'trabajadores.empresa_id')->whereColumn('h.trabajador_id', 'trabajadores.id')
            ->join('sedes as s', fn ($join) => $join->on('s.id', '=', 'h.sede_id')->on('s.empresa_id', '=', 'h.empresa_id'))
            ->join('zonas as z', fn ($join) => $join->on('z.id', '=', 'h.zona_id')->on('z.empresa_id', '=', 'h.empresa_id'))
            ->leftJoin('areas as a', fn ($join) => $join->on('a.id', '=', 'h.area_id')->on('a.empresa_id', '=', 'h.empresa_id'));
        $this->atDate($related, $this->referenceDate(), 'h.');
        if ($exact) {
            $related->where($columns[$field], $value);
        } else {
            app(TableFilters::class)->contains($related, $columns[$field], $value);
        }
        $query->whereExists($related, $or ? 'or' : 'and');

        return true;
    }
}
