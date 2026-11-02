<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class HseAccess
{
    public function context(int $accessId): object
    {
        $access = DB::table('accesos_aplicacion as a')
            ->join('empresas as e', 'e.id', '=', 'a.empresa_id')
            ->join('miembros_empresa as m', function ($join) {
                $join->on('m.id', '=', 'a.miembro_id')->on('m.empresa_id', '=', 'a.empresa_id');
            })
            ->join('usuarios as u', 'u.id', '=', 'm.usuario_id')
            ->join('roles as r', function ($join) {
                $join->on('r.id', '=', 'a.rol_id')->on('r.empresa_id', '=', 'a.empresa_id');
            })
            ->join('plataformas as p', 'p.id', '=', 'a.plataforma_id')
            ->where('a.id', $accessId)->where('a.activo', true)->where('e.activo', true)
            ->where('m.activo', true)->where('u.activo', true)->where('r.activo', true)
            ->select('a.id as acceso_id', 'a.empresa_id', 'a.miembro_id', 'a.rol_id', 'u.id as usuario_id',
                'u.nombre', 'u.email', 'e.zona_horaria', 'p.codigo as plataforma')->first();
        abort_unless($access, 401, 'Acceso inactivo o inexistente.');
        $today = CarbonImmutable::now($access->zona_horaria)->toDateString();
        $contracts = DB::table('contratos')->where('empresa_id', $access->empresa_id)
            ->whereIn('estado', ['activo', 'prueba'])->where('fecha_inicio', '<=', $today)
            ->where(fn ($query) => $query->whereNull('fecha_fin')->orWhere('fecha_fin', '>=', $today))->get();
        abort_unless($contracts->count() === 1, 403, 'Se requiere un único contrato vigente.');
        $access->contrato = $contracts->first();
        $limit = $access->contrato->limite_usuarios;
        abort_if($limit !== null && DB::table('miembros_empresa')->where('empresa_id', $access->empresa_id)
            ->where('activo', true)->count() > $limit, 403, 'Límite de usuarios excedido.');

        return $access;
    }

    public function authorize(object $context, string $module, string $action): void
    {
        $allowed = DB::table('rol_permisos as rp')->join('permisos as p', 'p.id', '=', 'rp.permiso_id')
            ->join('modulos as m', 'm.id', '=', 'p.modulo_id')
            ->join('empresa_modulos as em', function ($join) use ($context) {
                $join->on('em.modulo_id', '=', 'm.id')->where('em.empresa_id', $context->empresa_id);
            })
            ->where('rp.empresa_id', $context->empresa_id)->where('rp.rol_id', $context->rol_id)
            ->where('m.codigo', $module)->where('p.codigo', $module.'.'.$action)->where('em.activo', true)->exists();
        abort_unless($allowed, 403, 'Módulo o permiso no habilitado.');
    }
}
