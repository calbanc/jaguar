<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\HseDatabase;
use Tests\TestCase;

class EquipmentSheetTest extends TestCase
{
    use HseDatabase;

    public function test_sheet_is_stored_linked_replaced_and_downloaded_with_warehouse_permissions(): void
    {
        $this->installHseFixture();
        Storage::fake('hse');
        $tenant = $this->tenant('alpha');
        $other = $this->tenant('beta');
        $id = DB::table('epp_catalogo')->insertGetId(['empresa_id' => $tenant['company'], 'codigo' => 'CASCO', 'nombre' => 'Casco']);
        $permissions = DB::table('permisos')->where('codigo', 'like', 'documentos.%')->pluck('id');
        DB::table('rol_permisos')->where('rol_id', $tenant['role'])->whereIn('permiso_id', $permissions)->delete();
        $this->loginAsTenant($tenant);
        $this->getJson('/api/v1/epp_catalogo/'.$id.'/ficha')->assertNotFound();
        $this->postJson('/api/v1/epp_catalogo/'.$id.'/ficha', ['archivo' => UploadedFile::fake()->create('texto.txt', 1, 'text/plain')])->assertUnprocessable();
        $file = $this->postJson('/api/v1/epp_catalogo/'.$id.'/ficha', ['archivo' => UploadedFile::fake()->image('ficha.png')])->assertCreated()->json('data');
        $record = DB::table('archivos')->where('id', $file['id'])->first();
        Storage::disk('hse')->assertExists($record->ruta);
        $this->assertStringStartsWith('empresas/'.$tenant['company'].'/', $record->ruta);
        $this->assertArrayNotHasKey('ruta', $file);
        $this->assertDatabaseHas('epp_catalogo', ['id' => $id, 'ficha_archivo_id' => $file['id']]);
        $this->getJson('/api/v1/epp_catalogo/'.$id)->assertOk()->assertJsonPath('data.ficha_tecnica', 'ficha.png');
        $this->get('/api/v1/epp_catalogo/'.$id.'/ficha')->assertOk()->assertDownload('ficha.png');
        $replacement = $this->postJson('/api/v1/epp_catalogo/'.$id.'/ficha', ['archivo' => UploadedFile::fake()->image('nueva.png')])->assertCreated()->json('data.id');
        $this->assertDatabaseHas('epp_catalogo', ['id' => $id, 'ficha_archivo_id' => $replacement]);
        $this->assertDatabaseHas('auditoria_eventos', ['entidad' => 'epp_catalogo', 'accion' => 'ficha_tecnica']);
        $this->loginAsTenant($other);
        $this->getJson('/api/v1/epp_catalogo/'.$id.'/ficha')->assertNotFound();
        $this->postJson('/api/v1/epp_catalogo/'.$id.'/ficha', ['archivo' => UploadedFile::fake()->image('ajena.png')])->assertNotFound();
    }

    public function test_sheet_upload_enforces_permissions_and_storage_limit(): void
    {
        $this->installHseFixture();
        Storage::fake('hse');
        $tenant = $this->tenant('alpha');
        $id = DB::table('epp_catalogo')->insertGetId(['empresa_id' => $tenant['company'], 'codigo' => 'CASCO', 'nombre' => 'Casco']);
        DB::table('contratos')->where('empresa_id', $tenant['company'])->update(['limite_almacenamiento_mb' => 0]);
        $this->loginAsTenant($tenant);
        $this->postJson('/api/v1/epp_catalogo/'.$id.'/ficha', ['archivo' => UploadedFile::fake()->image('ficha.png')])->assertUnprocessable();
        $this->assertDatabaseCount('archivos', 0);
        $permission = DB::table('permisos')->where('codigo', 'almacenes.editar')->value('id');
        DB::table('rol_permisos')->where('rol_id', $tenant['role'])->where('permiso_id', $permission)->delete();
        $this->postJson('/api/v1/epp_catalogo/'.$id.'/ficha', ['archivo' => UploadedFile::fake()->image('ficha.png')])->assertForbidden();
    }
}
