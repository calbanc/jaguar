<?php

namespace Tests\Feature;

use App\Services\ExcelExport;
use App\Services\WorkerSpreadsheet;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\Support\HseDatabase;
use Tests\TestCase;
use ZipArchive;

class WorkerZonesTest extends TestCase
{
    use HseDatabase;

    private function location(array $tenant, string $name = 'Piura'): array
    {
        $site = DB::table('sedes')->insertGetId(['empresa_id' => $tenant['company'], 'codigo' => $name, 'nombre' => $name, 'pais' => 'PE']);
        $zone = DB::table('zonas')->insertGetId(['empresa_id' => $tenant['company'], 'sede_id' => $site, 'nombre' => 'Zona '.$name]);
        $area = DB::table('areas')->insertGetId(['empresa_id' => $tenant['company'], 'zona_id' => $zone, 'nombre' => 'Taller '.$name]);

        return compact('site', 'zone', 'area');
    }

    private function worker(): array
    {
        return ['tipo_documento' => 'DNI', 'numero_documento' => '01234567', 'nombres' => 'Ana', 'apellidos' => 'Torres', 'puesto' => 'Operadora', 'fecha_ingreso' => '2026-01-15'];
    }

    public function test_zone_is_required_and_area_must_belong_to_that_zone_and_company(): void
    {
        $this->installHseFixture();
        $tenant = $this->tenant('alpha');
        $location = $this->location($tenant);
        $second = $this->location($tenant, 'Lima');
        $foreign = $this->location($this->tenant('beta'));
        $this->loginAsTenant($tenant);
        $this->postJson('/api/v1/trabajadores', $this->worker())->assertUnprocessable()->assertJsonValidationErrors('zona_id');
        $this->postJson('/api/v1/trabajadores', $this->worker() + ['zona_id' => $foreign['zone']])->assertUnprocessable();
        foreach ([$second['area'], $foreign['area']] as $area) {
            $this->postJson('/api/v1/trabajadores', $this->worker() + ['zona_id' => $location['zone'], 'area_id' => $area])->assertUnprocessable()->assertJsonValidationErrors('area_id');
        }
        $id = $this->postJson('/api/v1/trabajadores', $this->worker() + ['zona_id' => $location['zone'], 'area_id' => $location['area']])->assertCreated()->assertJsonPath('data.zona_nombre', 'Zona Piura')->assertJsonPath('data.sede_nombre', 'Piura')->json('data.id');
        $this->patchJson('/api/v1/trabajadores/'.$id, ['zona_id' => $second['zone']])->assertUnprocessable();
        $this->patchJson('/api/v1/trabajadores/'.$id, ['zona_id' => null])->assertUnprocessable();
        $this->patchJson('/api/v1/areas/'.$location['area'], ['zona_id' => $second['zone']])->assertUnprocessable();
        $this->postJson('/api/v1/trabajadores/'.$id.'/asignaciones', ['zona_id' => $second['zone'], 'area_id' => null, 'fecha_inicio' => '2026-02-01', 'cerrar_anterior' => true])->assertCreated()->assertJsonPath('data.zona_nombre', 'Zona Lima')->assertJsonPath('data.area_id', null);
        $this->getJson('/api/v1/trabajadores/opciones')->assertOk()->assertJsonCount(2, 'zonas')->assertJsonCount(2, 'sedes')->assertJsonPath('data.0.zona_id', $second['zone']);
        $this->assertDatabaseHas('trabajador_asignaciones', ['trabajador_id' => $id, 'zona_id' => $second['zone'], 'area_id' => null]);
    }

    public function test_worker_filters_and_export_include_zone_site_and_area(): void
    {
        $this->installHseFixture();
        $tenant = $this->tenant('alpha');
        $location = $this->location($tenant);
        $this->loginAsTenant($tenant);
        $this->postJson('/api/v1/trabajadores', $this->worker() + ['zona_id' => $location['zone'], 'area_id' => $location['area']])->assertCreated();
        $filters = '?columns[zona_nombre]=Piura&columns[sede_nombre]=Piura&columns[area_nombre]=Taller';
        $this->getJson('/api/v1/trabajadores'.$filters)->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/api/v1/trabajadores?columns[zona_nombre]=Lima')->assertOk()->assertJsonPath('total', 0);
        $this->getJson('/api/v1/trabajadores?q=Piura')->assertOk()->assertJsonPath('total', 1);
        $response = $this->get('/api/v1/exportar/trabajadores'.$filters)->assertOk();
        $path = $response->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        $zip->open($path);
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        unlink($path);
        $this->assertStringContainsString('Zona Piura', $xml);
        $this->assertStringContainsString('Taller Piura', $xml);
    }

    public function test_import_requires_valid_zone_and_optional_area_before_saving(): void
    {
        $this->installHseFixture();
        $tenant = $this->tenant('alpha');
        $location = $this->location($tenant);
        $other = $this->location($tenant, 'Lima');
        $foreign = $this->location($this->tenant('beta'));
        $this->loginAsTenant($tenant);
        $path = app(ExcelExport::class)->create('Trabajadores', WorkerSpreadsheet::HEADERS, [['01234567', 'Ana', 'Torres', 'Operadora', '2026-01-15']], [1]);
        try {
            $file = new UploadedFile($path, 'trabajadores.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
            $body = ['archivo' => $file, 'confirmar' => true];
            $this->postJson('/api/v1/trabajadores/importar', $body)->assertUnprocessable()->assertJsonValidationErrors('zona_id');
            $this->postJson('/api/v1/trabajadores/importar', $body + ['zona_id' => $foreign['zone']])->assertUnprocessable();
            $this->postJson('/api/v1/trabajadores/importar', $body + ['zona_id' => $location['zone'], 'area_id' => $other['area']])->assertUnprocessable();
            $this->assertDatabaseCount('trabajadores', 0);
            $valid = ['archivo' => $file, 'zona_id' => $location['zone'], 'area_id' => $location['area']];
            $this->postJson('/api/v1/trabajadores/importar', $valid)->assertOk()->assertJsonPath('filas.0.datos.zona_id', $location['zone']);
            $this->postJson('/api/v1/trabajadores/importar', $valid + ['confirmar' => true])->assertCreated();
            $this->assertDatabaseHas('trabajadores', ['zona_id' => $location['zone'], 'area_id' => $location['area'], 'numero_documento' => '01234567']);
        } finally {
            unlink($path);
        }
    }

    public function test_migration_derives_known_zones_and_preserves_unassigned_workers_and_history(): void
    {
        $this->installHseFixture(withWorkerZones: false);
        $tenant = $this->tenant('alpha');
        $location = $this->location($tenant);
        $known = DB::table('trabajadores')->insertGetId($this->worker() + ['empresa_id' => $tenant['company'], 'area_id' => $location['area']]);
        $unknown = DB::table('trabajadores')->insertGetId(array_replace($this->worker(), ['numero_documento' => '87654321']) + ['empresa_id' => $tenant['company']]);
        $delivery = DB::table('epp_entregas')->insertGetId(['uuid_cliente' => 'c6df7f80-e215-4d23-bde4-d7965e64c263', 'empresa_id' => $tenant['company'], 'trabajador_id' => $known, 'entregado_por' => $tenant['member'], 'fecha' => '2026-10-09 12:00:00']);
        $costs = DB::table('v_epp_costo_trabajador')->get()->all();
        (require database_path('migrations/2026_10_09_174218_add_zone_to_workers.php'))->up();
        $this->assertDatabaseHas('trabajadores', ['id' => $known, 'zona_id' => $location['zone'], 'area_id' => $location['area']]);
        $this->assertDatabaseHas('trabajadores', ['id' => $unknown, 'zona_id' => null, 'area_id' => null]);
        $this->assertDatabaseHas('epp_entregas', ['id' => $delivery, 'trabajador_id' => $known]);
        $this->assertEquals($costs, DB::table('v_epp_costo_trabajador')->get()->all());
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
    }

    public function test_database_rejects_mismatched_area_and_zone(): void
    {
        $this->installHseFixture();
        $tenant = $this->tenant('alpha');
        $location = $this->location($tenant);
        $other = $this->location($tenant, 'Lima');
        $this->expectException(QueryException::class);
        DB::table('trabajadores')->insert($this->worker() + ['empresa_id' => $tenant['company'], 'zona_id' => $location['zone'], 'area_id' => $other['area']]);
    }
}
