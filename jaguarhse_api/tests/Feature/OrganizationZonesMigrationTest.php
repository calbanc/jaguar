<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\HseDatabase;
use Tests\TestCase;

class OrganizationZonesMigrationTest extends TestCase
{
    use HseDatabase;

    public function test_existing_areas_keep_their_site_and_worker_references(): void
    {
        $this->installHseFixture(withZones: false);
        $tenant = $this->tenant('alpha');
        $other = $this->tenant('beta');
        DB::table('empresas')->where('id', $other['company'])->update(['pais' => 'CL']);
        $sites = [];
        foreach ([$tenant, $other] as $owner) {
            $site = DB::table('sedes')->insertGetId(['empresa_id' => $owner['company'], 'codigo' => 'S1', 'nombre' => 'Principal']);
            $sites[] = $site;
            foreach (['Producción', 'Oficinas'] as $name) {
                DB::table('areas')->insert(['empresa_id' => $owner['company'], 'sede_id' => $site, 'nombre' => $name]);
            }
        }
        $areaId = DB::table('areas')->where('empresa_id', $tenant['company'])->value('id');
        DB::table('trabajadores')->insert(['empresa_id' => $tenant['company'], 'area_id' => $areaId, 'tipo_documento' => 'DNI', 'numero_documento' => '12345678', 'nombres' => 'Ana', 'apellidos' => 'Torres']);
        (require database_path('migrations/2026_10_09_173241_add_zones_and_site_countries.php'))->up();
        $this->assertDatabaseCount('areas', 4);
        $this->assertDatabaseCount('zonas', 2);
        $this->assertDatabaseHas('sedes', ['id' => $sites[0], 'pais' => 'PE']);
        $this->assertDatabaseHas('sedes', ['id' => $sites[1], 'pais' => 'CL']);
        $this->assertDatabaseHas('trabajadores', ['area_id' => $areaId]);
        $this->assertFalse(Schema::hasColumn('areas', 'sede_id'));
        foreach (DB::table('areas')->get() as $area) {
            $this->assertDatabaseHas('zonas', ['id' => $area->zona_id, 'empresa_id' => $area->empresa_id, 'nombre' => 'Zona general', 'sede_id' => $area->empresa_id === $tenant['company'] ? $sites[0] : $sites[1]]);
        }
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
    }
}
