<?php

namespace Tests\Feature;

use App\Services\TrainingReportPdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\HseDatabase;
use Tests\TestCase;

class TrainingReportTest extends TestCase
{
    use HseDatabase;

    private array $tenant;

    private array $design;

    private string $base = '/api/v1/capacitaciones/formatos';

    protected function setUp(): void
    {
        parent::setUp();
        $this->installHseFixture();
        foreach (['2026_10_24_011304_create_training_tables.php', '2026_10_24_012905_add_planilla_number_to_capacitacion.php', '2026_10_24_013635_add_signature_to_training_participants.php', '2026_10_24_205617_create_capacitacion_formatos_table.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        $this->tenant = $this->tenant('report');
        $this->loginAsTenant($this->tenant);
        $this->design = ['html' => '<h1>Mi informe {{planilla.numero_planilla}}</h1><p>{{tema.nombre}}</p><p>{{responsable.nombre}}</p><div>{{participantes.tabla}}</div>', 'orientacion' => 'vertical', 'margen' => 15, 'fuente' => 11, 'columnas' => ['numero_documento', 'nombres', 'firma']];
    }

    private function payload(): array
    {
        return ['empresa_id' => $this->tenant['company'], 'nombre' => 'Mi formato', 'diseno' => $this->design];
    }

    public function test_company_scoped_crud_and_optimistic_version_check(): void
    {
        $this->getJson($this->base)->assertUnprocessable();
        $this->postJson($this->base, ['nombre' => 'Sin empresa'])->assertUnprocessable();
        $id = $this->postJson($this->base, $this->payload())->assertCreated()->assertJsonPath('data.version', 1)->json('data.id');
        $query = '?empresa_id='.$this->tenant['company'];
        $this->getJson($this->base.$query)->assertOk()->assertJsonCount(1, 'data');
        $this->getJson($this->base.'/'.$id.$query)->assertOk()->assertJsonPath('data.diseno.orientacion', 'vertical');
        $this->patchJson($this->base.'/'.$id, $this->payload() + ['version' => 1])->assertOk()->assertJsonPath('data.version', 2);
        $this->patchJson($this->base.'/'.$id, $this->payload() + ['version' => 1])->assertConflict();
        $other = $this->tenant('other-report');
        $this->getJson($this->base.'?empresa_id='.$other['company'])->assertForbidden();
        $this->loginAsTenant($other);
        $this->getJson($this->base.'/'.$id.'?empresa_id='.$other['company'])->assertNotFound();
        $this->deleteJson($this->base.'/'.$id.'?empresa_id='.$other['company'])->assertNotFound();
        $this->loginAsTenant($this->tenant);
        $this->deleteJson($this->base.'/'.$id.$query)->assertOk();
        $this->getJson($this->base.$query)->assertJsonCount(0, 'data');
        $this->assertDatabaseHas('capacitacion_formatos', ['id' => $id, 'activo' => false]);
    }

    public function test_pdf_renderer_produces_a_downloadable_pdf(): void
    {
        if (! is_executable('/Applications/Google Chrome.app/Contents/MacOS/Google Chrome')) {
            $this->markTestSkipped('La prueba de integración requiere Chrome local.');
        }
        config(['hse.pdf_chrome_path' => '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome']);
        $pdf = app(TrainingReportPdf::class)->generate('<!doctype html><html><head><meta charset="utf-8"><style>@page{size:A4;margin:15mm}</style></head><body><h1>Capacitación</h1><table><tr><td>Trabajador de prueba</td></tr></table></body></html>');
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThan(1000, strlen($pdf));
        $this->assertStringContainsString('%%EOF', $pdf);
    }

    public function test_permissions_protect_design_and_preview_routes(): void
    {
        DB::table('rol_permisos')->where('rol_id', $this->tenant['role'])->whereIn('permiso_id', DB::table('permisos')->where('codigo', 'like', 'capacitaciones.%')->select('id'))->delete();
        $query = '?empresa_id='.$this->tenant['company'];
        $this->getJson($this->base.$query)->assertForbidden();
        $this->getJson($this->base.'/campos'.$query)->assertForbidden();
        $this->postJson($this->base, $this->payload())->assertForbidden();
        $this->patchJson($this->base.'/1', $this->payload() + ['version' => 1])->assertForbidden();
        $this->deleteJson($this->base.'/1'.$query)->assertForbidden();
        $this->postJson($this->base.'/vista-previa', $this->payload() + ['numero_planilla' => 1])->assertForbidden();
    }

    public function test_design_removes_executable_content_and_rejects_unknown_fields(): void
    {
        $this->design['html'] = '<h1 style="text-align:center;color:red" onclick="alert(1)">Hola</h1><script>alert(1)</script><img src="https://example.com/private"><iframe src="evil"></iframe><a href="javascript:alert(1)">Texto</a>';
        $html = $this->postJson($this->base, $this->payload())->assertCreated()->json('data.diseno.html');
        $this->assertStringNotContainsString('script', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('src=', $html);
        $this->assertStringNotContainsString('href=', $html);
        $this->assertStringContainsString('text-align:center', $html);
        $this->design['html'] = '<p>{{usuario.password}}</p>';
        $this->postJson($this->base, $this->payload())->assertUnprocessable();
        $this->design['html'] = '<p>{{planilla.<b>numero_planilla</b>}}</p>';
        $this->postJson($this->base, $this->payload())->assertUnprocessable();
        $this->design['html'] = '<p>Normal</p>';
        $this->design['detalle'] = 'desconocido';
        $this->postJson($this->base, $this->payload())->assertUnprocessable();
        $this->design['detalle'] = 'tabla';
        $this->design['columnas'] = ['firma_ruta'];
        $this->postJson($this->base, $this->payload())->assertUnprocessable();
    }

    public function test_preview_resolves_fields_and_only_active_participants_with_planilla_signatures(): void
    {
        Storage::fake('firmas_capacitaciones');
        $company = $this->tenant['company'];
        $input = ['empresa_id' => $company, 'numero_planilla' => 1, 'fecha' => '2026-10-24', 'hora_inicio' => '09:00', 'hora_termino' => '10:00', 'responsable_registro_id' => $this->tenant['user']];
        foreach (['tema_capacitacion', 'lugar_capacitacion', 'capacitador', 'empresa_capacitacion'] as $table) {
            $input[$table.'_id'] = DB::table($table)->insertGetId(['empresa_id' => $company, 'nombre' => '<script>tema</script>']);
        }
        $id = DB::table('capacitacion')->insertGetId($input);
        $worker = DB::table('trabajadores')->insertGetId(['empresa_id' => $company, 'tipo_documento' => 'DNI', 'numero_documento' => '12345678', 'nombres' => 'Ana', 'apellidos' => 'Prueba']);
        $path = $company.'/1/12345678.png';
        Storage::disk('firmas_capacitaciones')->put($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aMuoAAAAASUVORK5CYII='));
        $participant = DB::table('capacitacion_participantes')->insertGetId(['empresa_id' => $company, 'capacitacion_id' => $id, 'trabajador_id' => $worker, 'firma_ruta' => $path, 'firma_fecha' => now()]);
        $html = $this->postJson($this->base.'/vista-previa', $this->payload() + ['numero_planilla' => 1])->assertOk()->json('html');
        $this->assertStringContainsString('Mi informe 1', $html);
        $this->assertStringContainsString('&lt;script&gt;tema&lt;/script&gt;', $html);
        $this->assertStringContainsString('12345678', $html);
        $this->assertStringContainsString('data:image/png;base64,', $html);
        $this->assertStringNotContainsString('{{', $html);
        $this->design['detalle'] = 'listado';
        $saved = $this->postJson($this->base, $this->payload())->assertCreated()->assertJsonPath('data.diseno.detalle', 'listado')->json('data.id');
        $this->getJson($this->base.'/'.$saved.'?empresa_id='.$company)->assertJsonPath('data.diseno.columnas', ['numero_documento', 'nombres', 'firma']);
        $this->mock(TrainingReportPdf::class, function ($mock): void {
            $mock->shouldReceive('generate')->once()->withArgs(function (string $html): bool {
                return str_contains($html, 'Mi informe 1') && str_contains($html, 'class="participant-card"') && str_contains($html, '12345678') && str_contains($html, 'data:image/png;base64,');
            })->andReturn('%PDF-1.4 test');
        });
        $this->get($this->base.'/'.$saved.'/pdf?empresa_id='.$company.'&numero_planilla=1')->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertHeader('Content-Disposition', 'attachment; filename="capacitacion-1-formato-'.$saved.'.pdf"');
        $this->getJson($this->base.'/'.$saved.'/pdf?numero_planilla=1')->assertUnprocessable();
        $this->getJson($this->base.'/'.$saved.'/pdf?empresa_id='.$company.'&numero_planilla=999')->assertNotFound();
        DB::table('capacitacion_participantes')->where('id', $participant)->update(['activo' => false]);
        $html = $this->postJson($this->base.'/vista-previa', $this->payload() + ['numero_planilla' => 1])->assertOk()->json('html');
        $this->assertStringNotContainsString('12345678', $html);
        $this->assertStringContainsString('Sin participantes', $html);
        $other = $this->tenant('preview-other');
        $this->loginAsTenant($other);
        $payload = $this->payload();
        $payload['empresa_id'] = $other['company'];
        $this->postJson($this->base.'/vista-previa', $payload + ['numero_planilla' => 1])->assertNotFound();
    }
}
