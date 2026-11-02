<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TableFilters
{
    public function input(Request $request): array
    {
        return $request->validate(['columns' => 'sometimes|array|max:30', 'columns.*' => 'nullable|string|max:150', 'q' => 'nullable|string|max:150']);
    }

    public function contains(Builder $query, string $field, string $value): void
    {
        $query->whereRaw($query->getGrammar()->wrap($field)." LIKE ? ESCAPE '!'", [$this->pattern($value)]);
    }

    public function pattern(string $value): string
    {
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value).'%';
    }

    public function columns(string $table): array
    {
        $extra = match ($table) {
            'almacenes' => ['sede_nombre'],
            'epp_entregas' => ['trabajador', 'responsable'],
            'zonas' => ['sede_nombre', 'pais'],
            'areas' => ['zona_nombre', 'sede_nombre', 'pais'],
            'trabajadores' => ['zona_nombre', 'sede_nombre', 'pais', 'area_nombre'],
            'v_epp_movimiento_detalle' => ['costo_unitario_usd', 'costo_total_usd', 'tipo_cambio_consulta'],
            default => [],
        };

        return array_values(array_diff(array_merge(Schema::getColumnListing($table), $extra), ['empresa_id', 'uuid_cliente', 'ruta', 'disco', 'password_hash', 'huella', 'proveedor_clave', 'clave_operacion']));
    }

    private function relatedColumn(Builder $query, string $table, string $field, string $value, bool $or = false): bool
    {
        if ($table === 'epp_entregas' && in_array($field, ['trabajador', 'responsable'], true)) {
            if ($field === 'trabajador') {
                $related = DB::table('trabajadores as w')->selectRaw('1')->whereColumn('w.empresa_id', 'epp_entregas.empresa_id')->whereColumn('w.id', 'epp_entregas.trabajador_id');
                $related->where(function (Builder $names) use ($value) {
                    $this->contains($names, 'w.nombres', $value);
                    $names->orWhere(function (Builder $last) use ($value) {
                        $this->contains($last, 'w.apellidos', $value);
                    });
                    $names->orWhere(function (Builder $document) use ($value) {
                        $this->contains($document, 'w.numero_documento', $value);
                    });
                });
            } else {
                $related = DB::table('miembros_empresa as m')->join('usuarios as u', 'u.id', '=', 'm.usuario_id')->selectRaw('1')->whereColumn('m.empresa_id', 'epp_entregas.empresa_id')->whereColumn('m.id', 'epp_entregas.entregado_por');
                $this->contains($related, 'u.nombre', $value);
            }
            $query->whereExists($related, $or ? 'or' : 'and');

            return true;
        }
        if ($table === 'trabajadores') {
            return app(WorkerAssignments::class)->locationFilter($query, $field, $value, $or);
        }
        if ($table === 'areas' && in_array($field, ['zona_nombre', 'sede_nombre', 'pais'], true)) {
            $related = DB::table('zonas')->selectRaw('1')->whereColumn('zonas.id', $table.'.zona_id')->whereColumn('zonas.empresa_id', $table.'.empresa_id');
            if ($field === 'zona_nombre') {
                $column = 'zonas.nombre';
            } else {
                $related->join('sedes', function ($join) {
                    $join->on('sedes.id', '=', 'zonas.sede_id')->on('sedes.empresa_id', '=', 'zonas.empresa_id');
                });
                $column = $field === 'pais' ? 'sedes.pais' : 'sedes.nombre';
            }
        } elseif (in_array($table, ['almacenes', 'zonas'], true) && ($field === 'sede_nombre' || ($table === 'zonas' && $field === 'pais'))) {
            $related = DB::table('sedes')->selectRaw('1')->whereColumn('sedes.id', $table.'.sede_id')->whereColumn('sedes.empresa_id', $table.'.empresa_id');
            $column = $field === 'pais' ? 'sedes.pais' : 'sedes.nombre';
        } else {
            return false;
        }
        $this->contains($related, $column, $value);
        $query->whereExists($related, $or ? 'or' : 'and');

        return true;
    }

    public function apply(Builder $query, string $table, Request $request): void
    {
        if ($table === 'trabajadores') {
            app(WorkerAssignments::class)->referenceDate();
        }
        $input = $this->input($request);
        $fields = $this->columns($table);
        foreach ($input['columns'] ?? [] as $field => $value) {
            abort_unless(in_array($field, $fields, true), 422, 'Columna de filtro inválida.');
            if ($value === null || $value === '') {
                continue;
            }
            if ($this->relatedColumn($query, $table, $field, $value)) {
                continue;
            }
            if ($field === 'activo') {
                abort_unless(in_array($value, ['0', '1'], true), 422, 'Estado inválido.');
                $query->where($field, (int) $value);
            } else {
                $this->contains($query, $field, $value);
            }
        }
        if (! empty($input['q'])) {
            $query->where(function (Builder $search) use ($fields, $input, $table) {
                foreach ($fields as $field) {
                    if ($this->relatedColumn($search, $table, $field, $input['q'], true)) {
                        continue;
                    }
                    $search->orWhereRaw($search->getGrammar()->wrap($field)." LIKE ? ESCAPE '!'", [$this->pattern($input['q'])]);
                }
            });
        }
    }
}
