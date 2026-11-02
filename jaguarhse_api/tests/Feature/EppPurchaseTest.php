<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\HseDatabase;
use Tests\TestCase;

class EppPurchaseTest extends TestCase
{
    use HseDatabase;

    private array $tenant;

    private int $item;

    private int $warehouse;

    private int $worker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installHseFixture();
        $this->tenant = $this->tenant('alpha');
        $this->loginAsTenant($this->tenant);
        $site = DB::table('sedes')->insertGetId(['pais' => 'PE', 'empresa_id' => $this->tenant['company'], 'codigo' => 'LIM', 'nombre' => 'Lima']);
        $this->warehouse = DB::table('almacenes')->insertGetId(['empresa_id' => $this->tenant['company'], 'sede_id' => $site, 'nombre' => 'Central']);
        $this->item = DB::table('epp_catalogo')->insertGetId(['empresa_id' => $this->tenant['company'], 'codigo' => 'CAS', 'nombre' => 'Casco']);
        $this->worker = DB::table('trabajadores')->insertGetId(['empresa_id' => $this->tenant['company'], 'tipo_documento' => 'DNI', 'numero_documento' => '12345678', 'nombres' => 'Ana', 'apellidos' => 'Pérez']);
    }

    private function purchase(string $invoice, string $quantity, string $cost): array
    {
        return ['clave_operacion' => (string) Str::uuid(), 'proveedor' => 'Proveedor de equipos', 'proveedor_ruc' => '20123456789',
            'factura' => $invoice, 'fecha' => '2026-10-07', 'detalles' => [
                ['epp_id' => $this->item, 'almacen_id' => $this->warehouse, 'cantidad' => $quantity, 'costo_unitario' => $cost],
            ]];
    }

    private function delivery(string $quantity): array
    {
        $id = $this->postJson('/api/v1/epp_entregas', ['uuid_cliente' => (string) Str::uuid(), 'trabajador_id' => $this->worker, 'fecha' => '2026-10-07 12:00:00'])
            ->assertCreated()->json('data.id');
        $detail = $this->postJson('/api/v1/epp_entrega_detalles', ['entrega_id' => $id, 'epp_id' => $this->item, 'almacen_id' => $this->warehouse, 'cantidad' => $quantity])
            ->assertCreated()->json('data.id');

        return [$id, $detail];
    }

    public function test_monthly_movements_include_purchases_outputs_and_returns_with_reference_usd(): void
    {
        $this->travelTo(Carbon::parse('2026-10-01 04:59:59', 'UTC'));
        $this->loginAsTenant($this->tenant);
        $this->postJson('/api/v1/compras-epp', $this->purchase('PREVIOUS', '1', '20'))->assertCreated();
        $this->travelTo(Carbon::parse('2026-10-01 05:00:00', 'UTC'));
        $this->postJson('/api/v1/compras-epp', $this->purchase('CURRENT', '100', '20'))->assertCreated();
        [$delivery, $detail] = $this->delivery('3');
        $this->postJson('/api/v1/epp_entregas/'.$delivery.'/confirmar')->assertOk();
        $this->getJson('/api/v1/inventario/devoluciones/opciones')->assertOk()->assertJsonPath('data.0.cantidad_neta', 3)->assertJsonPath('data.0.nombres', 'Ana');
        $this->postJson('/api/v1/inventario/devoluciones', ['entrega_detalle_id' => $detail, 'cantidad' => 1, 'clave_operacion' => 'monthly-return'])->assertCreated();
        $this->getJson('/api/v1/inventario/devoluciones/opciones')->assertOk()->assertJsonPath('data.0.cantidad_neta', 2);
        $response = $this->getJson('/api/v1/epp_movimientos?mes=2026-10&tipo_cambio_consulta=4')->assertOk()->assertJsonPath('total', 3);
        $this->assertSame(['Devolución', 'Salida por entrega', 'Compra'], array_column($response->json('data'), 'movimiento'));
        $response->assertJsonPath('data.0.costo_total_usd', 5)->assertJsonPath('data.1.costo_total_usd', -15)
            ->assertJsonPath('data.1.salida', 3)->assertJsonPath('data.2.entrada', 100)->assertJsonPath('data.2.costo_total_usd', 500)
            ->assertJsonPath('data.2.fecha', '2026-10-01 00:00:00');
        $this->getJson('/api/v1/epp_movimientos?mes=2026-09')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.costo_total_usd', null);
        $this->getJson('/api/v1/epp_movimientos?mes=2026-10&columns[movimiento]=Devolución&columns[epp]=Casco')->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/api/v1/epp_movimientos?mes=2026-10&tipo_cambio_consulta=4&columns[costo_total_usd]=500')->assertOk()->assertJsonPath('total', 1);
        $export = $this->get('/api/v1/exportar/epp_movimientos?mes=2026-10&tipo_cambio_consulta=4')->assertOk()->assertDownload('epp_movimientos.xlsx');
        $zip = new \ZipArchive;
        $zip->open($export->baseResponse->getFile()->getPathname());
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        $this->assertStringContainsString('CURRENT', $sheet);
        $this->assertStringNotContainsString('PREVIOUS', $sheet);
        $this->getJson('/api/v1/epp_movimientos?mes=2026-13')->assertUnprocessable();
        $this->getJson('/api/v1/epp_movimientos?tipo_cambio_consulta=0')->assertUnprocessable();
        $this->postJson('/api/v1/inventario/devoluciones', ['entrega_detalle_id' => $detail, 'cantidad' => 3, 'clave_operacion' => 'too-many'])->assertUnprocessable();
        $this->postJson('/api/v1/inventario/devoluciones', ['entrega_detalle_id' => $detail, 'cantidad' => 2, 'clave_operacion' => 'complete-return'])->assertCreated();
        $this->getJson('/api/v1/inventario/devoluciones/opciones')->assertOk()->assertJsonCount(0, 'data');
        $other = $this->tenant('beta');
        $this->loginAsTenant($other);
        $this->getJson('/api/v1/epp_movimientos?mes=2026-10&tipo_cambio_consulta=4')->assertOk()->assertJsonPath('total', 0);
        $this->getJson('/api/v1/inventario/devoluciones/opciones')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_worker_report_tracks_purchase_delivery_return_and_cancellation(): void
    {
        $this->postJson('/api/v1/compras-epp', $this->purchase('F001-REPORT', '10', '20'))->assertCreated();
        [$delivery, $detail] = $this->delivery('2');
        $this->getJson('/api/v1/reportes/epp-trabajadores')->assertOk()->assertJsonPath('total', 0);
        $this->getJson('/api/v1/reportes/stock')->assertOk()->assertJsonPath('data.0.stock', 10);
        $this->postJson('/api/v1/epp_entregas/'.$delivery.'/confirmar')->assertOk();
        $this->getJson('/api/v1/reportes/stock')->assertOk()->assertJsonPath('data.0.stock', 8);
        $this->delivery('1');
        $this->getJson('/api/v1/reportes/epp-trabajadores?columns[numero_documento]=12345678')->assertOk()
            ->assertJsonPath('total', 1)->assertJsonPath('data.0.costo_neto', 40)->assertJsonPath('data.0.entregas_confirmadas', 1);
        $this->getJson('/api/v1/reportes/epp-trabajadores?columns[nombres]=Otro')->assertOk()->assertJsonPath('total', 0);
        $this->get('/api/v1/exportar/reportes-epp-trabajadores')->assertOk()->assertDownload('reportes-epp-trabajadores.xlsx');
        $this->postJson('/api/v1/inventario/devoluciones', ['entrega_detalle_id' => $detail, 'cantidad' => 1, 'clave_operacion' => 'report-return'])->assertCreated();
        $this->getJson('/api/v1/reportes/epp-trabajadores/'.$this->worker)->assertOk()
            ->assertJsonPath('data.costo_entregado', 40)->assertJsonPath('data.costo_devuelto', 20)->assertJsonPath('data.costo_neto', 20)
            ->assertJsonPath('detalles.0.epp', 'Casco')->assertJsonPath('detalles.0.cantidad_neta', 1);
        $other = $this->tenant('beta');
        $this->loginAsTenant($other);
        $this->getJson('/api/v1/reportes/epp-trabajadores')->assertOk()->assertJsonPath('total', 0);
        $this->getJson('/api/v1/reportes/epp-trabajadores/'.$this->worker)->assertNotFound();
        $this->loginAsTenant($this->tenant);
        $this->postJson('/api/v1/epp_entregas/'.$delivery.'/anular')->assertOk();
        $this->getJson('/api/v1/reportes/epp-trabajadores')->assertOk()->assertJsonPath('total', 0);
        $this->getJson('/api/v1/reportes/stock')->assertOk()->assertJsonPath('data.0.stock', 10);
    }

    public function test_dollar_purchases_preserve_original_amounts_and_value_deliveries_in_soles(): void
    {
        $payload = $this->purchase('USD-1', '10', '5');
        $payload['moneda'] = 'USD';
        $payload['tipo_cambio'] = '3.75';
        $id = $this->postJson('/api/v1/compras-epp', $payload)->assertCreated()->json('data.id');
        $this->assertDatabaseHas('epp_compras', ['id' => $id, 'moneda' => 'USD', 'tipo_cambio' => 3.75, 'costo_total_original' => 50, 'costo_total' => 187.5]);
        $this->assertDatabaseHas('epp_compra_detalles', ['compra_id' => $id, 'costo_unitario_original' => 5, 'costo_unitario' => 18.75]);
        $this->postJson('/api/v1/compras-epp', $payload)->assertOk();
        $payload['tipo_cambio'] = '4';
        $this->postJson('/api/v1/compras-epp', $payload)->assertConflict();
        $this->postJson('/api/v1/compras-epp', $this->purchase('PEN-1', '10', '21.25'))->assertCreated();
        [$delivery, $detail] = $this->delivery('2');
        $this->postJson('/api/v1/epp_entregas/'.$delivery.'/confirmar')->assertOk();
        $this->assertDatabaseHas('epp_entrega_detalles', ['id' => $detail, 'costo_unitario' => 20, 'costo_total' => 40]);
        $this->getJson('/api/v1/reportes/stock')->assertOk()->assertJsonPath('data.0.valor_stock', 360);
        $this->getJson('/api/v1/compras-epp/'.$id)->assertOk()->assertJsonPath('data.moneda', 'USD')->assertJsonPath('detalles.0.costo_total_original', 50);
    }

    public function test_currency_and_exchange_rate_are_validated(): void
    {
        $payload = $this->purchase('USD-1', '1', '5');
        $payload['moneda'] = 'USD';
        $this->postJson('/api/v1/compras-epp', $payload)->assertUnprocessable()->assertJsonValidationErrors('tipo_cambio');
        foreach ([null, 0, -1, 'invalid'] as $rate) {
            $payload['tipo_cambio'] = $rate;
            $this->postJson('/api/v1/compras-epp', $payload)->assertUnprocessable()->assertJsonValidationErrors('tipo_cambio');
        }
        $payload['moneda'] = 'EUR';
        $payload['tipo_cambio'] = 4;
        $this->postJson('/api/v1/compras-epp', $payload)->assertUnprocessable()->assertJsonValidationErrors('moneda');
        $payload['moneda'] = 'PEN';
        $this->postJson('/api/v1/compras-epp', $payload)->assertUnprocessable()->assertJsonValidationErrors('tipo_cambio');
        $this->assertDatabaseCount('epp_compras', 0);
    }

    public function test_weighted_cost_is_frozen_on_delivery_and_preserved_for_returns_and_cancellation(): void
    {
        $this->postJson('/api/v1/compras-epp', $this->purchase('F001-1', '10', '10'))->assertCreated();
        $this->postJson('/api/v1/compras-epp', $this->purchase('F001-2', '10', '20'))->assertCreated();
        $this->getJson('/api/v1/reportes/stock')->assertOk()->assertJsonPath('data.0.stock', 20)->assertJsonPath('data.0.valor_stock', 300);
        [$delivery, $detail] = $this->delivery('4');
        $this->postJson('/api/v1/epp_entregas/'.$delivery.'/confirmar')->assertOk();
        $this->assertDatabaseHas('epp_entrega_detalles', ['id' => $detail, 'costo_unitario' => 15, 'costo_total' => 60]);
        $this->postJson('/api/v1/compras-epp', $this->purchase('F001-3', '1', '100'))->assertCreated();
        $this->getJson('/api/v1/epp_entregas/'.$delivery)->assertOk()->assertJsonPath('data.costo_total', 60);
        $this->postJson('/api/v1/inventario/devoluciones', ['entrega_detalle_id' => $detail, 'cantidad' => 2, 'clave_operacion' => 'return-1'])->assertCreated();
        $this->assertDatabaseHas('epp_movimientos', ['tipo' => 'devolucion', 'costo_unitario' => 15, 'costo_total' => 30]);
        $this->postJson('/api/v1/epp_entregas/'.$delivery.'/anular')->assertOk();
        $this->getJson('/api/v1/reportes/stock')->assertOk()->assertJsonPath('data.0.stock', 21)->assertJsonPath('data.0.valor_stock', 400);
        $this->postJson('/api/v1/epp_entregas/'.$delivery.'/anular')->assertOk();
        $this->assertDatabaseCount('epp_movimientos', 6);
        $this->patchJson('/api/v1/epp_entrega_detalles/'.$detail, ['costo_total' => 1])->assertUnprocessable();
    }

    public function test_purchase_is_atomic_idempotent_and_prevents_duplicate_invoices(): void
    {
        $payload = $this->purchase('F001-1', '3.125', '10.123456');
        $payload['detalles'][] = ['epp_id' => $this->item, 'almacen_id' => $this->warehouse, 'cantidad' => '2', 'costo_unitario' => '20'];
        $id = $this->postJson('/api/v1/compras-epp', $payload)->assertCreated()->json('data.id');
        $this->postJson('/api/v1/compras-epp', $payload)->assertOk();
        $this->assertDatabaseCount('epp_compras', 1);
        $this->assertDatabaseCount('epp_movimientos', 2);
        $this->assertDatabaseHas('epp_compras', ['id' => $id, 'costo_total' => '71.635800']);
        $this->getJson('/api/v1/compras-epp/'.$id)->assertOk()->assertJsonCount(2, 'detalles')->assertJsonPath('detalles.0.epp', 'Casco');
        $this->get('/api/v1/exportar/epp_compras')->assertOk()->assertDownload('epp_compras.xlsx');
        $payload['detalles'][0]['cantidad'] = 99;
        $this->postJson('/api/v1/compras-epp', $payload)->assertConflict();
        $payload['clave_operacion'] = (string) Str::uuid();
        $payload['factura'] = ' f001-1 ';
        $this->postJson('/api/v1/compras-epp', $payload)->assertUnprocessable();
        $this->patchJson('/api/v1/epp_compras/'.$id, ['costo_total' => 0])->assertStatus(405);
        $bad = $this->purchase('F001-2', '1', '1');
        $bad['detalles'][] = ['epp_id' => 999999, 'almacen_id' => $this->warehouse, 'cantidad' => 1, 'costo_unitario' => 1];
        $this->postJson('/api/v1/compras-epp', $bad)->assertNotFound();
        $this->assertDatabaseCount('epp_compras', 1);
        $this->assertDatabaseCount('epp_movimientos', 2);
    }

    public function test_costs_cannot_be_read_or_created_for_another_company_and_input_is_validated(): void
    {
        $id = $this->postJson('/api/v1/compras-epp', $this->purchase('F001-1', '1', '2'))->assertCreated()->json('data.id');
        $this->postJson('/api/v1/compras-epp', $this->purchase('F001-2', '1', '0'))->assertUnprocessable();
        $this->postJson('/api/v1/compras-epp', $this->purchase('F001-2', '-1', '2'))->assertUnprocessable();
        $other = $this->tenant('beta');
        $this->loginAsTenant($other);
        $this->getJson('/api/v1/compras-epp/'.$id)->assertNotFound();
        $this->postJson('/api/v1/compras-epp', $this->purchase('F001-2', '1', '2'))->assertNotFound();
        $this->getJson('/api/v1/epp_compras')->assertOk()->assertJsonPath('total', 0);
        $permission = DB::table('permisos')->where('codigo', 'almacenes.crear')->value('id');
        DB::table('rol_permisos')->where('rol_id', $other['role'])->where('permiso_id', $permission)->delete();
        $this->postJson('/api/v1/compras-epp', $this->purchase('F001-2', '1', '2'))->assertForbidden();
    }

    public function test_existing_unpriced_stock_is_not_valued_at_zero_and_full_depletion_resets_the_balance(): void
    {
        DB::table('epp_movimientos')->insert(['empresa_id' => $this->tenant['company'], 'epp_id' => $this->item, 'almacen_id' => $this->warehouse,
            'registrado_por' => $this->tenant['member'], 'fecha' => now(), 'tipo' => 'entrada', 'cantidad' => 5, 'clave_operacion' => 'legacy']);
        $this->postJson('/api/v1/compras-epp', $this->purchase('F001-1', '1', '10'))->assertCreated();
        [$delivery, $detail] = $this->delivery('6');
        $this->postJson('/api/v1/epp_entregas/'.$delivery.'/confirmar')->assertOk();
        $this->assertDatabaseHas('epp_entrega_detalles', ['id' => $detail, 'costo_unitario' => null, 'costo_total' => null]);
        $this->getJson('/api/v1/reportes/epp-trabajadores')->assertOk()->assertJsonPath('data.0.costo_neto', null)->assertJsonPath('data.0.detalles_sin_costo', 1);
        $this->postJson('/api/v1/compras-epp', $this->purchase('F001-2', '2', '12'))->assertCreated();
        $this->getJson('/api/v1/reportes/stock')->assertOk()->assertJsonPath('data.0.valor_stock', 24);
    }
}
