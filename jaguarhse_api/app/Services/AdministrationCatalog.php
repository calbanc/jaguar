<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdministrationCatalog
{
    public const COLUMNS = [
        'usuarios' => ['nombre' => 'Usuario', 'email' => 'Correo electrónico', 'roles' => 'Roles y plataformas', 'activo' => 'Estado'],
        'modulos' => ['nombre' => 'Módulo', 'codigo' => 'Código', 'activo' => 'Habilitado en la empresa'],
        'roles' => ['nombre' => 'Rol', 'descripcion' => 'Descripción', 'permisos_detalle' => 'Permisos', 'activo' => 'Estado'],
        'permisos' => ['modulo' => 'Módulo', 'descripcion' => 'Permiso', 'codigo' => 'Código'],
        'permisos-usuarios' => ['usuario' => 'Usuario', 'email' => 'Correo electrónico', 'rol' => 'Rol', 'plataforma' => 'Plataforma', 'permisos_detalle' => 'Permisos heredados', 'activo' => 'Estado'],
        'permisos-roles' => ['rol' => 'Rol', 'modulo' => 'Módulo', 'descripcion' => 'Permiso'],
    ];

    public function query(string $section, object $context, string $search = '', array $columns = []): Builder
    {
        abort_unless(isset(self::COLUMNS[$section]), 404);
        [$query, $fields, $order] = match ($section) {
            'usuarios' => [DB::table('miembros_empresa as m')->join('usuarios as u', 'u.id', '=', 'm.usuario_id')
                ->where('m.empresa_id', $context->empresa_id)->select('m.id', 'm.usuario_id', 'm.activo', 'u.nombre', 'u.email', 'u.activo as identidad_activa', 'm.created_at'), ['u.nombre', 'u.email'], 'u.nombre'],
            'modulos' => [DB::table('modulos as m')->leftJoin('empresa_modulos as em', function ($join) use ($context) {
                $join->on('em.modulo_id', '=', 'm.id')->where('em.empresa_id', $context->empresa_id);
            })->select('m.id', 'm.nombre', 'm.codigo')->selectRaw('COALESCE(em.activo, 0) as activo'), ['m.nombre', 'm.codigo'], 'm.nombre'],
            'roles' => [DB::table('roles as r')->where('r.empresa_id', $context->empresa_id)->select('r.id', 'r.nombre', 'r.descripcion', 'r.activo'), ['r.nombre', 'r.descripcion'], 'r.nombre'],
            'permisos' => [DB::table('permisos as p')->join('modulos as m', 'm.id', '=', 'p.modulo_id')
                ->select('p.id', 'p.modulo_id', 'p.codigo', 'p.descripcion', 'm.nombre as modulo'), ['p.descripcion', 'p.codigo', 'm.nombre'], 'p.codigo'],
            'permisos-usuarios' => [DB::table('accesos_aplicacion as a')
                ->join('miembros_empresa as m', function ($join) {
                    $join->on('m.id', '=', 'a.miembro_id')->on('m.empresa_id', '=', 'a.empresa_id');
                })
                ->join('usuarios as u', 'u.id', '=', 'm.usuario_id')
                ->join('roles as r', function ($join) {
                    $join->on('r.id', '=', 'a.rol_id')->on('r.empresa_id', '=', 'a.empresa_id');
                })
                ->join('plataformas as p', 'p.id', '=', 'a.plataforma_id')->where('a.empresa_id', $context->empresa_id)
                ->select('a.id', 'a.miembro_id', 'a.rol_id', 'a.plataforma_id', 'a.activo', 'u.nombre as usuario', 'u.email', 'r.nombre as rol', 'r.activo as rol_activo', 'm.activo as usuario_activo', 'p.nombre as plataforma'), ['u.nombre', 'u.email', 'r.nombre', 'p.nombre'], 'u.nombre'],
            'permisos-roles' => [DB::table('rol_permisos as rp')
                ->join('roles as r', function ($join) {
                    $join->on('r.id', '=', 'rp.rol_id')->on('r.empresa_id', '=', 'rp.empresa_id');
                })
                ->join('permisos as p', 'p.id', '=', 'rp.permiso_id')->join('modulos as m', 'm.id', '=', 'p.modulo_id')
                ->where('rp.empresa_id', $context->empresa_id)->select('rp.id', 'rp.rol_id', 'rp.permiso_id', 'r.nombre as rol', 'm.nombre as modulo', 'p.descripcion', 'p.codigo'), ['r.nombre', 'm.nombre', 'p.descripcion', 'p.codigo'], 'r.nombre'],
        };
        if ($search !== '') {
            $query->where(function ($filter) use ($fields, $search) {
                foreach ($fields as $field) {
                    $filter->orWhere($field, 'like', '%'.$search.'%');
                }
            });
        }

        $this->filterColumns($query, $section, $context, $columns);

        return $query->orderBy($order)->orderBy(match ($section) {
            'usuarios', 'modulos' => 'm.id', 'roles' => 'r.id', 'permisos' => 'p.id', 'permisos-usuarios' => 'a.id', default => 'rp.id',
        });
    }

    private function filterColumns(Builder $query, string $section, object $context, array $columns): void
    {
        $fields = match ($section) {
            'usuarios' => ['nombre' => 'u.nombre', 'email' => 'u.email', 'activo' => 'm.activo'],
            'modulos' => ['nombre' => 'm.nombre', 'codigo' => 'm.codigo', 'activo' => 'em.activo'],
            'roles' => ['nombre' => 'r.nombre', 'descripcion' => 'r.descripcion', 'activo' => 'r.activo'],
            'permisos' => ['modulo' => 'm.nombre', 'descripcion' => 'p.descripcion', 'codigo' => 'p.codigo'],
            'permisos-usuarios' => ['usuario' => 'u.nombre', 'email' => 'u.email', 'rol' => 'r.nombre', 'plataforma' => 'p.nombre', 'activo' => 'a.activo'],
            'permisos-roles' => ['rol' => 'r.nombre', 'modulo' => 'm.nombre', 'descripcion' => 'p.descripcion'],
        };
        $filters = app(TableFilters::class);
        foreach ($columns as $key => $value) {
            if ($section === 'permisos' && in_array($key, ['ver', 'crear', 'editar', 'eliminar', 'descargar'], true)) {
                if ($value === '' || $value === null) {
                    continue;
                }
                abort_unless(in_array($value, ['0', '1'], true), 422, 'Disponibilidad inválida.');
                $query->whereExists(function ($q) use ($key) {
                    $q->selectRaw('1')->from('permisos as fp')->whereColumn('fp.modulo_id', 'm.id')
                        ->where('fp.codigo', 'like', '%.'.$key);
                }, 'and', $value === '0');

                continue;
            }
            abort_unless(array_key_exists($key, self::COLUMNS[$section]), 422, 'Columna de filtro inválida.');
            if ($value === null || $value === '') {
                continue;
            }
            if ($key === 'activo') {
                abort_unless(in_array($value, ['0', '1'], true), 422, 'Estado inválido.');
                if ($section === 'modulos' && $value === '0') {
                    $query->where(fn ($q) => $q->where('em.activo', 0)->orWhereNull('em.activo'));
                } else {
                    $query->where($fields[$key], (int) $value);
                }
            } elseif ($key === 'roles') {
                $query->whereExists(function ($q) use ($context, $value, $filters) {
                    $q->selectRaw('1')->from('accesos_aplicacion as fa')->join('roles as fr', 'fr.id', '=', 'fa.rol_id')
                        ->join('plataformas as fp', 'fp.id', '=', 'fa.plataforma_id')
                        ->whereColumn('fa.miembro_id', 'm.id')->where('fa.empresa_id', $context->empresa_id)
                        ->where(function ($match) use ($value, $filters) {
                            $match->whereRaw("fr.nombre LIKE ? ESCAPE '!'", [$filters->pattern($value)])
                                ->orWhereRaw("fp.nombre LIKE ? ESCAPE '!'", [$filters->pattern($value)]);
                        });
                });
            } elseif ($key === 'permisos_detalle') {
                $query->whereExists(function ($q) use ($section, $context, $value, $filters) {
                    $q->selectRaw('1')->from('rol_permisos as frp')->join('permisos as fperm', 'fperm.id', '=', 'frp.permiso_id')
                        ->where('frp.empresa_id', $context->empresa_id)->whereColumn('frp.rol_id', $section === 'roles' ? 'r.id' : 'a.rol_id');
                    $filters->contains($q, 'fperm.descripcion', $value);
                });
            } else {
                $filters->contains($query, $fields[$key], $value);
            }
        }
    }

    public function enrich(string $section, Collection $rows, object $context): Collection
    {
        if (in_array($section, ['roles', 'permisos-usuarios'], true)) {
            $ids = $rows->pluck($section === 'roles' ? 'id' : 'rol_id');
            $permissions = DB::table('rol_permisos as rp')->join('permisos as p', 'p.id', '=', 'rp.permiso_id')
                ->join('modulos as m', 'm.id', '=', 'p.modulo_id')
                ->leftJoin('empresa_modulos as em', function ($join) use ($context) {
                    $join->on('em.modulo_id', '=', 'm.id')->where('em.empresa_id', $context->empresa_id);
                })
                ->where('rp.empresa_id', $context->empresa_id)->whereIn('rp.rol_id', $ids)
                ->select('rp.rol_id', 'p.id', 'p.descripcion', 'm.nombre as modulo')->selectRaw('COALESCE(em.activo, 0) as habilitado')->orderBy('p.codigo')->get()->groupBy('rol_id');
            foreach ($rows as $row) {
                $items = $permissions->get($section === 'roles' ? $row->id : $row->rol_id, collect());
                $row->permisos = $items->values()->all();
                $row->permisos_detalle = $items->map(fn ($p) => $p->descripcion.($p->habilitado ? '' : ' (módulo deshabilitado)'))->implode("\n");
            }
        }
        if ($section === 'usuarios') {
            $accesses = DB::table('accesos_aplicacion as a')->join('roles as r', 'r.id', '=', 'a.rol_id')->join('plataformas as p', 'p.id', '=', 'a.plataforma_id')
                ->where('a.empresa_id', $context->empresa_id)->whereIn('a.miembro_id', $rows->pluck('id'))
                ->select('a.miembro_id', 'a.activo', 'r.nombre as rol', 'p.nombre as plataforma')->get()->groupBy('miembro_id');
            foreach ($rows as $row) {
                $row->roles = $accesses->get($row->id, collect())->map(fn ($a) => $a->rol.' · '.$a->plataforma.($a->activo ? '' : ' (inactivo)'))->implode("\n");
            }
        }

        return $rows;
    }
}
