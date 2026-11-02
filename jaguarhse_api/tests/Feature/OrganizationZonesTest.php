<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\Support\HseDatabase;
use Tests\TestCase;
use ZipArchive;

class OrganizationZonesTest extends TestCase
{
    use HseDatabase;

    private array $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installHseFixture();
        $this->tenant = $this->tenant('alpha');
        $this->loginAsTenant($this->tenant);
    }

    private function create(string $resource, array $values): array
    {
        return $this->postJson('/api/v1/'.$resource, $values)->assertCreated()->json('data');
    }

    public function test_sites_in_different_countries_have_their_own_zones_and_areas(): void
    {
        $piura = $this->create('sedes', ['codigo' => 'PIU', 'nombre' => 'Piura', 'pais' => 'PE']);
        $santiago = $this->create('sedes', ['codigo' => 'SCL', 'nombre' => 'Santiago', 'pais' => 'CL']);
        $zoneOne = $this->create('zonas', ['sede_id' => $piura['id'], 'nombre' => 'Zona 1']);
        $zoneTwo = $this->create('zonas', ['sede_id' => $piura['id'], 'nombre' => 'Zona 2']);
        $chileZone = $this->create('zonas', ['sede_id' => $santiago['id'], 'nombre' => 'Zona 1']);
        $area = $this->create('areas', ['zona_id' => $zoneOne['id'], 'nombre' => 'Producción']);
        $this->create('areas', ['zona_id' => $zoneTwo['id'], 'nombre' => 'Producción']);
        $this->assertSame('Piura', $area['sede_nombre']);
        $this->assertSame('Zona 1', $area['zona_nombre']);
        $this->assertSame('PE', $area['pais']);
        $this->postJson('/api/v1/zonas', ['sede_id' => $piura['id'], 'nombre' => 'Zona 1'])->assertUnprocessable();
        $this->postJson('/api/v1/areas', ['zona_id' => $zoneOne['id'], 'nombre' => 'Producción'])->assertUnprocessable();
        $this->patchJson('/api/v1/areas/'.$area['id'], ['zona_id' => $chileZone['id']])->assertOk()->assertJsonPath('data.sede_nombre', 'Santiago')->assertJsonPath('data.pais', 'CL');
        $this->patchJson('/api/v1/zonas/'.$zoneTwo['id'], ['nombre' => 'Zona norte'])->assertOk();
        $this->getJson('/api/v1/areas?columns[zona_nombre]=norte')->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/api/v1/trabajadores/opciones')->assertOk()->assertJsonFragment(['sede' => 'Santiago', 'zona' => 'Zona 1', 'pais' => 'CL']);
        $this->assertDatabaseHas('auditoria_eventos', ['entidad' => 'zonas', 'registro_id' => $zoneOne['id'], 'accion' => 'crear']);
        $this->deleteJson('/api/v1/zonas/'.$zoneTwo['id'])->assertOk();
        $this->assertDatabaseHas('zonas', ['id' => $zoneTwo['id'], 'activo' => false]);
        $this->assertDatabaseCount('areas', 2);
    }

    public function test_required_relationships_and_country_codes_are_validated(): void
    {
        $this->postJson('/api/v1/sedes', ['codigo' => 'PIU', 'nombre' => 'Piura'])->assertUnprocessable()->assertJsonValidationErrors('pais');
        $this->postJson('/api/v1/sedes', ['codigo' => 'PIU', 'nombre' => 'Piura', 'pais' => 'ZZ'])->assertUnprocessable();
        $this->postJson('/api/v1/zonas', ['nombre' => 'Zona 1'])->assertUnprocessable()->assertJsonValidationErrors('sede_id');
        $this->postJson('/api/v1/areas', ['nombre' => 'Producción'])->assertUnprocessable()->assertJsonValidationErrors('zona_id');
        $site = $this->create('sedes', ['codigo' => 'PIU', 'nombre' => 'Piura', 'pais' => 'PE']);
        $this->patchJson('/api/v1/sedes/'.$site['id'], ['nombre' => 'Piura central'])->assertOk()->assertJsonPath('data.pais', 'PE');
        $this->postJson('/api/v1/areas', ['sede_id' => $site['id'], 'nombre' => 'Producción'])->assertUnprocessable();
        $this->postJson('/api/v1/areas', ['zona_id' => 999999, 'nombre' => 'Producción'])->assertUnprocessable();
        $this->getJson('/api/v1/catalogos')->assertOk()->assertJsonFragment(['paises' => config('hse.country_codes')]);
    }

    public function test_zones_and_areas_cannot_cross_company_boundaries(): void
    {
        $site = $this->create('sedes', ['codigo' => 'PIU', 'nombre' => 'Piura', 'pais' => 'PE']);
        $zone = $this->create('zonas', ['sede_id' => $site['id'], 'nombre' => 'Zona 1']);
        $area = $this->create('areas', ['zona_id' => $zone['id'], 'nombre' => 'Oficina']);
        $other = $this->tenant('beta');
        $this->loginAsTenant($other);
        $this->getJson('/api/v1/zonas')->assertOk()->assertJsonPath('total', 0);
        $this->getJson('/api/v1/zonas/'.$zone['id'])->assertNotFound();
        $this->getJson('/api/v1/areas/'.$area['id'])->assertNotFound();
        $this->patchJson('/api/v1/zonas/'.$zone['id'], ['nombre' => 'Ajeno'])->assertNotFound();
        $this->postJson('/api/v1/zonas', ['sede_id' => $site['id'], 'nombre' => 'Ajeno'])->assertUnprocessable();
        $this->postJson('/api/v1/areas', ['zona_id' => $zone['id'], 'nombre' => 'Ajeno'])->assertUnprocessable();
        $otherSite = $this->create('sedes', ['codigo' => 'SCL', 'nombre' => 'Santiago', 'pais' => 'CL']);
        $otherZone = $this->create('zonas', ['sede_id' => $otherSite['id'], 'nombre' => 'Zona 1']);
        $this->loginAsTenant($this->tenant);
        $this->patchJson('/api/v1/zonas/'.$zone['id'], ['sede_id' => $otherSite['id']])->assertUnprocessable();
        $this->patchJson('/api/v1/areas/'.$area['id'], ['zona_id' => $otherZone['id']])->assertUnprocessable();
    }

    public function test_filters_and_exports_include_the_location_hierarchy(): void
    {
        $site = $this->create('sedes', ['codigo' => 'PIU', 'nombre' => 'Piura', 'pais' => 'PE']);
        $zone = $this->create('zonas', ['sede_id' => $site['id'], 'nombre' => 'Zona norte']);
        $this->create('areas', ['zona_id' => $zone['id'], 'nombre' => 'Taller']);
        $this->getJson('/api/v1/zonas?filter[sede_id]='.$site['id'])->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/api/v1/zonas?columns[sede_nombre]=Piura&columns[pais]=PE')->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/api/v1/areas?columns[zona_nombre]=norte&columns[sede_nombre]=Piura&columns[pais]=PE')->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/api/v1/areas?columns[pais]=CL')->assertOk()->assertJsonPath('total', 0);
        foreach (['zonas', 'areas'] as $resource) {
            $this->getJson('/api/v1/'.$resource.'?q=Piura')->assertOk()->assertJsonPath('total', 1);
            $response = $this->get('/api/v1/exportar/'.$resource.'?columns[sede_nombre]=Piura')->assertOk();
            $path = $response->baseResponse->getFile()->getPathname();
            $zip = new ZipArchive;
            $zip->open($path);
            $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
            $zip->close();
            unlink($path);
            $this->assertStringContainsString('Piura', $xml);
            $this->assertStringContainsString('Zona norte', $xml);
        }
    }

    public function test_zones_use_organization_permissions(): void
    {
        $permissions = DB::table('permisos')->where('codigo', 'like', 'organizacion.%')->pluck('id');
        DB::table('rol_permisos')->where('rol_id', $this->tenant['role'])->whereIn('permiso_id', $permissions)->delete();
        $this->getJson('/api/v1/zonas')->assertForbidden();
        $this->postJson('/api/v1/zonas', ['sede_id' => 1, 'nombre' => 'Zona 1'])->assertForbidden();
        $this->get('/api/v1/exportar/zonas')->assertForbidden();
    }
}
