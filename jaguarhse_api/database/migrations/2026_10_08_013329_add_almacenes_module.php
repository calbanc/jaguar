<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            DB::table('modulos')->updateOrInsert(['codigo' => 'almacenes'], ['nombre' => 'Almacenes']);
            $module = DB::table('modulos')->where('codigo', 'almacenes')->value('id');
            $source = DB::table('modulos')->where('codigo', 'epp')->value('id');
            foreach (DB::table('empresa_modulos')->where('modulo_id', $source)->get() as $enabled) {
                DB::table('empresa_modulos')->insertOrIgnore(['empresa_id' => $enabled->empresa_id,
                    'modulo_id' => $module, 'activo' => $enabled->activo]);
            }
            foreach (['ver' => 'Consultar', 'crear' => 'Crear', 'editar' => 'Editar', 'eliminar' => 'Eliminar', 'descargar' => 'Descargar informes'] as $action => $label) {
                DB::table('permisos')->updateOrInsert(['codigo' => 'almacenes.'.$action],
                    ['modulo_id' => $module, 'descripcion' => $label.' · Almacenes']);
                $permission = DB::table('permisos')->where('codigo', 'almacenes.'.$action)->value('id');
                $grants = DB::table('rol_permisos as rp')->join('permisos as p', 'p.id', '=', 'rp.permiso_id')
                    ->where('p.modulo_id', $source)->where('p.codigo', 'epp.'.$action)->select('rp.*')->get();
                foreach ($grants as $grant) {
                    DB::table('rol_permisos')->insertOrIgnore(['empresa_id' => $grant->empresa_id,
                        'rol_id' => $grant->rol_id, 'permiso_id' => $permission]);
                }
            }
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Almacenes conserva sus permisos; la reversión requiere un procedimiento manual.');
    }
};
