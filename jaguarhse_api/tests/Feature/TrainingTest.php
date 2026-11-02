<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Support\HseDatabase;
use Tests\TestCase;

class TrainingTest extends TestCase
{
    use HseDatabase;

    private array $tenant;

    private array $input;

    private const BASE = '/api/v1/capacitaciones/';

    private const CATALOGS = ['tema_capacitacion', 'lugar_capacitacion', 'capacitador', 'empresa_capacitacion'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->installHseFixture();
        (require database_path('migrations/2026_10_24_011304_create_training_tables.php'))->up();
        (require database_path('migrations/2026_10_24_012905_add_planilla_number_to_capacitacion.php'))->up();
        (require database_path('migrations/2026_10_24_013635_add_signature_to_training_participants.php'))->up();
        $this->tenant = $this->tenant('alpha');
        $this->loginAsTenant($this->tenant);
        $this->input = ['empresa_id' => $this->tenant['company'], 'fecha' => '2026-10-23', 'hora_inicio' => '09:00', 'hora_termino' => '10:00', 'responsable_registro_id' => $this->tenant['user']];
        foreach (self::CATALOGS as $catalog) {
            $this->input[$catalog.'_id'] = $this->postJson(self::BASE.$catalog, ['empresa_id' => $this->tenant['company'], 'nombre' => $catalog])->assertCreated()->json('data.id');
        }
        $this->input['trabajadores'] = [DB::table('trabajadores')->insertGetId(['empresa_id' => $this->tenant['company'], 'tipo_documento' => 'DNI', 'numero_documento' => '12345678', 'nombres' => 'Ana', 'apellidos' => 'Prueba'])];
    }

    public function test_catalogs_support_crud_and_require_company_on_every_method(): void
    {
        foreach (self::CATALOGS as $catalog) {
            $url = self::BASE.$catalog;
            $id = $this->input[$catalog.'_id'];
            $company = '?empresa_id='.$this->tenant['company'];
            $this->getJson($url)->assertUnprocessable();
            $this->postJson($url, ['nombre' => 'Otro'])->assertUnprocessable();
            $this->getJson($url.'/'.$id)->assertUnprocessable();
            $this->patchJson($url.'/'.$id, ['nombre' => 'Otro'])->assertUnprocessable();
            $this->deleteJson($url.'/'.$id)->assertUnprocessable();
            $this->getJson($url.$company)->assertOk()->assertJsonPath('total', 1);
            $this->getJson($url.'/'.$id.$company)->assertOk();
            $this->patchJson($url.'/'.$id, ['empresa_id' => $this->tenant['company'], 'nombre' => 'Actualizado'])->assertOk()->assertJsonPath('data.nombre', 'Actualizado');
            $this->deleteJson($url.'/'.$id.$company)->assertOk();
            $this->assertDatabaseHas($catalog, ['id' => $id, 'activo' => false]);
        }
    }

    public function test_training_crud_updates_participants_atomically_and_preserves_history(): void
    {
        $url = self::BASE.'capacitacion';
        $id = $this->postJson($url, $this->input)->assertCreated()->assertJsonPath('data.trabajadores', $this->input['trabajadores'])->json('data.id');
        $company = '?empresa_id='.$this->tenant['company'];
        $this->getJson($url.$company)->assertOk()->assertJsonPath('total', 1);
        $this->getJson($url.'/'.$id.$company)->assertOk();
        $this->patchJson($url.'/'.$id, ['empresa_id' => $this->tenant['company'], 'observaciones' => 'Actualizado'])->assertOk()->assertJsonPath('data.observaciones', 'Actualizado');
        $this->patchJson($url.'/'.$id, ['empresa_id' => $this->tenant['company'], 'hora_inicio' => '11:00'])->assertUnprocessable();
        $this->assertDatabaseHas('capacitacion', ['id' => $id, 'hora_inicio' => '09:00']);
        $worker = DB::table('trabajadores')->insertGetId(['empresa_id' => $this->tenant['company'], 'tipo_documento' => 'DNI', 'numero_documento' => '87654321', 'nombres' => 'Luis', 'apellidos' => 'Prueba']);
        $this->patchJson($url.'/'.$id, ['empresa_id' => $this->tenant['company'], 'trabajadores' => [$worker]])->assertOk()->assertJsonPath('data.trabajadores', [$worker]);
        $this->assertDatabaseHas('capacitacion_participantes', ['trabajador_id' => $this->input['trabajadores'][0], 'activo' => false]);
        $this->deleteJson(self::BASE.'tema_capacitacion/'.$this->input['tema_capacitacion_id'].$company)->assertUnprocessable();
        $this->deleteJson($url.'/'.$id.$company)->assertOk();
        $this->assertDatabaseHas('capacitacion', ['id' => $id, 'activo' => false]);
        $this->assertDatabaseHas('capacitacion_participantes', ['trabajador_id' => $worker, 'activo' => false]);
        $this->assertDatabaseHas('auditoria_eventos', ['empresa_id' => $this->tenant['company'], 'entidad' => 'capacitacion', 'registro_id' => $id, 'accion' => 'eliminar']);
    }

    public function test_invalid_dates_workers_and_cross_company_relationships_are_rejected(): void
    {
        $other = $this->tenant('beta');
        $invalid = [
            ['fecha' => '2026-02-30'], ['hora_termino' => '08:00'], ['hora_termino' => '09:00'],
            ['trabajadores' => [999999]],
            ['trabajadores' => [$this->input['trabajadores'][0], $this->input['trabajadores'][0]]],
            ['responsable_registro_id' => $other['user']],
        ];
        foreach (self::CATALOGS as $catalog) {
            $foreign = DB::table($catalog)->insertGetId(['empresa_id' => $other['company'], 'nombre' => 'Otra empresa']);
            $invalid[] = [$catalog.'_id' => $foreign];
        }
        $worker = DB::table('trabajadores')->insertGetId(['empresa_id' => $other['company'], 'tipo_documento' => 'DNI', 'numero_documento' => '12345678', 'nombres' => 'Otra', 'apellidos' => 'Empresa']);
        $invalid[] = ['trabajadores' => [$worker]];
        foreach ($invalid as $values) {
            $this->postJson(self::BASE.'capacitacion', array_replace($this->input, $values))->assertUnprocessable();
        }
        $this->assertDatabaseCount('capacitacion', 0);
        $this->assertDatabaseCount('capacitacion_participantes', 0);
        $this->postJson(self::BASE.'capacitacion', array_replace($this->input, ['empresa_id' => $other['company']]))->assertForbidden();
    }

    public function test_tenant_isolation_and_permissions_apply_to_all_crud_routes(): void
    {
        $id = $this->postJson(self::BASE.'capacitacion', $this->input)->assertCreated()->json('data.id');
        $other = $this->tenant('beta');
        $this->loginAsTenant($other);
        foreach (self::CATALOGS as $catalog) {
            $url = self::BASE.$catalog.'/'.$this->input[$catalog.'_id'].'?empresa_id='.$other['company'];
            $this->getJson($url)->assertNotFound();
            $this->patchJson($url, ['nombre' => 'Ajeno'])->assertNotFound();
            $this->deleteJson($url)->assertNotFound();
        }
        $url = self::BASE.'capacitacion/'.$id.'?empresa_id='.$other['company'];
        $this->getJson($url)->assertNotFound();
        $this->patchJson($url, ['observaciones' => 'Ajeno'])->assertNotFound();
        $this->deleteJson($url)->assertNotFound();
        $this->getJson(self::BASE.'capacitacion?empresa_id='.$other['company'])->assertOk()->assertJsonPath('total', 0);
        $this->loginAsTenant($this->tenant);
        DB::table('rol_permisos')->where('rol_id', $this->tenant['role'])->whereIn('permiso_id', DB::table('permisos')->where('codigo', 'like', 'capacitaciones.%')->select('id'))->delete();
        foreach (array_merge(self::CATALOGS, ['capacitacion']) as $resource) {
            $record = $resource === 'capacitacion' ? $id : $this->input[$resource.'_id'];
            $base = self::BASE.$resource;
            $company = '?empresa_id='.$this->tenant['company'];
            $this->getJson($base.$company)->assertForbidden();
            $this->getJson($base.'/'.$record.$company)->assertForbidden();
            $this->postJson($base, $this->input)->assertForbidden();
            $this->patchJson($base.'/'.$record, $this->input)->assertForbidden();
            $this->deleteJson($base.'/'.$record.$company)->assertForbidden();
        }
    }

    public function test_database_foreign_keys_reject_cross_company_catalogs(): void
    {
        $other = $this->tenant('beta');
        $foreign = DB::table('tema_capacitacion')->insertGetId(['empresa_id' => $other['company'], 'nombre' => 'Ajeno']);
        $values = $this->input;
        unset($values['trabajadores']);
        $values['tema_capacitacion_id'] = $foreign;
        $values['numero_planilla'] = 1;
        $this->expectException(QueryException::class);
        DB::table('capacitacion')->insert($values);
    }

    public function test_planilla_numbers_are_automatic_per_company_and_not_reused_or_editable(): void
    {
        $url = self::BASE.'capacitacion';
        $this->postJson($url, $this->input + ['numero_planilla' => 99])->assertUnprocessable();
        $first = $this->postJson($url, $this->input)->assertCreated()->assertJsonPath('data.numero_planilla', 1)->json('data.id');
        $second = $this->postJson($url, $this->input)->assertCreated()->assertJsonPath('data.numero_planilla', 2)->json('data.id');
        $this->patchJson($url.'/'.$first, ['empresa_id' => $this->tenant['company'], 'numero_planilla' => 8])->assertUnprocessable();
        $this->patchJson($url.'/'.$first, ['empresa_id' => $this->tenant['company'], 'observaciones' => 'Corrección'])->assertOk()->assertJsonPath('data.numero_planilla', 1);
        $this->deleteJson($url.'/'.$second.'?empresa_id='.$this->tenant['company'])->assertOk();
        $this->postJson($url, array_replace($this->input, ['trabajadores' => [999999]]))->assertUnprocessable();
        $this->postJson($url, $this->input)->assertCreated()->assertJsonPath('data.numero_planilla', 3);
        $other = $this->tenant('beta');
        $this->loginAsTenant($other);
        $values = array_replace($this->input, ['empresa_id' => $other['company'], 'responsable_registro_id' => $other['user']]);
        foreach (self::CATALOGS as $catalog) {
            $values[$catalog.'_id'] = DB::table($catalog)->insertGetId(['empresa_id' => $other['company'], 'nombre' => 'Otro']);
        }
        $values['trabajadores'] = [DB::table('trabajadores')->insertGetId(['empresa_id' => $other['company'], 'tipo_documento' => 'DNI', 'numero_documento' => '12345678', 'nombres' => 'Otro', 'apellidos' => 'Trabajador'])];
        $this->postJson($url, $values)->assertCreated()->assertJsonPath('data.numero_planilla', 1);
    }

    public function test_database_rejects_duplicate_planilla_numbers_in_a_company(): void
    {
        $this->postJson(self::BASE.'capacitacion', $this->input)->assertCreated();
        $values = (array) DB::table('capacitacion')->first();
        unset($values['id']);
        $this->expectException(QueryException::class);
        DB::table('capacitacion')->insert($values);
    }

    public function test_migration_numbers_existing_planillas_and_keeps_children(): void
    {
        $url = self::BASE.'capacitacion';
        $first = $this->postJson($url, $this->input)->assertCreated()->json('data.id');
        $second = $this->postJson($url, $this->input)->assertCreated()->json('data.id');
        $this->deleteJson($url.'/'.$first.'?empresa_id='.$this->tenant['company'])->assertOk();
        Schema::table('capacitacion', function (Blueprint $table) {
            $table->dropUnique('uq_capacitacion_planilla_empresa');
            $table->dropColumn('numero_planilla');
        });
        (require database_path('migrations/2026_10_24_012905_add_planilla_number_to_capacitacion.php'))->up();
        $this->assertDatabaseHas('capacitacion', ['id' => $first, 'numero_planilla' => 1, 'activo' => false]);
        $this->assertDatabaseHas('capacitacion', ['id' => $second, 'numero_planilla' => 2]);
        $this->assertDatabaseCount('capacitacion_participantes', 2);
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
        $this->postJson($url, $this->input)->assertCreated()->assertJsonPath('data.numero_planilla', 3);
    }

    public function test_parent_can_be_saved_before_workers_and_options_and_lookup_are_company_scoped(): void
    {
        $this->getJson(self::BASE.'opciones?empresa_id='.$this->tenant['company'])->assertOk()->assertJsonPath('siguiente_numero', 1)->assertJsonCount(1, 'trabajadores');
        $input = $this->input;
        unset($input['trabajadores']);
        $id = $this->postJson(self::BASE.'capacitacion', $input)->assertCreated()->assertJsonPath('data.trabajadores', [])->json('data.id');
        $this->getJson(self::BASE.'planillas/1?empresa_id='.$this->tenant['company'])->assertOk()->assertJsonPath('data.id', $id);
        $this->getJson(self::BASE.'opciones?empresa_id='.$this->tenant['company'])->assertOk()->assertJsonPath('siguiente_numero', 2);
        $other = $this->tenant('beta');
        $this->loginAsTenant($other);
        $this->getJson(self::BASE.'planillas/1?empresa_id='.$other['company'])->assertNotFound();
        $this->getJson(self::BASE.'opciones?empresa_id='.$other['company'])->assertOk()->assertJsonCount(0, 'trabajadores');
        $this->getJson(self::BASE.'opciones')->assertUnprocessable();
        $this->getJson(self::BASE.'opciones?empresa_id='.$this->tenant['company'])->assertForbidden();
    }

    public function test_participant_registration_signature_and_removal_preserve_tenant_and_planilla(): void
    {
        Storage::fake('firmas_capacitaciones');
        config(['hse.clamscan_path' => null]);
        $input = $this->input;
        unset($input['trabajadores']);
        $id = $this->postJson(self::BASE.'capacitacion', $input)->assertCreated()->json('data.id');
        $url = self::BASE.'capacitacion/'.$id.'/participantes';
        $body = ['empresa_id' => $this->tenant['company'], 'trabajador_id' => $this->input['trabajadores'][0]];
        $participant = $this->postJson($url, $body)->assertCreated()->json('data.id');
        $this->postJson($url, $body)->assertUnprocessable();
        $query = '?empresa_id='.$this->tenant['company'];
        $this->getJson($url.$query)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.numero_documento', '12345678');
        $signature = $url.'/'.$participant.'/firma';
        $this->postJson($signature, ['empresa_id' => $this->tenant['company'], 'firma' => UploadedFile::fake()->createWithContent('firma.png', 'invalid')])->assertUnprocessable();
        $this->postJson($signature, ['empresa_id' => $this->tenant['company'], 'firma' => UploadedFile::fake()->image('firma.png', 300, 100)])->assertCreated();
        $path = DB::table('capacitacion_participantes')->where('id', $participant)->value('firma_ruta');
        $this->assertSame('12345678.png', basename($path));
        $this->assertStringStartsWith($this->tenant['company'].'/1/', $path);
        Storage::disk('firmas_capacitaciones')->assertExists($path);
        $this->get($signature.$query)->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->postJson($signature, ['empresa_id' => $this->tenant['company'], 'firma' => UploadedFile::fake()->image('firma.png', 300, 100)])->assertUnprocessable();
        $other = $this->tenant('beta');
        $this->loginAsTenant($other);
        $this->getJson($url.'?empresa_id='.$other['company'])->assertNotFound();
        $this->getJson($signature.'?empresa_id='.$other['company'])->assertNotFound();
        $this->loginAsTenant($this->tenant);
        $this->deleteJson($url.'/'.$participant.$query)->assertOk();
        $this->getJson($url.$query)->assertOk()->assertJsonCount(0, 'data');
        Storage::disk('firmas_capacitaciones')->assertExists($path);
    }

    public function test_existing_worker_signature_can_be_copied_and_planilla_signed_again(): void
    {
        Storage::fake('firmas');
        Storage::fake('firmas_capacitaciones');
        config(['hse.clamscan_path' => null]);
        $source = UploadedFile::fake()->image('12345678.jpg', 300, 100);
        Storage::disk('firmas')->put('worker/12345678.jpg', file_get_contents($source->getRealPath()));
        $worker = $this->input['trabajadores'][0];
        DB::table('trabajadores')->where('id', $worker)->update(['firma_almacenada' => true, 'firma_ruta' => 'worker/12345678.jpg']);
        $id = $this->postJson(self::BASE.'capacitacion', $this->input)->assertCreated()->json('data.id');
        $url = self::BASE.'capacitacion/'.$id.'/participantes';
        $rows = $this->getJson($url.'?empresa_id='.$this->tenant['company'])->assertOk()->json('data');
        $this->assertTrue((bool) $rows[0]['firma_disponible']);
        $this->assertNull($rows[0]['firma_fecha']);
        $url .= '/'.$rows[0]['id'].'/firma';
        $this->get($url.'?empresa_id='.$this->tenant['company'].'&fuente=trabajador')->assertOk();
        $this->postJson($url, ['empresa_id' => $this->tenant['company'], 'usar_guardada' => true])->assertCreated();
        $copy = DB::table('capacitacion_participantes')->where('id', $rows[0]['id'])->value('firma_ruta');
        $this->assertSame('12345678.jpg', basename($copy));
        $this->assertSame(Storage::disk('firmas')->get('worker/12345678.jpg'), Storage::disk('firmas_capacitaciones')->get($copy));
        $this->get($url.'?empresa_id='.$this->tenant['company'])->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->postJson($url, ['empresa_id' => $this->tenant['company'], 'reemplazar' => true, 'firma' => UploadedFile::fake()->image('nueva.png', 300, 100)])->assertCreated();
        $new = DB::table('capacitacion_participantes')->where('id', $rows[0]['id'])->value('firma_ruta');
        $this->assertNotSame($copy, $new);
        Storage::disk('firmas_capacitaciones')->assertExists($copy);
        Storage::disk('firmas_capacitaciones')->assertExists($new);
        $this->assertDatabaseHas('trabajadores', ['id' => $worker, 'firma_ruta' => 'worker/12345678.jpg']);
        $other = $this->tenant('beta');
        $this->loginAsTenant($other);
        $this->getJson($url.'?empresa_id='.$other['company'].'&fuente=trabajador')->assertNotFound();
        $this->postJson($url, ['empresa_id' => $other['company'], 'usar_guardada' => true])->assertNotFound();
    }
}
