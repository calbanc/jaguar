<?php

namespace Tests\Feature;

use App\Services\SafetyImplements;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\HseDatabase;
use Tests\TestCase;

class SafetyImplementTest extends TestCase
{
    use HseDatabase;

    private array $tenant;

    private int $area;

    private int $provider;

    private int $category;

    private int $catalog;

    private int $item;

    private string $base = '/api/v1/implementos';

    protected function setUp(): void
    {
        parent::setUp();
        $this->installHseFixture();
        (require database_path('migrations/2026_10_26_152256_create_safety_implements_module.php'))->up();
        (require database_path('migrations/2026_10_27_151909_create_safety_implement_categories.php'))->up();
        (require database_path('migrations/2026_10_27_152826_add_supplier_to_safety_implements.php'))->up();
        $this->tenant = $this->tenant('safety');
        $this->loginAsTenant($this->tenant);
        $company = $this->tenant['company'];
        $site = DB::table('sedes')->insertGetId(['empresa_id' => $company, 'codigo' => 'SEDE', 'nombre' => 'Sede', 'pais' => 'PE']);
        $zone = DB::table('zonas')->insertGetId(['empresa_id' => $company, 'sede_id' => $site, 'nombre' => 'Oficinas']);
        $this->area = DB::table('areas')->insertGetId(['empresa_id' => $company, 'zona_id' => $zone, 'nombre' => 'Informática']);
        $this->provider = $this->postJson($this->base.'/proveedores', $this->payload(['nombre' => 'Proveedor A']))->assertCreated()->json('data.id');
        $this->category = $this->postJson($this->base.'/categorias', $this->payload(['nombre' => 'Incendio']))->assertCreated()->json('data.id');
        $this->catalog = $this->postJson($this->base.'/catalogo', $this->payload(['codigo' => 'EXT', 'nombre' => 'Extintor', 'categoria_id' => $this->category, 'periodicidad_cantidad' => 1, 'periodicidad_unidad' => 'meses']))->assertCreated()->json('data.id');
        $this->item = $this->postJson($this->base.'/unidades', $this->payload(['codigo' => 'EXT-001', 'catalogo_id' => $this->catalog]))->assertCreated()->json('data.id');
    }

    private function payload(array $values = []): array
    {
        return ['empresa_id' => $this->tenant['company']] + $values;
    }

    private function install(string $date = '2026-01-31'): int
    {
        return $this->postJson($this->base.'/unidades/'.$this->item.'/instalaciones', $this->payload(['area_id' => $this->area, 'ubicacion' => 'Ingreso principal', 'fecha_instalacion' => $date, 'fecha_renovacion' => '2027-01-31', 'latitud' => -12.05, 'longitud' => -77.03, 'responsable_id' => $this->tenant['member']]))->assertCreated()->json('data.id');
    }

    public function test_quantity_entry_creates_individual_units_and_installation_leaves_three_available(): void
    {
        $response = $this->postJson($this->base.'/ingresos', $this->payload(['categoria_id' => $this->category, 'catalogo_id' => $this->catalog, 'proveedor_id' => $this->provider, 'cantidad' => 4, 'marca' => 'Marca común']))->assertCreated()->assertJsonCount(4, 'data')->assertJsonPath('cantidad', 4);
        $items = $response->json('data');
        $ids = array_column($items, 'id');
        $this->assertCount(4, array_unique(array_column($items, 'codigo')));
        $this->assertSame(4, DB::table('implementos')->whereIn('id', $ids)->where('estado', 'disponible')->count());
        $this->postJson($this->base.'/unidades/'.$ids[0].'/instalaciones', $this->payload(['area_id' => $this->area, 'ubicacion' => 'Ingreso', 'fecha_instalacion' => '2026-01-01']))->assertCreated();
        $this->assertSame(3, DB::table('implementos')->whereIn('id', $ids)->where('estado', 'disponible')->count());
        $this->assertSame(1, DB::table('implementos')->whereIn('id', $ids)->where('estado', 'instalado')->count());
        $next = $this->postJson($this->base.'/ingresos', $this->payload(['categoria_id' => $this->category, 'catalogo_id' => $this->catalog, 'proveedor_id' => $this->provider, 'cantidad' => 4]))->assertCreated();
        $this->assertCount(8, array_unique(array_merge(array_column($items, 'codigo'), array_column($next->json('data'), 'codigo'))));
    }

    public function test_stock_suppliers_are_required_scoped_active_and_preserved_per_unit(): void
    {
        $base = ['categoria_id' => $this->category, 'catalogo_id' => $this->catalog, 'cantidad' => 2];
        $before = DB::table('implementos')->count();
        $this->postJson($this->base.'/ingresos', $this->payload($base))->assertUnprocessable()->assertJsonValidationErrors('proveedor_id');
        $other = $this->tenant('supplier-other');
        $foreign = DB::table('implementos_proveedores')->insertGetId(['empresa_id' => $other['company'], 'nombre' => 'Ajeno', 'activo' => true]);
        $this->postJson($this->base.'/ingresos', $this->payload($base + ['proveedor_id' => $foreign]))->assertUnprocessable();
        $providerB = $this->postJson($this->base.'/proveedores', $this->payload(['nombre' => 'Proveedor B', 'activo' => false]))->assertCreated()->json('data.id');
        $this->postJson($this->base.'/ingresos', $this->payload($base + ['proveedor_id' => $providerB]))->assertUnprocessable();
        $this->assertSame($before, DB::table('implementos')->count());
        $this->patchJson($this->base.'/proveedores/'.$providerB, $this->payload(['nombre' => 'Proveedor B', 'activo' => true]))->assertOk();
        $this->postJson($this->base.'/ingresos', $this->payload($base + ['proveedor_id' => $this->provider]))->assertCreated()->assertJsonPath('data.0.proveedor_id', $this->provider);
        $this->postJson($this->base.'/ingresos', $this->payload($base + ['proveedor_id' => $providerB]))->assertCreated()->assertJsonPath('data.0.proveedor_id', $providerB);
        $this->assertSame(2, DB::table('implementos')->where('catalogo_id', $this->catalog)->where('proveedor_id', $this->provider)->count());
        $this->assertSame(2, DB::table('implementos')->where('catalogo_id', $this->catalog)->where('proveedor_id', $providerB)->count());
    }

    public function test_quantity_entry_rejects_invalid_counts_categories_and_permissions_without_creating_units(): void
    {
        $before = DB::table('implementos')->count();
        foreach ([0, -1, 1.5, 201] as $quantity) {
            $this->postJson($this->base.'/ingresos', $this->payload(['categoria_id' => $this->category, 'catalogo_id' => $this->catalog, 'proveedor_id' => $this->provider, 'cantidad' => $quantity]))->assertUnprocessable();
        }
        $category = $this->postJson($this->base.'/categorias', $this->payload(['nombre' => 'Sensores']))->assertCreated()->json('data.id');
        $this->postJson($this->base.'/ingresos', $this->payload(['categoria_id' => $category, 'catalogo_id' => $this->catalog, 'proveedor_id' => $this->provider, 'cantidad' => 4]))->assertUnprocessable();
        $this->assertSame($before, DB::table('implementos')->count());
        $other = $this->tenant('entry-other');
        $this->loginAsTenant($other);
        $this->postJson($this->base.'/ingresos', ['empresa_id' => $other['company'], 'categoria_id' => $this->category, 'catalogo_id' => $this->catalog, 'proveedor_id' => $this->provider, 'cantidad' => 4])->assertUnprocessable();
        $this->loginAsTenant($this->tenant);
        DB::table('rol_permisos')->where('rol_id', $this->tenant['role'])->whereIn('permiso_id', DB::table('permisos')->where('codigo', 'implementos.crear')->select('id'))->delete();
        $this->postJson($this->base.'/ingresos', $this->payload(['categoria_id' => $this->category, 'catalogo_id' => $this->catalog, 'proveedor_id' => $this->provider, 'cantidad' => 4]))->assertForbidden();
        $this->assertSame($before, DB::table('implementos')->count());
    }

    public function test_migration_converts_existing_category_text_into_shared_references(): void
    {
        $migration = require database_path('migrations/2026_10_27_151909_create_safety_implement_categories.php');
        $migration->down();
        $second = DB::table('implementos_catalogo')->insertGetId(['empresa_id' => $this->tenant['company'], 'codigo' => 'CO2', 'nombre' => 'Extintor CO2', 'categoria' => 'Incendio', 'periodicidad_cantidad' => 1, 'periodicidad_unidad' => 'meses', 'activo' => true]);
        $migration->up();
        $category = DB::table('implementos_categorias')->where('empresa_id', $this->tenant['company'])->sole();
        $this->assertSame('Incendio', $category->nombre);
        $this->assertDatabaseHas('implementos_catalogo', ['id' => $this->catalog, 'categoria_id' => $category->id]);
        $this->assertDatabaseHas('implementos_catalogo', ['id' => $second, 'categoria_id' => $category->id]);
    }

    public function test_categories_are_scoped_unique_and_keep_existing_types_when_deactivated(): void
    {
        $this->postJson($this->base.'/categorias', $this->payload(['nombre' => 'Incendio']))->assertUnprocessable();
        $this->patchJson($this->base.'/categorias/'.$this->category, $this->payload(['nombre' => 'Extintores', 'activo' => false]))->assertOk();
        $this->assertDatabaseHas('implementos_catalogo', ['id' => $this->catalog, 'categoria_id' => $this->category, 'categoria' => 'Extintores']);
        $this->postJson($this->base.'/catalogo', $this->payload(['codigo' => 'CO2', 'nombre' => 'Extintor CO2', 'categoria_id' => $this->category, 'periodicidad_cantidad' => 1, 'periodicidad_unidad' => 'meses']))->assertUnprocessable();
        $other = $this->tenant('category-other');
        $this->loginAsTenant($other);
        $this->postJson($this->base.'/catalogo', ['empresa_id' => $other['company'], 'codigo' => 'CO2', 'nombre' => 'CO2', 'categoria_id' => $this->category, 'periodicidad_cantidad' => 1, 'periodicidad_unidad' => 'meses'])->assertUnprocessable();
        $this->patchJson($this->base.'/categorias/'.$this->category, ['empresa_id' => $other['company'], 'nombre' => 'Ajena'])->assertNotFound();
        $this->getJson($this->base.'/datos?empresa_id='.$other['company'])->assertOk()->assertJsonCount(0, 'categorias');
    }

    public function test_installation_schedules_month_end_and_inspection_records_preserve_history(): void
    {
        $installation = $this->install();
        $inspection = DB::table('implemento_inspecciones')->where('implemento_id', $this->item)->sole();
        $this->assertSame('2026-02-28', $inspection->fecha_programada);
        $this->patchJson($this->base.'/catalogo/'.$this->catalog, $this->payload(['codigo' => 'EXT', 'nombre' => 'Extintor', 'categoria_id' => $this->category, 'periodicidad_cantidad' => 3, 'periodicidad_unidad' => 'meses']))->assertOk();
        $this->postJson($this->base.'/inspecciones/'.$inspection->id.'/realizar', $this->payload(['fecha_realizada' => '2026-02-28', 'resultado' => 'operativo', 'observaciones' => 'Precinto, presión y acceso revisados.']))->assertOk();
        $this->assertDatabaseHas('implemento_inspecciones', ['id' => $inspection->id, 'estado' => 'realizada', 'periodicidad_cantidad' => 1]);
        $this->assertDatabaseHas('implemento_inspecciones', ['instalacion_id' => $installation, 'fecha_programada' => '2026-05-28', 'estado' => 'pendiente', 'periodicidad_cantidad' => 3]);
        $this->postJson($this->base.'/inspecciones/'.$inspection->id.'/realizar', $this->payload(['fecha_realizada' => '2026-02-28', 'resultado' => 'operativo', 'observaciones' => 'Duplicado']))->assertConflict();
        $this->assertSame(2, DB::table('implemento_inspecciones')->count());
    }

    public function test_transfer_and_retirement_cancel_pending_reviews_without_deleting_history(): void
    {
        $first = $this->install();
        $this->install('2026-03-01');
        $this->assertDatabaseHas('implemento_instalaciones', ['id' => $first, 'fecha_retiro' => '2026-03-01']);
        $this->assertSame(1, DB::table('implemento_instalaciones')->whereNull('fecha_retiro')->count());
        $this->assertSame(1, DB::table('implemento_inspecciones')->where('estado', 'pendiente')->count());
        $this->postJson($this->base.'/unidades/'.$this->item.'/retiro', $this->payload(['estado' => 'baja']))->assertOk();
        $this->assertSame(0, DB::table('implemento_inspecciones')->where('estado', 'pendiente')->count());
        $this->assertSame(2, DB::table('implemento_instalaciones')->count());
        $this->postJson($this->base.'/unidades/'.$this->item.'/instalaciones', $this->payload(['area_id' => $this->area, 'ubicacion' => 'Puerta', 'fecha_instalacion' => '2026-03-01']))->assertUnprocessable();
    }

    public function test_tenant_boundaries_and_permissions_are_enforced(): void
    {
        $other = $this->tenant('other');
        $this->getJson($this->base.'/datos?empresa_id='.$other['company'])->assertForbidden();
        $this->loginAsTenant($other);
        $this->postJson($this->base.'/unidades', ['empresa_id' => $other['company'], 'codigo' => 'X', 'catalogo_id' => $this->catalog])->assertUnprocessable();
        $this->postJson($this->base.'/unidades/'.$this->item.'/retiro', ['empresa_id' => $other['company'], 'estado' => 'baja'])->assertNotFound();
        $this->getJson($this->base.'/datos?empresa_id='.$other['company'])->assertOk()->assertJsonCount(0, 'implementos');
        $this->loginAsTenant($this->tenant);
        DB::table('rol_permisos')->where('rol_id', $this->tenant['role'])->whereIn('permiso_id', DB::table('permisos')->where('codigo', 'like', 'implementos.%')->select('id'))->delete();
        $this->getJson($this->base.'/datos?empresa_id='.$this->tenant['company'])->assertForbidden();
        $this->postJson($this->base.'/unidades/'.$this->item.'/retiro', $this->payload(['estado' => 'baja']))->assertForbidden();
    }

    public function test_area_polygon_and_installation_dates_are_validated(): void
    {
        $path = $this->base.'/areas/'.$this->area;
        $this->patchJson($path, $this->payload(['coordenadas' => [[-77.03, -12.05], [-77.02, -12.05], [-77.02, -12.04]]]))->assertOk();
        $geometry = json_decode(DB::table('areas')->where('id', $this->area)->value('coordenadas'), true);
        $this->assertSame('Polygon', $geometry['type']);
        $this->assertCount(4, $geometry['coordinates'][0]);
        $this->patchJson($path, $this->payload(['coordenadas' => [[0, 0], [1, 1], [2, 2]]]))->assertUnprocessable();
        $this->patchJson($path, $this->payload(['coordenadas' => [[181, 0], [1, 1], [2, 2]]]))->assertUnprocessable();
        $this->postJson($this->base.'/unidades/'.$this->item.'/instalaciones', $this->payload(['area_id' => $this->area, 'ubicacion' => 'Puerta', 'fecha_instalacion' => '2026-01-31', 'latitud' => -12]))->assertUnprocessable();
        $this->install();
        $this->postJson($this->base.'/unidades/'.$this->item.'/instalaciones', $this->payload(['area_id' => $this->area, 'ubicacion' => 'Puerta', 'fecha_instalacion' => '2026-01-01']))->assertUnprocessable();
        $this->assertSame(1, DB::table('implemento_instalaciones')->count());
    }

    public function test_costs_retain_provider_currency_and_amount(): void
    {
        $provider = $this->postJson($this->base.'/proveedores', $this->payload(['nombre' => 'Proveedor de prueba']))->assertCreated()->json('data.id');
        $this->postJson($this->base.'/unidades/'.$this->item.'/costos', $this->payload(['proveedor_id' => $provider, 'tipo' => 'compra', 'fecha' => '2026-01-01', 'moneda' => 'USD', 'importe' => 125.50]))->assertCreated();
        $this->assertDatabaseHas('implemento_costos', ['implemento_id' => $this->item, 'proveedor_id' => $provider, 'importe' => 125.50, 'moneda' => 'USD']);
        $this->postJson($this->base.'/unidades/'.$this->item.'/costos', $this->payload(['proveedor_id' => $provider, 'tipo' => 'compra', 'fecha' => '2026-01-01', 'moneda' => 'PEN', 'importe' => -5]))->assertUnprocessable();
    }

    public function test_private_photos_are_linked_to_item_and_inspection_and_isolated(): void
    {
        Storage::fake('hse');
        $this->install();
        $inspection = DB::table('implemento_inspecciones')->first();
        $this->post($this->base.'/unidades/'.$this->item.'/fotos', $this->payload(['archivo' => UploadedFile::fake()->image('revision.png'), 'inspeccion_id' => $inspection->id]))->assertCreated();
        $photo = DB::table('implemento_archivos')->sole();
        $this->assertSame($inspection->id, $photo->inspeccion_id);
        $this->get($this->base.'/fotos/'.$photo->id)->assertOk();
        $other = $this->tenant('photos-other');
        $this->loginAsTenant($other);
        $this->get($this->base.'/fotos/'.$photo->id)->assertNotFound();
    }

    public function test_organization_area_forms_create_update_and_clear_the_map_geometry(): void
    {
        $zone = DB::table('areas')->where('id', $this->area)->value('zona_id');
        $polygon = ['type' => 'Polygon', 'coordinates' => [[[-77.03, -12.05], [-77.02, -12.05], [-77.02, -12.04]]]];
        $created = $this->postJson('/api/v1/areas', ['zona_id' => $zone, 'nombre' => 'Área con mapa', 'coordenadas' => $polygon])
            ->assertCreated()->assertJsonPath('data.coordenadas.type', 'Polygon')->assertJsonCount(4, 'data.coordenadas.coordinates.0');
        $id = $created->json('data.id');
        $this->patchJson('/api/v1/areas/'.$id, ['nombre' => 'Área renombrada'])->assertOk()->assertJsonPath('data.coordenadas.type', 'Polygon');
        $polygon['coordinates'][0][1] = [-77.01, -12.05];
        $this->patchJson('/api/v1/areas/'.$id, ['coordenadas' => $polygon])->assertOk()->assertJsonPath('data.coordenadas.coordinates.0.1.0', -77.01);
        $this->getJson($this->base.'/datos?empresa_id='.$this->tenant['company'])->assertOk()->assertJsonFragment(['nombre' => 'Área renombrada']);
        $polygon['coordinates'][0][1] = [181, -12.05];
        $this->patchJson('/api/v1/areas/'.$id, ['coordenadas' => $polygon])->assertUnprocessable();
        $this->patchJson('/api/v1/areas/'.$id, ['coordenadas' => ['type' => 'Polygon', 'coordinates' => [[[0, 0], [1, 1], [2, 2]]]]])->assertUnprocessable();
        $this->patchJson('/api/v1/areas/'.$id, ['coordenadas' => null])->assertOk()->assertJsonPath('data.coordenadas', null);
        $this->assertDatabaseHas('areas', ['id' => $id, 'coordenadas' => null]);
    }

    public function test_calendar_periods_do_not_overflow(): void
    {
        $service = app(SafetyImplements::class);
        $this->assertSame('2025-02-28', $service->nextDate('2024-02-29', 1, 'anios'));
        $this->assertSame('2026-02-28', $service->nextDate('2026-01-31', 1, 'meses'));
        $this->assertSame('2026-02-02', $service->nextDate('2026-01-31', 2, 'dias'));
    }
}
