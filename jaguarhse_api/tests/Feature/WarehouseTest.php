<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\Support\HseDatabase;
use Tests\TestCase;

class WarehouseTest extends TestCase
{
    use HseDatabase;

    public function test_warehouse_crud_filters_and_tenant_boundaries(): void
    {
        $this->installHseFixture();
        $tenant = $this->tenant('alpha');
        $other = $this->tenant('beta');
        $site = DB::table('sedes')->insertGetId(['pais' => 'PE', 'empresa_id' => $tenant['company'], 'codigo' => 'LIM', 'nombre' => 'Lima Central']);
        $foreign = DB::table('sedes')->insertGetId(['pais' => 'PE', 'empresa_id' => $other['company'], 'codigo' => 'CUS', 'nombre' => 'Cusco']);
        $this->loginAsTenant($tenant);
        $this->postJson('/api/v1/almacenes', ['sede_id' => $foreign, 'nombre' => 'Ajeno'])->assertUnprocessable();
        $this->postJson('/api/v1/almacenes', ['sede_id' => $site])->assertUnprocessable();
        $id = $this->postJson('/api/v1/almacenes', ['sede_id' => $site, 'nombre' => 'Principal'])->assertCreated()->assertJsonPath('data.sede_nombre', 'Lima Central')->json('data.id');
        $this->getJson('/api/v1/almacenes?columns[sede_nombre]=Lima')->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/api/v1/almacenes?q=Lima')->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/api/v1/almacenes?columns[sede_nombre]=Cusco')->assertOk()->assertJsonPath('total', 0);
        $this->get('/api/v1/exportar/almacenes?columns[sede_nombre]=Lima')->assertOk()->assertDownload('almacenes.xlsx');
        $this->patchJson('/api/v1/almacenes/'.$id, ['nombre' => 'Central'])->assertOk()->assertJsonPath('data.nombre', 'Central');
        $this->deleteJson('/api/v1/almacenes/'.$id)->assertOk();
        $this->assertDatabaseHas('almacenes', ['id' => $id, 'activo' => false]);
        $this->getJson('/api/v1/inventario/almacenes')->assertOk()->assertJsonCount(0, 'data');
        $this->loginAsTenant($other);
        $this->getJson('/api/v1/almacenes/'.$id)->assertNotFound();
    }

    public function test_management_uses_warehouse_permissions_but_epp_keeps_its_options(): void
    {
        $this->installHseFixture();
        $tenant = $this->tenant('alpha');
        $site = DB::table('sedes')->insertGetId(['pais' => 'PE', 'empresa_id' => $tenant['company'], 'codigo' => 'LIM', 'nombre' => 'Lima']);
        DB::table('almacenes')->insert(['empresa_id' => $tenant['company'], 'sede_id' => $site, 'nombre' => 'Principal']);
        $permissions = DB::table('permisos')->where('codigo', 'like', 'almacenes.%')->pluck('id');
        DB::table('rol_permisos')->where('rol_id', $tenant['role'])->whereIn('permiso_id', $permissions)->delete();
        $this->loginAsTenant($tenant);
        $this->getJson('/api/v1/almacenes')->assertForbidden();
        $this->postJson('/api/v1/almacenes', ['sede_id' => $site, 'nombre' => 'Otro'])->assertForbidden();
        $this->getJson('/api/v1/inventario/almacenes')->assertOk()->assertJsonPath('data.0.nombre', 'Principal');
    }
}
