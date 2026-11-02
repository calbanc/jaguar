<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            DB::table('modulos')->updateOrInsert(['codigo' => 'capacitaciones'], ['nombre' => 'Capacitaciones']);
            $module = DB::table('modulos')->where('codigo', 'capacitaciones')->value('id');
            $source = DB::table('modulos')->where('codigo', 'gestion_sst')->value('id');
            foreach (DB::table('empresa_modulos')->where('modulo_id', $source)->get() as $enabled) {
                DB::table('empresa_modulos')->insertOrIgnore(['empresa_id' => $enabled->empresa_id,
                    'modulo_id' => $module, 'activo' => $enabled->activo]);
            }
            foreach (['ver', 'crear', 'editar', 'revisar', 'publicar', 'confirmar', 'gestionar', 'confidenciales'] as $action) {
                $code = 'capacitaciones.'.$action;
                DB::table('permisos')->insertOrIgnore(['modulo_id' => $module, 'codigo' => $code,
                    'descripcion' => $action.' Capacitaciones']);
                $permission = DB::table('permisos')->where('codigo', $code)->value('id');
                $grants = DB::table('rol_permisos as rp')->join('permisos as p', 'p.id', '=', 'rp.permiso_id')
                    ->where('p.modulo_id', $source)->where('p.codigo', 'gestion_sst.'.$action)->select('rp.*')->get();
                foreach ($grants as $grant) {
                    DB::table('rol_permisos')->insertOrIgnore(['empresa_id' => $grant->empresa_id,
                        'rol_id' => $grant->rol_id, 'permiso_id' => $permission]);
                }
            }
        });
    }

    public function down(): void
    {
        throw new RuntimeException('El módulo Capacitaciones conserva sus asignaciones; su reversión requiere un procedimiento manual.');
    }
};
