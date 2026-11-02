<?php

namespace Tests\Feature;

use App\Services\EppDeliveryPdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\HseDatabase;
use Tests\TestCase;

class EppDeliveryReportTest extends TestCase
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

    public function test_pdf_filters_historical_location_warehouse_and_local_date(): void
    {
        [$tenant, $input] = $this->setupDelivery();
        $site = DB::table('sedes')->where('empresa_id', $tenant['company'])->value('id');
        $zone = DB::table('zonas')->insertGetId(['empresa_id' => $tenant['company'], 'sede_id' => $site, 'nombre' => 'Zona original']);
        $otherZone = DB::table('zonas')->insertGetId(['empresa_id' => $tenant['company'], 'sede_id' => $site, 'nombre' => 'Zona siguiente']);
        DB::table('trabajador_asignaciones')->insert([
            ['empresa_id' => $tenant['company'], 'trabajador_id' => $input['trabajador_id'], 'sede_id' => $site, 'zona_id' => $zone, 'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-10-09'],
            ['empresa_id' => $tenant['company'], 'trabajador_id' => $input['trabajador_id'], 'sede_id' => $site, 'zona_id' => $otherZone, 'fecha_inicio' => '2026-10-10', 'fecha_fin' => null],
        ]);
        $input['hora'] = '23:59';
        $id = $this->postJson('/api/v1/entregas-epp', $input)->assertCreated()->json('data.id');
        $url = '/api/v1/informe-entregas/pdf?fecha=2026-10-09&sede_id='.$site.'&zona_id='.$zone.'&almacen_id='.$input['almacen_id'];
        $response = $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-1.4', $response->getContent());
        $this->assertStringContainsString('01234567', $response->getContent());
        $this->assertStringContainsString('Ana Prueba', $response->getContent());
        $this->assertStringContainsString('Sin firma almacenada', $response->getContent());
        $this->assertStringNotContainsString('01234567', $this->get('/api/v1/informe-entregas/pdf?fecha=2026-10-10')->assertOk()->getContent());
        $this->assertStringNotContainsString('01234567', $this->get('/api/v1/informe-entregas/pdf?fecha=2026-10-09&zona_id='.$otherZone)->assertOk()->getContent());
        $this->postJson('/api/v1/epp_entregas/'.$id.'/anular')->assertOk();
        $this->assertStringNotContainsString('01234567', $this->get($url)->assertOk()->getContent());
        $other = $this->tenant('beta');
        $otherSite = DB::table('sedes')->insertGetId(['empresa_id' => $other['company'], 'codigo' => 'OTR', 'nombre' => 'Otra sede', 'pais' => 'PE']);
        $this->getJson('/api/v1/informe-entregas/pdf?fecha=2026-10-09&sede_id='.$otherSite)->assertUnprocessable();
        $permission = DB::table('permisos')->where('codigo', 'epp.descargar')->value('id');
        DB::table('rol_permisos')->where('rol_id', $tenant['role'])->where('permiso_id', $permission)->delete();
        $this->getJson($url)->assertForbidden();
    }

    public function test_planilla_report_uses_its_number_and_registration_header_and_is_tenant_scoped(): void
    {
        [$tenant, $input] = $this->setupDelivery();
        $site = DB::table('sedes')->where('empresa_id', $tenant['company'])->value('id');
        $zone = DB::table('zonas')->insertGetId(['empresa_id' => $tenant['company'], 'sede_id' => $site, 'nombre' => 'Zona original']);
        DB::table('trabajador_asignaciones')->insert(['empresa_id' => $tenant['company'], 'trabajador_id' => $input['trabajador_id'], 'sede_id' => $site, 'zona_id' => $zone, 'fecha_inicio' => '2026-01-01']);
        $other = $this->tenant('beta');
        $responsible = DB::table('miembros_empresa')->insertGetId(['empresa_id' => $tenant['company'], 'usuario_id' => $other['user']]);
        $input['entregado_por'] = $responsible;
        $id = $this->postJson('/api/v1/entregas-epp', $input)->assertCreated()->assertJsonPath('planilla_numero', 1)->json('data.id');
        $input['uuid_cliente'] = (string) Str::uuid();
        $this->postJson('/api/v1/entregas-epp', $input)->assertCreated()->assertJsonPath('planilla_numero', 2);
        $this->getJson('/api/v1/informe-entregas/opciones')->assertOk()->assertJsonCount(2, 'planillas')->assertJsonPath('planillas.0.numero', 2)->assertJsonPath('planillas.0.responsable', 'alpha');
        DB::table('zonas')->where('id', $zone)->update(['nombre' => 'Zona renombrada']);
        $url = '/api/v1/informe-entregas/pdf?planilla_numero=1';
        $pdf = $this->get($url)->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="planilla-entrega-1.pdf"')->getContent();
        foreach (['Planilla: 1', 'Fecha de entrega: 09/10/2026', 'Zona: Zona original', 'Responsable del registro: alpha', '1 registros', '01234567'] as $text) {
            $this->assertStringContainsString($text, $pdf);
        }
        $this->getJson('/api/v1/informe-entregas/pdf?planilla_numero=999')->assertNotFound();
        $this->getJson('/api/v1/informe-entregas/pdf?planilla_numero=0')->assertUnprocessable();
        $this->postJson('/api/v1/epp_entregas/'.$id.'/anular')->assertOk();
        $this->assertStringNotContainsString('01234567', $this->get($url)->assertOk()->getContent());
        $this->loginAsTenant($other);
        $this->getJson('/api/v1/informe-entregas/opciones')->assertOk()->assertJsonCount(0, 'planillas');
        $this->getJson($url)->assertNotFound();
    }

    public function test_pdf_renders_private_signatures_and_multiple_pages_with_long_names(): void
    {
        Storage::fake('firmas');
        $image = imagecreatetruecolor(400, 110);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        $ink = imagecolorallocate($image, 25, 40, 60);
        imagestring($image, 5, 100, 35, 'FIRMA DE PRUEBA', $ink);
        imageline($image, 40, 80, 360, 60, $ink);
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);
        Storage::disk('firmas')->put('test.png', $bytes);
        $rows = [];
        for ($i = 0; $i < 18; $i++) {
            $rows[] = (object) ['dni' => str_pad((string) $i, 8, '0', STR_PAD_LEFT), 'trabajador' => 'María Fernández Rodríguez Apellido largo de prueba', 'epp' => 'Guantes de protección contra riesgos mecánicos y químicos (TALLA L)', 'cantidad' => '2 par', 'firma_ruta' => $i % 2 ? null : 'test.png'];
        }
        $pdf = app(EppDeliveryPdf::class)->render('Empresa de prueba', ['Fecha' => '09/10/2026 (America/Lima)', 'Sede' => 'Piura', 'Zona' => 'Zona 1', 'Almacén' => 'Principal'], $rows);
        $this->assertStringContainsString('/Subtype /Image', $pdf);
        $this->assertGreaterThan(1, substr_count($pdf, '/Type /Page '));
        $this->assertStringContainsString('00000017', $pdf);
        if ($path = getenv('HSE_PDF_PREVIEW')) {
            file_put_contents($path, $pdf);
        }
    }
}
