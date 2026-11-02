<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class HsePermissions
{
    public const ADMIN_DESCRIPTION = 'Administración de los módulos habilitados para la empresa.';

    public static function actionsFor(string $module): array
    {
        return config('hse.permission_actions') + ($module === 'documentos' ? ['confidenciales' => 'Acceder a documentos confidenciales'] : []);
    }

    public function synchronize(bool $updateAdministrators = false): void
    {
        DB::transaction(function () use ($updateAdministrators) {
            foreach (config('hse.modules') as $code => $name) {
                DB::table('modulos')->updateOrInsert(['codigo' => $code], ['nombre' => $name]);
                $module = DB::table('modulos')->where('codigo', $code)->value('id');
                foreach (self::actionsFor($code) as $action => $label) {
                    DB::table('permisos')->updateOrInsert(['codigo' => $code.'.'.$action],
                        ['modulo_id' => $module, 'descripcion' => $label.' · '.$name]);
                }
                foreach (DB::table('permisos')->where('modulo_id', $module)->get() as $permission) {
                    $action = substr($permission->codigo, strlen($code) + 1);
                    if (array_key_exists($action, self::actionsFor($code))) {
                        continue;
                    }
                    $targets = match ($action) {
                        'exportar' => ['descargar'],
                        'gestionar' => $code === 'administracion' ? ['crear', 'editar', 'eliminar'] : [],
                        'revisar', 'aprobar' => in_array($code, ['documentos', 'hallazgos', 'inspecciones']) ? ['editar'] : [],
                        'publicar' => $code === 'inspecciones' ? ['editar', 'eliminar'] : [],
                        'confirmar' => $code === 'epp' ? ['crear', 'editar', 'eliminar'] : [],
                        'anular' => ['eliminar'],
                        default => [],
                    };
                    foreach ($targets as $target) {
                        $targetId = DB::table('permisos')->where('codigo', $code.'.'.$target)->value('id');
                        foreach (DB::table('rol_permisos')->where('permiso_id', $permission->id)->get() as $grant) {
                            DB::table('rol_permisos')->insertOrIgnore(['empresa_id' => $grant->empresa_id,
                                'rol_id' => $grant->rol_id, 'permiso_id' => $targetId]);
                        }
                    }
                    DB::table('rol_permisos')->where('permiso_id', $permission->id)->delete();
                    DB::table('permisos')->where('id', $permission->id)->delete();
                }
            }
            if (! $updateAdministrators) {
                return;
            }
            foreach (DB::table('roles')->where('nombre', 'Administrador')->get() as $role) {
                if ($role->descripcion === null || $role->descripcion === '') {
                    DB::table('roles')->where('id', $role->id)->update([
                        'descripcion' => self::ADMIN_DESCRIPTION, 'updated_at' => now(),
                    ]);
                }
                $permissions = DB::table('permisos as p')->join('modulos as m', 'm.id', '=', 'p.modulo_id')
                    ->join('empresa_modulos as em', 'em.modulo_id', '=', 'm.id')
                    ->where('em.empresa_id', $role->empresa_id)->where('em.activo', true)
                    ->whereIn('m.codigo', array_keys(config('hse.modules')))->select('p.id', 'p.codigo', 'm.codigo as module')->get();
                foreach ($permissions as $permission) {
                    $action = substr($permission->codigo, strlen($permission->module) + 1);
                    if (! array_key_exists($action, self::actionsFor($permission->module))) {
                        continue;
                    }
                    DB::table('rol_permisos')->insertOrIgnore(['empresa_id' => $role->empresa_id,
                        'rol_id' => $role->id, 'permiso_id' => $permission->id]);
                }
            }
        });
    }
}
