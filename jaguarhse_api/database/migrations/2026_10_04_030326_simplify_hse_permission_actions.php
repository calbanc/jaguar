<?php

use App\Services\HsePermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $grants = DB::table('rol_permisos as rp')->join('permisos as p', 'p.id', '=', 'rp.permiso_id')
                ->select('rp.empresa_id', 'rp.rol_id', 'p.codigo')->get();
            app(HsePermissions::class)->synchronize(updateAdministrators: true);
            foreach ($grants as $grant) {
                [$module, $action] = explode('.', $grant->codigo, 2);
                $targets = match ($action) {
                    'ver' => ['descargar'],
                    'editar' => ['eliminar'],
                    default => [],
                };
                foreach ($targets as $target) {
                    $id = DB::table('permisos')->where('codigo', $module.'.'.$target)->value('id');
                    if ($id) {
                        DB::table('rol_permisos')->insertOrIgnore(['empresa_id' => $grant->empresa_id,
                            'rol_id' => $grant->rol_id, 'permiso_id' => $id]);
                    }
                }
            }
        });
    }

    public function down(): void
    {
        throw new RuntimeException('La simplificación de permisos requiere restaurar el respaldo para recuperar las asignaciones anteriores.');
    }
};
