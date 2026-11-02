<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\Support\HseDatabase;
use Tests\TestCase;
use ZipArchive;

class WorkerAssignmentsTest extends TestCase
{
    use HseDatabase;

    private function location(array $tenant, string $name): array
    {
        $site = DB::table('sedes')->insertGetId(['empresa_id' => $tenant['company'], 'codigo' => $name, 'nombre' => $name, 'pais' => 'PE']);
        $zone = DB::table('zonas')->insertGetId(['empresa_id' => $tenant['company'], 'sede_id' => $site, 'nombre' => 'Zona '.$name]);
        $area = DB::table('areas')->insertGetId(['empresa_id' => $tenant['company'], 'zona_id' => $zone, 'nombre' => 'Área '.$name]);

        return compact('site', 'zone', 'area');
    }

    private function setupWorker(): array
    {
        $this->installHseFixture();
        $tenant = $this->tenant('alpha');
        $first = $this->location($tenant, 'Piura');
        $second = $this->location($tenant, 'Lima');
        $this->loginAsTenant($tenant);
        $worker = $this->postJson('/api/v1/trabajadores', [
            'tipo_documento' => 'DNI', 'numero_documento' => '71462409', 'nombres' => 'Persona', 'apellidos' => 'De prueba',
            'puesto' => 'Operador', 'fecha_ingreso' => '2026-01-01', 'zona_id' => $first['zone'], 'area_id' => $first['area'],
        ])->assertCreated()->json('data.id');

        return compact('tenant', 'first', 'second', 'worker');
    }

    public function test_registration_accepts_a_separate_initial_period_and_rolls_back_invalid_dates(): void
    {
        ['first' => $first] = $this->setupWorker();
        $input = [
            'tipo_documento' => 'DNI', 'numero_documento' => '01234567', 'nombres' => 'Ana', 'apellidos' => 'Prueba',
            'puesto' => 'Operadora', 'fecha_ingreso' => '2026-01-01', 'fecha_cese' => '2026-12-31', 'zona_id' => $first['zone'],
        ];
        foreach ([
            ['fecha_inicio' => '2025-12-31'],
            ['fecha_inicio' => '2026-08-01', 'fecha_fin' => '2026-07-31'],
            ['fecha_inicio' => '2026-08-01', 'fecha_fin' => '2027-01-01'],
            ['fecha_inicio' => '2026-08-01', 'fecha_fin' => null],
            ['fecha_inicio' => '2026-02-30'],
            ['fecha_inicio' => '2026-08-01', 'fecha_fin' => '2026-12-31', 'zona_id' => 999],
        ] as $period) {
            $this->postJson('/api/v1/trabajadores', $input + ['asignacion_inicial' => $period])->assertUnprocessable();
            $this->assertDatabaseCount('trabajadores', 1);
            $this->assertDatabaseCount('trabajador_asignaciones', 1);
        }
        $worker = $this->postJson('/api/v1/trabajadores', $input + ['asignacion_inicial' => [
            'fecha_inicio' => '2026-08-01', 'fecha_fin' => '2026-12-31',
        ]])->assertCreated()->json('data.id');
        $this->assertDatabaseHas('trabajadores', ['id' => $worker, 'fecha_ingreso' => '2026-01-01']);
        $this->assertDatabaseHas('trabajador_asignaciones', ['trabajador_id' => $worker, 'fecha_inicio' => '2026-08-01', 'fecha_fin' => '2026-12-31']);
        $this->getJson('/api/v1/trabajadores/'.$worker.'?fecha_ubicacion=2026-07-31')->assertOk()->assertJsonPath('data.sede_nombre', null);
        $this->getJson('/api/v1/trabajadores/'.$worker.'?fecha_ubicacion=2026-08-01')->assertOk()->assertJsonPath('data.sede_nombre', 'Piura');
    }

    public function test_zone_transfer_within_the_same_site_preserves_each_period_and_area(): void
    {
        ['tenant' => $tenant, 'first' => $first, 'worker' => $worker] = $this->setupWorker();
        $zone = DB::table('zonas')->insertGetId(['empresa_id' => $tenant['company'], 'sede_id' => $first['site'], 'nombre' => 'Zona 2']);
        $area = DB::table('areas')->insertGetId(['empresa_id' => $tenant['company'], 'zona_id' => $zone, 'nombre' => 'Área zona 2']);
        $url = '/api/v1/trabajadores/'.$worker.'/asignaciones';
        $this->postJson($url, ['zona_id' => $zone, 'area_id' => $first['area'], 'fecha_inicio' => '2026-08-01', 'cerrar_anterior' => true])->assertUnprocessable();
        $this->assertDatabaseHas('trabajador_asignaciones', ['trabajador_id' => $worker, 'zona_id' => $first['zone'], 'fecha_fin' => null]);
        $this->postJson($url, ['zona_id' => $zone, 'area_id' => $area, 'fecha_inicio' => '2026-08-01', 'cerrar_anterior' => true])->assertCreated();
        $this->getJson('/api/v1/trabajadores?sede_id='.$first['site'].'&zona_id='.$zone.'&fecha_ubicacion=2026-07-31')->assertOk()->assertJsonPath('total', 0);
        $this->getJson('/api/v1/trabajadores?sede_id='.$first['site'].'&zona_id='.$zone.'&fecha_ubicacion=2026-08-01')->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/api/v1/trabajadores?sede_id=999999&fecha_ubicacion=2026-08-01')->assertOk()->assertJsonPath('total', 0);
        $this->getJson('/api/v1/trabajadores?sede_id=invalid')->assertUnprocessable();
        $this->assertDatabaseCount('trabajadores', 1);
        $this->assertDatabaseHas('trabajador_asignaciones', ['trabajador_id' => $worker, 'sede_id' => $first['site'], 'zona_id' => $first['zone'], 'area_id' => $first['area'], 'fecha_fin' => '2026-07-31']);
        $this->getJson('/api/v1/trabajadores/'.$worker.'?fecha_ubicacion=2026-07-31')->assertOk()->assertJsonPath('data.sede_id', $first['site'])->assertJsonPath('data.zona_id', $first['zone'])->assertJsonPath('data.area_id', $first['area']);
        $this->getJson('/api/v1/trabajadores/'.$worker.'?fecha_ubicacion=2026-08-01')->assertOk()->assertJsonPath('data.sede_id', $first['site'])->assertJsonPath('data.zona_id', $zone)->assertJsonPath('data.area_id', $area);
    }

    public function test_transfer_preserves_january_to_july_and_assigns_august_to_december(): void
    {
        ['tenant' => $tenant, 'first' => $first, 'second' => $second, 'worker' => $worker] = $this->setupWorker();
        $url = '/api/v1/trabajadores/'.$worker.'/asignaciones';
        $initial = $this->getJson($url)->assertOk()->assertJsonCount(1, 'data')->json('data.0.id');
        $this->postJson($url, ['zona_id' => $second['zone'], 'area_id' => $second['area'], 'fecha_inicio' => '2026-08-01', 'fecha_fin' => '2026-12-31', 'cerrar_anterior' => true])->assertCreated();
        $this->assertDatabaseCount('trabajadores', 1);
        $this->assertDatabaseHas('trabajador_asignaciones', ['id' => $initial, 'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-07-31', 'zona_id' => $first['zone']]);
        foreach (['2026-07-31' => 'Piura', '2026-08-01' => 'Lima', '2026-12-31' => 'Lima', '2027-01-01' => null] as $date => $site) {
            $this->getJson('/api/v1/trabajadores/'.$worker.'?fecha_ubicacion='.$date)->assertOk()->assertJsonPath('data.sede_nombre', $site);
        }
        $this->getJson($url.'?fecha_ubicacion=2026-07-31')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.estado_periodo', 'Programada')->assertJsonPath('data.1.estado_periodo', 'Vigente');
        $this->assertDatabaseHas('auditoria_eventos', ['empresa_id' => $tenant['company'], 'entidad' => 'trabajador_asignaciones', 'registro_id' => $initial, 'accion' => 'cerrar_por_traslado']);
    }

    public function test_overlaps_and_invalid_dates_are_rejected_and_periods_can_be_corrected(): void
    {
        ['first' => $first, 'second' => $second, 'worker' => $worker] = $this->setupWorker();
        $url = '/api/v1/trabajadores/'.$worker.'/asignaciones';
        $initial = $this->getJson($url)->json('data.0.id');
        $this->postJson($url, ['zona_id' => $second['zone'], 'fecha_inicio' => '2026-08-01'])->assertUnprocessable();
        $this->postJson($url, ['zona_id' => $second['zone'], 'fecha_inicio' => '2026-02-30'])->assertUnprocessable();
        $this->postJson($url, ['zona_id' => $second['zone'], 'fecha_inicio' => '2025-12-31'])->assertUnprocessable();
        $this->postJson($url, ['zona_id' => $second['zone'], 'fecha_inicio' => '2026-08-01', 'fecha_fin' => '2026-07-31', 'cerrar_anterior' => true])->assertUnprocessable();
        $this->assertDatabaseHas('trabajador_asignaciones', ['id' => $initial, 'fecha_fin' => null]);
        $this->patchJson($url.'/'.$initial, ['fecha_fin' => '2026-07-31'])->assertOk();
        $this->postJson($url, ['zona_id' => $second['zone'], 'fecha_inicio' => '2026-07-31'])->assertUnprocessable();
        $new = $this->postJson($url, ['zona_id' => $second['zone'], 'fecha_inicio' => '2026-08-01', 'fecha_fin' => '2026-12-31'])->assertCreated()->json('data.id');
        $this->patchJson($url.'/'.$initial, ['fecha_fin' => '2026-08-01'])->assertUnprocessable();
        $this->patchJson($url.'/'.$new, ['observaciones' => 'Traslado acordado'])->assertOk()->assertJsonPath('data.observaciones', 'Traslado acordado');
        $this->postJson($url, ['zona_id' => $first['zone'], 'fecha_inicio' => '2027-01-01'])->assertCreated();
        $this->patchJson('/api/v1/trabajadores/'.$worker, ['zona_id' => $second['zone']])->assertUnprocessable();
        $this->patchJson('/api/v1/trabajadores/'.$worker, ['fecha_ingreso' => '2026-02-01'])->assertUnprocessable();
        $this->patchJson('/api/v1/trabajadores/'.$worker, ['fecha_cese' => '2026-12-31'])->assertUnprocessable();
    }

    public function test_date_filters_and_excel_use_the_historical_location(): void
    {
        ['second' => $second, 'worker' => $worker] = $this->setupWorker();
        $this->postJson('/api/v1/trabajadores/'.$worker.'/asignaciones', ['zona_id' => $second['zone'], 'fecha_inicio' => '2026-08-01', 'cerrar_anterior' => true])->assertCreated();
        $this->getJson('/api/v1/trabajadores?fecha_ubicacion=2026-07-01&columns[sede_nombre]=Piura')->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/api/v1/trabajadores?fecha_ubicacion=2026-08-01&columns[sede_nombre]=Piura')->assertOk()->assertJsonPath('total', 0);
        $this->getJson('/api/v1/trabajadores?fecha_ubicacion=2026-08-01&filter[zona_id]='.$second['zone'])->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/api/v1/trabajadores?fecha_ubicacion=2026-07-01&filter[zona_id]='.$second['zone'])->assertOk()->assertJsonPath('total', 0);
        $this->getJson('/api/v1/trabajadores?fecha_ubicacion=2026-08-01&q=Lima')->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/api/v1/trabajadores?fecha_ubicacion=2026-13-01')->assertUnprocessable();
        $this->getJson('/api/v1/trabajadores/'.$worker.'?fecha_ubicacion=invalid')->assertUnprocessable();
        $response = $this->get('/api/v1/exportar/trabajadores?fecha_ubicacion=2026-07-01&columns[sede_nombre]=Piura')->assertOk();
        $path = $response->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        $zip->open($path);
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        unlink($path);
        $this->assertStringContainsString('Piura', $xml);
        $this->assertStringContainsString('2026-07-01', $xml);
        $this->assertStringNotContainsString('Lima', $xml);
    }

    public function test_history_is_scoped_to_worker_company_and_edit_permissions(): void
    {
        ['tenant' => $tenant, 'first' => $first, 'second' => $second, 'worker' => $worker] = $this->setupWorker();
        $other = $this->tenant('beta');
        $foreign = $this->location($other, 'Otra sede');
        $url = '/api/v1/trabajadores/'.$worker.'/asignaciones';
        $initial = $this->getJson($url)->json('data.0.id');
        $this->postJson($url, ['zona_id' => $foreign['zone'], 'fecha_inicio' => '2026-08-01', 'cerrar_anterior' => true])->assertUnprocessable();
        $this->postJson($url, ['zona_id' => $second['zone'], 'area_id' => $first['area'], 'fecha_inicio' => '2026-08-01', 'cerrar_anterior' => true])->assertUnprocessable();
        $this->patchJson('/api/v1/zonas/'.$first['zone'], ['sede_id' => $second['site']])->assertUnprocessable();
        $this->assertDatabaseHas('trabajador_asignaciones', ['id' => $initial, 'fecha_fin' => null]);
        $this->loginAsTenant($other);
        $this->getJson($url)->assertNotFound();
        $this->postJson($url, ['zona_id' => $foreign['zone'], 'fecha_inicio' => '2026-08-01'])->assertNotFound();
        $this->patchJson($url.'/'.$initial, ['fecha_fin' => '2026-07-31'])->assertNotFound();
        $this->loginAsTenant($tenant);
        $permission = DB::table('permisos')->where('codigo', 'organizacion.editar')->value('id');
        DB::table('rol_permisos')->where('rol_id', $tenant['role'])->where('permiso_id', $permission)->delete();
        $this->getJson($url)->assertOk();
        $this->postJson($url, ['zona_id' => $second['zone'], 'fecha_inicio' => '2026-08-01'])->assertForbidden();
        $this->patchJson($url.'/'.$initial, ['fecha_fin' => '2026-07-31'])->assertForbidden();
    }

    public function test_migration_preserves_known_location_as_reviewable_initial_period(): void
    {
        $this->installHseFixture(withAssignments: false);
        $tenant = $this->tenant('alpha');
        $location = $this->location($tenant, 'Piura');
        $worker = DB::table('trabajadores')->insertGetId([
            'empresa_id' => $tenant['company'], 'tipo_documento' => 'DNI', 'numero_documento' => '71462409', 'nombres' => 'Persona', 'apellidos' => 'Prueba',
            'zona_id' => $location['zone'], 'area_id' => $location['area'], 'fecha_ingreso' => '2026-01-01', 'fecha_cese' => '2026-12-31',
        ]);
        DB::table('trabajadores')->insert(['empresa_id' => $tenant['company'], 'tipo_documento' => 'DNI', 'numero_documento' => '01234567', 'nombres' => 'Sin', 'apellidos' => 'Ubicación']);
        (require database_path('migrations/2026_10_09_174837_create_worker_assignments.php'))->up();
        $this->assertDatabaseCount('trabajadores', 2);
        $this->assertDatabaseCount('trabajador_asignaciones', 1);
        $this->assertDatabaseHas('trabajador_asignaciones', ['trabajador_id' => $worker, 'sede_id' => $location['site'], 'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-12-31']);
        $this->assertStringContainsString('Revisar las fechas', DB::table('trabajador_asignaciones')->value('observaciones'));
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
    }
}
