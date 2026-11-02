<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\HseDatabase;
use Tests\TestCase;

class EppDeliveryTest extends TestCase
{
    use HseDatabase;

    private function setupDelivery(): array
    {
        $this->installHseFixture();
        $tenant = $this->tenant('alpha');
        $this->loginAsTenant($tenant);
        $site = DB::table('sedes')->insertGetId(['empresa_id' => $tenant['company'], 'codigo' => 'PIU', 'nombre' => 'Piura', 'pais' => 'PE']);
        $warehouse = DB::table('almacenes')->insertGetId(['empresa_id' => $tenant['company'], 'sede_id' => $site, 'nombre' => 'Central']);
        $item = DB::table('epp_catalogo')->insertGetId(['empresa_id' => $tenant['company'], 'codigo' => 'CAS', 'nombre' => 'Casco']);
        $worker = DB::table('trabajadores')->insertGetId(['empresa_id' => $tenant['company'], 'tipo_documento' => 'DNI', 'numero_documento' => '01234567', 'nombres' => 'Ana', 'apellidos' => 'Prueba']);
        $this->postJson('/api/v1/compras-epp', ['clave_operacion' => (string) Str::uuid(), 'proveedor' => 'Proveedor', 'factura' => 'F001', 'fecha' => '2026-10-09', 'detalles' => [['epp_id' => $item, 'almacen_id' => $warehouse, 'cantidad' => 10, 'costo_unitario' => 5]]])->assertCreated();
        $input = ['uuid_cliente' => (string) Str::uuid(), 'trabajador_id' => $worker, 'epp_id' => $item, 'almacen_id' => $warehouse, 'cantidad' => 2, 'motivo' => 'reposicion', 'fecha' => '2026-10-09', 'hora' => '09:30', 'entregado_por' => $tenant['member']];

        return [$tenant, $input];
    }

    public function test_complete_delivery_records_reason_responsible_time_and_stock_once(): void
    {
        [$tenant, $input] = $this->setupDelivery();
        $this->getJson('/api/v1/entregas-epp/opciones')->assertOk()->assertJsonPath('proxima_planilla', 1)->assertJsonPath('responsable_actual', $tenant['member'])->assertJsonCount(1, 'trabajadores');
        $id = $this->postJson('/api/v1/entregas-epp', $input)->assertCreated()->assertJsonPath('data.estado', 'confirmada')->assertJsonPath('data.motivo', 'reposicion')->assertJsonPath('data.responsable', 'alpha')->assertJsonPath('data.fecha', '2026-10-09 14:30:00')->json('data.id');
        $this->assertDatabaseHas('epp_entrega_detalles', ['entrega_id' => $id, 'epp_id' => $input['epp_id'], 'almacen_id' => $input['almacen_id'], 'cantidad' => 2]);
        $this->postJson('/api/v1/entregas-epp', $input)->assertOk()->assertJsonPath('data.id', $id);
        $this->assertDatabaseCount('epp_entregas', 1);
        $this->getJson('/api/v1/entregas-epp/opciones')->assertOk()->assertJsonPath('proxima_planilla', 2);
        $this->assertEquals(8, DB::table('epp_movimientos')->sum('cantidad'));
        $this->postJson('/api/v1/entregas-epp', array_replace($input, ['cantidad' => 3]))->assertConflict();
        $this->getJson('/api/v1/epp_entregas?columns[trabajador]=01234567&columns[responsable]=alpha')->assertOk()->assertJsonPath('total', 1);
        $this->postJson('/api/v1/epp_entregas/'.$id.'/anular')->assertOk();
        $this->assertEquals(10, DB::table('epp_movimientos')->sum('cantidad'));
    }

    public function test_invalid_input_or_insufficient_stock_leaves_no_partial_delivery(): void
    {
        [, $input] = $this->setupDelivery();
        foreach ([['cantidad' => 11], ['cantidad' => 0], ['motivo' => 'otro'], ['hora' => '25:00'], ['fecha' => '2026-02-30'], ['entregado_por' => 999999], ['almacen_id' => 999999], ['trabajador_id' => 999999]] as $invalid) {
            $this->postJson('/api/v1/entregas-epp', array_replace($input, $invalid))->assertUnprocessable();
            $this->assertDatabaseCount('epp_entregas', 0);
            $this->assertDatabaseCount('epp_entrega_detalles', 0);
            $this->assertEquals(10, DB::table('epp_movimientos')->sum('cantidad'));
        }
    }

    public function test_other_company_and_inactive_users_cannot_be_responsible(): void
    {
        [$tenant, $input] = $this->setupDelivery();
        $other = $this->tenant('beta');
        $this->postJson('/api/v1/entregas-epp', array_replace($input, ['entregado_por' => $other['member']]))->assertUnprocessable();
        DB::table('usuarios')->where('id', $other['user'])->update(['activo' => false]);
        $member = DB::table('miembros_empresa')->insertGetId(['empresa_id' => $tenant['company'], 'usuario_id' => $other['user']]);
        $this->postJson('/api/v1/entregas-epp', array_replace($input, ['entregado_por' => $member]))->assertUnprocessable();
        $permission = DB::table('permisos')->where('codigo', 'epp.crear')->value('id');
        DB::table('rol_permisos')->where('rol_id', $tenant['role'])->where('permiso_id', $permission)->delete();
        $this->postJson('/api/v1/entregas-epp', $input)->assertForbidden();
        $this->getJson('/api/v1/entregas-epp/opciones')->assertForbidden();
    }
}
