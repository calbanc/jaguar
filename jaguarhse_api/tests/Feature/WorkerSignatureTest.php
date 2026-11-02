<?php

namespace Tests\Feature;

use App\Services\HseData;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\HseDatabase;
use Tests\TestCase;

class WorkerSignatureTest extends TestCase
{
    use HseDatabase;

    private function prepareWorker(): array
    {
        $this->installHseFixture();
        Storage::fake('firmas');
        config(['hse.clamscan_path' => null]);
        $tenant = $this->tenant('alpha');
        $site = DB::table('sedes')->insertGetId(['empresa_id' => $tenant['company'], 'codigo' => 'PIU', 'nombre' => 'Piura', 'pais' => 'PE']);
        $zone = DB::table('zonas')->insertGetId(['empresa_id' => $tenant['company'], 'sede_id' => $site, 'nombre' => 'Zona 1']);
        $this->loginAsTenant($tenant);
        $worker = $this->postJson('/api/v1/trabajadores', ['tipo_documento' => 'DNI', 'numero_documento' => '01234567', 'nombres' => 'Ana', 'apellidos' => 'Prueba', 'puesto' => 'Operadora', 'fecha_ingreso' => '2026-01-01', 'zona_id' => $zone])->assertCreated()->assertJsonPath('data.firma_almacenada', false)->json('data.id');

        return [$tenant, $worker];
    }

    public function test_upload_replace_and_document_correction_keep_the_signature_associated(): void
    {
        [$tenant, $worker] = $this->prepareWorker();
        $url = '/api/v1/trabajadores/'.$worker.'/firma';
        $this->getJson($url)->assertNotFound();
        $this->postJson($url, ['firma' => UploadedFile::fake()->image('firma.png', 300, 100)])->assertCreated()->assertJsonPath('data.firma_almacenada', true)->assertJsonMissingPath('data.firma_ruta');
        $old = DB::table('trabajadores')->where('id', $worker)->value('firma_ruta');
        $this->assertStringStartsWith('empresas/'.$tenant['company'].'/01234567/', $old);
        Storage::disk('firmas')->assertExists($old);
        $this->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->postJson($url, ['firma' => UploadedFile::fake()->image('otra.jpg')])->assertCreated();
        $replacement = DB::table('trabajadores')->where('id', $worker)->value('firma_ruta');
        Storage::disk('firmas')->assertMissing($old);
        $this->patchJson('/api/v1/trabajadores/'.$worker, ['numero_documento' => '01234568'])->assertOk()->assertJsonPath('data.firma_almacenada', true);
        $new = DB::table('trabajadores')->where('id', $worker)->value('firma_ruta');
        $this->assertStringStartsWith('empresas/'.$tenant['company'].'/01234568/', $new);
        Storage::disk('firmas')->assertExists($new);
        Storage::disk('firmas')->assertMissing($replacement);
        $this->assertDatabaseHas('auditoria_eventos', ['entidad' => 'trabajadores', 'registro_id' => $worker, 'accion' => 'guardar_firma']);
    }

    public function test_invalid_uploads_and_manual_flags_are_rejected(): void
    {
        [, $worker] = $this->prepareWorker();
        $url = '/api/v1/trabajadores/'.$worker.'/firma';
        foreach ([UploadedFile::fake()->create('firma.pdf', 10, 'application/pdf'), UploadedFile::fake()->image('firma.png')->size(2049), UploadedFile::fake()->image('firma.png', 4097, 10), UploadedFile::fake()->createWithContent('firma.png', '<script>bad</script>')] as $file) {
            $this->postJson($url, ['firma' => $file])->assertUnprocessable();
        }
        foreach (['firma_almacenada' => true, 'firma_ruta' => 'otro/archivo.png', 'firma_bytes' => 1, 'firma_actualizada_at' => '2026-10-09 12:00:00'] as $field => $value) {
            $this->patchJson('/api/v1/trabajadores/'.$worker, [$field => $value])->assertUnprocessable();
        }
        $this->assertDatabaseHas('trabajadores', ['id' => $worker, 'firma_almacenada' => false]);
        $this->assertSame([], Storage::disk('firmas')->allFiles());
    }

    public function test_signature_is_scoped_to_company_and_permissions(): void
    {
        [$tenant, $worker] = $this->prepareWorker();
        $url = '/api/v1/trabajadores/'.$worker.'/firma';
        $other = $this->tenant('beta');
        $this->loginAsTenant($other);
        $this->postJson($url, ['firma' => UploadedFile::fake()->image('firma.png')])->assertNotFound();
        $this->getJson($url)->assertNotFound();
        $this->loginAsTenant($tenant);
        $permission = DB::table('permisos')->where('codigo', 'organizacion.editar')->value('id');
        DB::table('rol_permisos')->where('rol_id', $tenant['role'])->where('permiso_id', $permission)->delete();
        $this->postJson($url, ['firma' => UploadedFile::fake()->image('firma.png')])->assertForbidden();
    }

    public function test_failed_audit_rolls_back_the_flag_and_cleans_up_the_file(): void
    {
        [, $worker] = $this->prepareWorker();
        $this->partialMock(HseData::class, function ($mock) {
            $mock->shouldReceive('audit')->andThrow(new \RuntimeException('Audit unavailable'));
        });
        $this->postJson('/api/v1/trabajadores/'.$worker.'/firma', ['firma' => UploadedFile::fake()->image('firma.png')])->assertStatus(500);
        $this->assertDatabaseHas('trabajadores', ['id' => $worker, 'firma_almacenada' => false, 'firma_ruta' => null]);
        $this->assertSame([], Storage::disk('firmas')->allFiles());
    }
}
