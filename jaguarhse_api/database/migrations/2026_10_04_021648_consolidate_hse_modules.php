<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MODULES = [
        'administracion' => 'Administración', 'documentos' => 'Documentos',
        'indicadores' => 'Indicadores', 'epp' => 'EPP', 'hallazgos' => 'Hallazgos',
        'gestion_sst' => 'Gestión SST', 'organizacion' => 'Organización', 'inspecciones' => 'Inspecciones',
    ];

    public function up(): void
    {
        DB::transaction(function () {
            foreach (self::MODULES as $code => $name) {
                DB::table('modulos')->updateOrInsert(['codigo' => $code], ['nombre' => $name]);
            }
            $ids = DB::table('modulos')->pluck('id', 'codigo');
            $this->transferAccess($ids['administracion'], $ids['organizacion'], 'organizacion');
            $this->transferAccess($ids['documentos'], $ids['gestion_sst'], 'gestion_sst');
            $legacy = ['checklists', 'liderazgo', 'planificacion', 'comite', 'risst', 'procedimientos',
                'capacitaciones', 'incidentes', 'salud', 'emergencias', 'alto_riesgo', 'equipos',
                'vehiculos', 'quimicos', 'contratistas', 'auditorias', 'comunicacion', 'ambiente'];
            foreach ($legacy as $code) {
                if (! isset($ids[$code])) {
                    continue;
                }
                $targetCode = $code === 'checklists' ? 'inspecciones' : 'gestion_sst';
                $source = $ids[$code];
                $target = $ids[$targetCode];
                $this->transferAccess($source, $target, $targetCode);
                DB::table('temas_hse')->where('modulo_id', $source)->update(['modulo_id' => $target]);
                $permissions = DB::table('permisos')->where('modulo_id', $source)->pluck('id');
                DB::table('rol_permisos')->whereIn('permiso_id', $permissions)->delete();
                DB::table('permisos')->where('modulo_id', $source)->delete();
                DB::table('empresa_modulos')->where('modulo_id', $source)->delete();
                DB::table('modulos')->where('id', $source)->delete();
            }
            foreach (self::MODULES as $code => $name) {
                foreach (['ver', 'crear', 'editar', 'revisar', 'publicar', 'confirmar', 'gestionar', 'confidenciales'] as $action) {
                    DB::table('permisos')->insertOrIgnore(['modulo_id' => $ids[$code],
                        'codigo' => $code.'.'.$action, 'descripcion' => $action.' '.$name]);
                }
            }
        });
    }

    private function transferAccess(int $source, int $target, string $targetCode): void
    {
        foreach (DB::table('empresa_modulos')->where('modulo_id', $source)->get() as $enabled) {
            DB::table('empresa_modulos')->insertOrIgnore(['empresa_id' => $enabled->empresa_id,
                'modulo_id' => $target, 'activo' => $enabled->activo]);
            if ($enabled->activo) {
                DB::table('empresa_modulos')->where('empresa_id', $enabled->empresa_id)
                    ->where('modulo_id', $target)->update(['activo' => true]);
            }
        }
        foreach (DB::table('permisos')->where('modulo_id', $source)->get() as $permission) {
            $action = explode('.', $permission->codigo, 2)[1];
            $code = $targetCode.'.'.$action;
            DB::table('permisos')->insertOrIgnore(['modulo_id' => $target, 'codigo' => $code, 'descripcion' => $action.' '.$targetCode]);
            $targetPermission = DB::table('permisos')->where('codigo', $code)->value('id');
            foreach (DB::table('rol_permisos')->where('permiso_id', $permission->id)->get() as $grant) {
                DB::table('rol_permisos')->insertOrIgnore(['empresa_id' => $grant->empresa_id,
                    'rol_id' => $grant->rol_id, 'permiso_id' => $targetPermission]);
            }
        }
    }

    public function down(): void
    {
        throw new RuntimeException('La consolidación de módulos conserva datos y permisos; su reversión requiere restaurar un respaldo.');
    }
};
