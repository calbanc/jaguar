<?php

namespace Tests\Feature;

use App\Services\ExcelExport;
use App\Services\InventoryService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\HseDatabase;
use Tests\TestCase;

class EppBulkDeliveryTest extends TestCase
{
    use HseDatabase;

    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $path) {
            @unlink($path);
        }
        parent::tearDown();
    }

    private function sheet(array $documents): UploadedFile
    {
        $path = app(ExcelExport::class)->create('Trabajadores', ['DNI'], array_map(fn ($dni) => [$dni], $documents), textColumns: [1]);
        $this->files[] = $path;

        return new UploadedFile($path, 'dni.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

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

    public function test_preview_then_confirm_delivers_to_all_workers_only_once(): void
    {
        [$tenant, $input] = $this->setupDelivery();
        DB::table('trabajadores')->insert(['empresa_id' => $tenant['company'], 'tipo_documento' => 'DNI', 'numero_documento' => '71462409', 'nombres' => 'Luis', 'apellidos' => 'Prueba']);
        unset($input['trabajador_id']);
        $input['archivo'] = $this->sheet(['01234567', '71462409']);
        $preview = $this->postJson('/api/v1/entregas-epp/masiva', $input)->assertOk()->assertJsonPath('invalidos', 0)->assertJsonPath('cantidad_total', '4.000')->assertJsonPath('filas.0.dni', '01234567')->json('validacion');
        $this->assertDatabaseCount('epp_entregas', 0);
        $input += ['confirmar' => true, 'validacion' => $preview];
        $this->postJson('/api/v1/entregas-epp/masiva', $input)->assertCreated()->assertJsonPath('registrados', 2)->assertJsonPath('planilla_numero', 1);
        $this->assertEquals(6, DB::table('epp_movimientos')->sum('cantidad'));
        $this->postJson('/api/v1/entregas-epp/masiva', $input)->assertOk()->assertJsonPath('registrados', 2)->assertJsonPath('planilla_numero', 1);
        $this->assertDatabaseCount('epp_entregas', 2);
        $this->assertDatabaseCount('epp_entrega_lotes', 1);
        $this->assertDatabaseCount('epp_planillas', 1);
        $this->assertDatabaseHas('epp_planillas', ['numero' => 1, 'trabajadores' => 2, 'registrado_por' => $tenant['member']]);
        $this->postJson('/api/v1/entregas-epp/masiva', array_replace($input, ['cantidad' => 3]))->assertConflict();
    }

    public function test_twenty_workers_share_one_planilla_and_next_delivery_gets_next_number(): void
    {
        [$tenant, $input] = $this->setupDelivery();
        $documents = ['01234567'];
        for ($i = 1; $i < 20; $i++) {
            $dni = str_pad((string) $i, 8, '0', STR_PAD_LEFT);
            DB::table('trabajadores')->insert(['empresa_id' => $tenant['company'], 'tipo_documento' => 'DNI', 'numero_documento' => $dni, 'nombres' => 'Trabajador', 'apellidos' => 'Prueba '.$i]);
            $documents[] = $dni;
        }
        $bulk = $input;
        unset($bulk['trabajador_id']);
        $bulk['cantidad'] = 0.1;
        $bulk['archivo'] = $this->sheet($documents);
        $bulk['validacion'] = $this->postJson('/api/v1/entregas-epp/masiva', $bulk)->assertOk()->json('validacion');
        $this->assertDatabaseCount('epp_planillas', 0);
        $this->postJson('/api/v1/entregas-epp/masiva', $bulk + ['confirmar' => true])->assertCreated()->assertJsonPath('planilla_numero', 1);
        $this->assertDatabaseHas('epp_planillas', ['numero' => 1, 'trabajadores' => 20]);
        $this->assertCount(20, json_decode(DB::table('epp_planillas')->value('entregas'), true));
        $input['uuid_cliente'] = (string) Str::uuid();
        $this->postJson('/api/v1/entregas-epp', $input)->assertCreated()->assertJsonPath('planilla_numero', 2);
        $this->postJson('/api/v1/entregas-epp', $input)->assertOk()->assertJsonPath('planilla_numero', 2);
        $this->assertDatabaseCount('epp_planillas', 2);
        $pdf = $this->get('/api/v1/informe-entregas/pdf?planilla_numero=1')->assertOk()->getContent();
        foreach ($documents as $dni) {
            $this->assertStringContainsString($dni, $pdf);
        }
        $this->assertStringContainsString('20 registros', $pdf);
        if ($path = getenv('HSE_PLANILLA_PREVIEW')) {
            file_put_contents($path, $pdf);
        }
        DB::statement('DROP TABLE epp_planillas');
        (require database_path('migrations/2026_10_14_135511_create_epp_planillas_table.php'))->up();
        $this->assertDatabaseCount('epp_planillas', 2);
        $this->assertDatabaseHas('epp_planillas', ['numero' => 1, 'trabajadores' => 20, 'registrado_por' => $tenant['member']]);
        $this->postJson('/api/v1/entregas-epp/masiva', $bulk + ['confirmar' => true])->assertOk()->assertJsonPath('planilla_numero', 1);
    }

    public function test_invalid_duplicate_foreign_or_inactive_documents_block_the_whole_batch(): void
    {
        [$tenant, $input] = $this->setupDelivery();
        $other = $this->tenant('beta');
        DB::table('trabajadores')->insert(['empresa_id' => $other['company'], 'tipo_documento' => 'DNI', 'numero_documento' => '71462409', 'nombres' => 'Luis', 'apellidos' => 'Prueba']);
        unset($input['trabajador_id']);
        foreach ([['01234567', '01234567'], ['01234567', '71462409'], ['1234567'], ['XXXXXXXX']] as $documents) {
            $this->postJson('/api/v1/entregas-epp/masiva', $input + ['archivo' => $this->sheet($documents), 'confirmar' => true])->assertOk()->assertJsonPath('validacion', null);
            $this->assertDatabaseCount('epp_entregas', 0);
        }
        DB::table('trabajadores')->where('empresa_id', $tenant['company'])->update(['activo' => false]);
        $this->postJson('/api/v1/entregas-epp/masiva', $input + ['archivo' => $this->sheet(['01234567'])])->assertOk()->assertJsonPath('invalidos', 1);
    }

    public function test_confirmation_rechecks_stock_and_requires_unchanged_preview(): void
    {
        [, $input] = $this->setupDelivery();
        unset($input['trabajador_id']);
        $input['archivo'] = $this->sheet(['01234567']);
        $hash = $this->postJson('/api/v1/entregas-epp/masiva', $input)->json('validacion');
        $this->postJson('/api/v1/entregas-epp/masiva', $input + ['confirmar' => true])->assertUnprocessable();
        $this->postJson('/api/v1/entregas-epp/masiva', array_replace($input, ['confirmar' => true, 'validacion' => $hash, 'cantidad' => 3]))->assertUnprocessable();
        DB::table('epp_movimientos')->update(['cantidad' => 1]);
        $this->postJson('/api/v1/entregas-epp/masiva', $input + ['confirmar' => true, 'validacion' => $hash])->assertOk()->assertJsonPath('stock_suficiente', false)->assertJsonPath('validacion', null);
        $this->assertDatabaseCount('epp_entregas', 0);
    }

    public function test_failure_in_second_delivery_rolls_back_first_delivery_and_stock(): void
    {
        [$tenant, $input] = $this->setupDelivery();
        DB::table('trabajadores')->insert(['empresa_id' => $tenant['company'], 'tipo_documento' => 'DNI', 'numero_documento' => '71462409', 'nombres' => 'Luis', 'apellidos' => 'Prueba']);
        unset($input['trabajador_id']);
        $input['archivo'] = $this->sheet(['01234567', '71462409']);
        $service = app(InventoryService::class);
        $calls = 0;
        $this->partialMock(InventoryService::class, function ($mock) use ($service, &$calls) {
            $mock->shouldReceive('transition')->andReturnUsing(function (...$args) use ($service, &$calls) {
                if (++$calls === 2) {
                    throw new \RuntimeException('Failure during second delivery');
                }

                return $service->transition(...$args);
            });
        });
        $input['validacion'] = $this->postJson('/api/v1/entregas-epp/masiva', $input)->json('validacion');
        $this->postJson('/api/v1/entregas-epp/masiva', $input + ['confirmar' => true])->assertStatus(500);
        $this->assertDatabaseCount('epp_entregas', 0);
        $this->assertDatabaseCount('epp_entrega_detalles', 0);
        $this->assertDatabaseCount('epp_entrega_lotes', 0);
        $this->assertDatabaseCount('epp_planillas', 0);
        $this->assertEquals(10, DB::table('epp_movimientos')->sum('cantidad'));
    }
}
