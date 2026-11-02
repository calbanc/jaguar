<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\Support\HseDatabase;
use Tests\TestCase;

class WarehouseModuleTest extends TestCase
{
    use HseDatabase;

    public function test_migration_preserves_disabled_modules_and_copies_only_existing_role_actions(): void
    {
        $this->installHseFixture();
        $tenant = $this->tenant('alpha');
        $module = DB::table('modulos')->where('codigo', 'almacenes')->value('id');
        $ids = DB::table('permisos')->where('modulo_id', $module)->pluck('id');
        DB::table('rol_permisos')->whereIn('permiso_id', $ids)->delete();
        DB::table('permisos')->whereIn('id', $ids)->delete();
        DB::table('empresa_modulos')->where('modulo_id', $module)->delete();
        DB::table('modulos')->where('id', $module)->delete();
        $epp = DB::table('modulos')->where('codigo', 'epp')->value('id');
        DB::table('empresa_modulos')->where('modulo_id', $epp)->update(['activo' => false]);
        $create = DB::table('permisos')->where('codigo', 'epp.crear')->value('id');
        DB::table('rol_permisos')->where('permiso_id', $create)->delete();
        $migration = require database_path('migrations/2026_10_08_013329_add_almacenes_module.php');
        $migration->up();
        $migration->up();
        $module = DB::table('modulos')->where('codigo', 'almacenes')->value('id');
        $this->assertDatabaseHas('empresa_modulos', ['empresa_id' => $tenant['company'], 'modulo_id' => $module, 'activo' => false]);
        $read = DB::table('permisos')->where('codigo', 'almacenes.ver')->value('id');
        $create = DB::table('permisos')->where('codigo', 'almacenes.crear')->value('id');
        $this->assertDatabaseHas('rol_permisos', ['rol_id' => $tenant['role'], 'permiso_id' => $read]);
        $this->assertDatabaseMissing('rol_permisos', ['rol_id' => $tenant['role'], 'permiso_id' => $create]);
        $this->assertSame(5, DB::table('permisos')->where('modulo_id', $module)->count());
    }

    public function test_warehouse_only_role_can_manage_inventory_but_not_worker_deliveries(): void
    {
        $this->installHseFixture();
        $tenant = $this->tenant('alpha');
        $ids = DB::table('permisos')->where('codigo', 'not like', 'almacenes.%')->pluck('id');
        DB::table('rol_permisos')->where('rol_id', $tenant['role'])->whereIn('permiso_id', $ids)->delete();
        $this->loginAsTenant($tenant);
        foreach (['almacenes/opciones', 'almacenes', 'epp_catalogo', 'epp_movimientos', 'reportes/stock'] as $resource) {
            $this->getJson('/api/v1/'.$resource)->assertOk();
        }
        $this->get('/api/v1/exportar/reportes-stock')->assertOk();
        $this->postJson('/api/v1/inventario/movimientos', [])->assertUnprocessable();
        $this->getJson('/api/v1/epp_entregas')->assertForbidden();
        $this->postJson('/api/v1/inventario/devoluciones', [])->assertForbidden();
    }
}
