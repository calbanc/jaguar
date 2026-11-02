<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\HseDatabase;
use Tests\TestCase;

class HseApiTest extends TestCase
{
    use HseDatabase;

    private array $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installHseFixture();
        $this->tenant = $this->tenant('alpha');
    }

    private function create(string $resource, array $input): array
    {
        return $this->postJson('/api/v1/'.$resource, $input)->assertCreated()->json('data');
    }

    public function test_login_stores_only_hash_and_logout_revokes_token(): void
    {
        $token = $this->loginAsTenant($this->tenant);
        $this->assertSame(hash('sha256', $token), DB::table('sesiones')->value('token_hash'));
        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.empresa_id', $this->tenant['company']);
        $this->postJson('/api/v1/auth/logout')->assertOk();
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_missing_token_disabled_access_and_expired_contract_are_rejected(): void
    {
        $this->getJson('/api/v1/sedes')->assertUnauthorized();
        $this->loginAsTenant($this->tenant);
        DB::table('contratos')->update(['fecha_fin' => '2021-01-01']);
        $this->getJson('/api/v1/sedes')->assertForbidden();
        DB::table('contratos')->update(['fecha_fin' => null]);
        DB::table('accesos_aplicacion')->update(['activo' => false]);
        $this->getJson('/api/v1/sedes')->assertUnauthorized();
    }

    public function test_tenant_scope_foreign_keys_and_server_owned_fields(): void
    {
        $other = $this->tenant('beta');
        $otherSite = DB::table('sedes')->insertGetId(['pais' => 'PE', 'empresa_id' => $other['company'], 'codigo' => 'B', 'nombre' => 'Privado']);
        $this->loginAsTenant($this->tenant);
        $this->getJson('/api/v1/sedes')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/sedes/'.$otherSite)->assertNotFound();
        $this->patchJson('/api/v1/sedes/'.$otherSite, ['nombre' => 'Ataque'])->assertNotFound();
        $this->postJson('/api/v1/areas', ['sede_id' => $otherSite, 'nombre' => 'Cruce'])->assertUnprocessable();
        $this->postJson('/api/v1/sedes', ['empresa_id' => $other['company'], 'codigo' => 'A', 'nombre' => 'Cruce'])->assertUnprocessable();
        $this->getJson('/api/v1/usuarios')->assertNotFound();
        $site = $this->create('sedes', ['pais' => 'PE', 'codigo' => 'A', 'nombre' => 'Propio']);
        $this->assertDatabaseHas('auditoria_eventos', ['empresa_id' => $this->tenant['company'], 'entidad' => 'sedes', 'registro_id' => $site['id']]);
    }

    public function test_permission_and_module_are_both_required(): void
    {
        $this->loginAsTenant($this->tenant);
        $permission = DB::table('permisos')->where('codigo', 'organizacion.ver')->value('id');
        DB::table('rol_permisos')->where('permiso_id', $permission)->delete();
        $this->getJson('/api/v1/sedes')->assertForbidden();
        DB::table('empresa_modulos')->update(['activo' => false]);
        $this->postJson('/api/v1/sedes', ['codigo' => 'A', 'nombre' => 'A'])->assertForbidden();
    }

    public function test_checklist_required_false_answer_publishing_and_immutability(): void
    {
        $this->loginAsTenant($this->tenant);
        $template = $this->create('checklist_plantillas', ['codigo' => 'C', 'nombre' => 'Inspección']);
        $section = $this->create('checklist_secciones', ['plantilla_id' => $template['id'], 'titulo' => 'General']);
        $question = $this->create('checklist_preguntas', ['plantilla_id' => $template['id'], 'seccion_id' => $section['id'], 'codigo' => 'Q1', 'pregunta' => '¿Funciona?', 'tipo' => 'si_no']);
        $this->postJson('/api/v1/checklist_plantillas/'.$template['id'].'/publicar')->assertOk();
        $this->getJson('/api/v1/checklist_plantillas/'.$template['id'].'/formulario')->assertOk()->assertJsonPath('data.secciones.0.preguntas.0.id', $question['id']);
        $this->patchJson('/api/v1/checklist_preguntas/'.$question['id'], ['pregunta' => 'Cambio'])->assertConflict();
        $record = $this->create('checklist_registros', ['plantilla_id' => $template['id'], 'uuid_cliente' => (string) Str::uuid(), 'fecha' => '2026-09-22 12:00:00']);
        $this->postJson('/api/v1/checklist_registros/'.$record['id'].'/enviar')->assertUnprocessable();
        $this->postJson('/api/v1/checklist_registros/'.$record['id'].'/respuestas', ['respuestas' => [['pregunta_id' => $question['id'], 'valor_texto' => 'Incorrecto']]])->assertUnprocessable();
        $this->postJson('/api/v1/checklist_registros/'.$record['id'].'/respuestas', ['respuestas' => [['pregunta_id' => $question['id'], 'valor_booleano' => false]]])->assertOk();
        $this->postJson('/api/v1/checklist_registros/'.$record['id'].'/enviar')->assertOk();
        $this->getJson('/api/v1/checklist_registros/'.$record['id'].'/puntaje')->assertOk()->assertJsonPath('data.puntaje', null);
        $this->getJson('/api/v1/checklist_registros/'.$record['id'].'/detalle')->assertOk()->assertJsonPath('data.respuestas.0.valor_booleano', 0);
        $this->postJson('/api/v1/checklist_registros/'.$record['id'].'/respuestas', ['respuestas' => [['pregunta_id' => $question['id'], 'valor_booleano' => true]]])->assertConflict();
        $this->postJson('/api/v1/checklist_plantillas/'.$template['id'].'/versionar')->assertOk()->assertJsonPath('data.version', 2);
    }

    public function test_inventory_confirmation_rollback_idempotency_and_reversal(): void
    {
        $this->loginAsTenant($this->tenant);
        $site = $this->create('sedes', ['pais' => 'PE', 'codigo' => 'S', 'nombre' => 'Sede']);
        $warehouse = $this->create('almacenes', ['sede_id' => $site['id'], 'nombre' => 'Almacén']);
        $zone = $this->create('zonas', ['sede_id' => $site['id'], 'nombre' => 'Zona 1']);
        $worker = $this->create('trabajadores', ['zona_id' => $zone['id'], 'tipo_documento' => 'DNI', 'numero_documento' => '12345678', 'nombres' => 'Ana', 'apellidos' => 'Pérez', 'puesto' => 'Operadora', 'fecha_ingreso' => '2026-01-15']);
        $item = $this->create('epp_catalogo', ['codigo' => 'CAS', 'nombre' => 'Casco']);
        $delivery = $this->create('epp_entregas', ['uuid_cliente' => (string) Str::uuid(), 'trabajador_id' => $worker['id'], 'fecha' => '2026-09-22 12:00:00']);
        $this->create('epp_entrega_detalles', ['entrega_id' => $delivery['id'], 'epp_id' => $item['id'], 'almacen_id' => $warehouse['id'], 'cantidad' => 2]);
        $url = '/api/v1/epp_entregas/'.$delivery['id'];
        $this->postJson($url.'/confirmar')->assertUnprocessable();
        $this->assertDatabaseHas('epp_entregas', ['id' => $delivery['id'], 'estado' => 'borrador']);
        $this->postJson('/api/v1/inventario/movimientos', ['epp_id' => $item['id'], 'almacen_id' => $warehouse['id'], 'cantidad' => 5, 'tipo' => 'entrada', 'costo_unitario' => '10.00', 'clave_operacion' => 'entrada-1'])->assertCreated();
        $this->postJson($url.'/confirmar')->assertOk();
        $this->postJson($url.'/confirmar')->assertOk();
        $this->assertEquals(3, DB::table('epp_movimientos')->sum('cantidad'));
        $this->postJson($url.'/anular')->assertOk();
        $this->postJson($url.'/anular')->assertOk();
        $this->assertEquals(5, DB::table('epp_movimientos')->sum('cantidad'));
        $this->assertSame(3, DB::table('epp_movimientos')->count());
        $this->patchJson($url, ['observacion' => 'Cambio'])->assertConflict();
    }

    public function test_document_review_is_atomic_and_version_cannot_be_rewritten(): void
    {
        Storage::fake('hse');
        $this->loginAsTenant($this->tenant);
        $file = $this->postJson('/api/v1/archivos/subir', ['archivo' => UploadedFile::fake()->image('foto.png')])->assertCreated()->json('data');
        $this->assertArrayNotHasKey('ruta', $file);
        $module = DB::table('modulos')->where('codigo', 'documentos')->value('id');
        $topic = $this->create('temas_hse', ['modulo_id' => $module, 'codigo' => 'POL', 'nombre' => 'Política']);
        $requirement = $this->create('requisitos_documentales', ['tema_id' => $topic['id'], 'nombre' => 'Documento']);
        $version = $this->create('documento_versiones', ['requisito_id' => $requirement['id'], 'archivo_id' => $file['id']]);
        $this->postJson('/api/v1/documento_versiones/'.$version['id'].'/revisar', ['resultado' => 'aprobado'])->assertOk();
        $this->assertDatabaseHas('documento_revisiones', ['documento_version_id' => $version['id'], 'resultado' => 'aprobado']);
        $this->patchJson('/api/v1/documento_versiones/'.$version['id'], ['archivo_id' => $file['id'], 'requisito_id' => $requirement['id']])->assertConflict();
        $this->getJson('/api/v1/archivos/'.$file['id'].'/descargar')->assertOk();
    }

    public function test_indicator_type_and_month_validation_and_no_public_audit_write(): void
    {
        $this->loginAsTenant($this->tenant);
        $indicator = $this->create('indicadores', ['codigo' => 'I', 'nombre' => 'Inspecciones', 'unidad' => '%', 'tipo_calculo' => 'razon']);
        $input = ['indicador_id' => $indicator['id'], 'periodo' => '2026-09-01', 'meta' => 95, 'numerador' => 18, 'denominador' => 20];
        $this->create('indicador_mediciones', $input);
        $this->postJson('/api/v1/indicador_mediciones', $input)->assertUnprocessable();
        $this->postJson('/api/v1/indicador_mediciones', array_merge($input, ['periodo' => '2026-10-15']))->assertUnprocessable();
        $this->patchJson('/api/v1/indicadores/'.$indicator['id'], ['tipo_calculo' => 'manual'])->assertUnprocessable();
        $this->postJson('/api/v1/auditoria_eventos', [])->assertStatus(405);
        $this->postJson('/api/v1/epp_movimientos', [])->assertStatus(405);
    }

    public function test_selection_evidence_rules_score_and_atomic_answer_replacement(): void
    {
        $this->loginAsTenant($this->tenant);
        $template = $this->create('checklist_plantillas', ['codigo' => 'SELECT', 'nombre' => 'Inspección']);
        $section = $this->create('checklist_secciones', ['plantilla_id' => $template['id'], 'titulo' => 'General']);
        $question = $this->create('checklist_preguntas', ['plantilla_id' => $template['id'], 'seccion_id' => $section['id'], 'codigo' => 'Q1', 'pregunta' => 'Estado', 'tipo' => 'seleccion_multiple', 'peso' => 2]);
        $yes = $this->create('checklist_pregunta_opciones', ['pregunta_id' => $question['id'], 'valor' => 'si', 'etiqueta' => 'Bueno', 'resultado' => 'cumple']);
        $no = $this->create('checklist_pregunta_opciones', ['pregunta_id' => $question['id'], 'valor' => 'no', 'etiqueta' => 'Malo', 'resultado' => 'no_cumple', 'exige_observacion' => true]);
        $na = $this->create('checklist_pregunta_opciones', ['pregunta_id' => $question['id'], 'valor' => 'na', 'etiqueta' => 'No aplica', 'resultado' => 'no_aplica']);
        $this->postJson('/api/v1/checklist_plantillas/'.$template['id'].'/publicar')->assertOk();
        $record = $this->create('checklist_registros', ['plantilla_id' => $template['id'], 'uuid_cliente' => (string) Str::uuid(), 'fecha' => '2026-09-22 12:00:00']);
        $url = '/api/v1/checklist_registros/'.$record['id'];
        $this->postJson($url.'/respuestas', ['respuestas' => [['pregunta_id' => $question['id'], 'opciones' => [$no['id']]]]])->assertOk();
        $this->postJson($url.'/enviar')->assertUnprocessable();
        $this->postJson($url.'/respuestas', ['respuestas' => [['pregunta_id' => $question['id'], 'opciones' => [$yes['id'], $na['id']]]]])->assertOk();
        $this->postJson($url.'/enviar')->assertUnprocessable();
        $this->postJson($url.'/respuestas', ['respuestas' => [['pregunta_id' => $question['id'], 'opciones' => [$yes['id']]]]])->assertOk();
        $this->postJson($url.'/enviar')->assertOk();
        $this->getJson($url.'/puntaje')->assertOk()->assertJsonPath('data.puntaje', 100);
        $this->assertSame(1, DB::table('checklist_respuesta_opciones')->count());
    }

    public function test_offline_uuid_retry_conflict_and_direct_state_change(): void
    {
        $this->loginAsTenant($this->tenant);
        $input = ['uuid_cliente' => (string) Str::uuid(), 'titulo' => 'Riesgo', 'descripcion' => 'Piso mojado', 'fecha' => '2026-09-22 12:00:00'];
        $finding = $this->create('hallazgos', $input);
        $this->postJson('/api/v1/hallazgos', $input)->assertOk()->assertJsonPath('data.id', $finding['id']);
        $this->postJson('/api/v1/hallazgos', array_merge($input, ['titulo' => 'Otro']))->assertConflict();
        $this->patchJson('/api/v1/hallazgos/'.$finding['id'], ['estado' => 'cerrado'])->assertUnprocessable();
        $this->postJson('/api/v1/hallazgos/'.$finding['id'].'/transicion', ['estado' => 'cerrado', 'comentario' => 'Sin validar'])->assertConflict();
        foreach (['en_proceso', 'por_validar', 'cerrado'] as $state) {
            $this->postJson('/api/v1/hallazgos/'.$finding['id'].'/transicion', ['estado' => $state, 'comentario' => 'Verificado'])->assertOk();
        }
        $this->assertDatabaseHas('hallazgos', ['id' => $finding['id'], 'cerrado_por' => $this->tenant['member']]);
        $this->assertSame(3, DB::table('hallazgo_seguimientos')->count());
    }

    public function test_private_file_scope_confidential_permission_and_quota(): void
    {
        Storage::fake('hse');
        $other = $this->tenant('beta');
        $this->loginAsTenant($this->tenant);
        $file = $this->postJson('/api/v1/archivos/subir', ['archivo' => UploadedFile::fake()->image('foto.png'), 'clasificacion' => 'confidencial'])->assertCreated()->json('data');
        $permission = DB::table('permisos')->where('codigo', 'documentos.confidenciales')->value('id');
        DB::table('rol_permisos')->where('empresa_id', $this->tenant['company'])->where('permiso_id', $permission)->delete();
        $this->getJson('/api/v1/archivos/'.$file['id'].'/descargar')->assertForbidden();
        DB::table('contratos')->where('empresa_id', $this->tenant['company'])->update(['limite_almacenamiento_mb' => 0]);
        $this->postJson('/api/v1/archivos/subir', ['archivo' => UploadedFile::fake()->image('otra.png')])->assertUnprocessable();
        $this->loginAsTenant($other);
        $this->getJson('/api/v1/archivos/'.$file['id'].'/descargar')->assertNotFound();
        $this->assertCount(1, Storage::disk('hse')->allFiles());
    }

    public function test_administrator_can_delegate_but_not_escalate_permissions(): void
    {
        $this->loginAsTenant($this->tenant);
        $permission = DB::table('permisos')->where('codigo', 'documentos.ver')->value('id');
        $role = $this->postJson('/api/v1/administracion/roles', ['nombre' => 'Consulta', 'permisos' => [$permission]])->assertCreated()->json('data');
        $member = $this->postJson('/api/v1/administracion/miembros', ['nombre' => 'Operador', 'email' => 'operator@example.test', 'password' => 'password-test-456'])->assertCreated()->json('data');
        $this->postJson('/api/v1/administracion/accesos', ['miembro_id' => $member['id'], 'rol_id' => $role['id'], 'plataforma_id' => 1, 'activo' => true])->assertOk();
        $this->patchJson('/api/v1/administracion/roles/'.$this->tenant['role'], ['nombre' => 'Mío', 'permisos' => []])->assertUnprocessable();
        DB::table('rol_permisos')->where('empresa_id', $this->tenant['company'])->where('rol_id', $this->tenant['role'])->where('permiso_id', $permission)->delete();
        $this->postJson('/api/v1/administracion/roles', ['nombre' => 'Escalada', 'permisos' => [$permission]])->assertForbidden();
    }

    public function test_returning_equipment_is_bounded_and_cancellation_reverses_only_remaining_quantity(): void
    {
        $this->loginAsTenant($this->tenant);
        $site = $this->create('sedes', ['pais' => 'PE', 'codigo' => 'S', 'nombre' => 'Sede']);
        $warehouse = $this->create('almacenes', ['sede_id' => $site['id'], 'nombre' => 'Almacén']);
        $zone = $this->create('zonas', ['sede_id' => $site['id'], 'nombre' => 'Zona 1']);
        $worker = $this->create('trabajadores', ['zona_id' => $zone['id'], 'tipo_documento' => 'DNI', 'numero_documento' => '12345678', 'nombres' => 'Ana', 'apellidos' => 'Pérez', 'puesto' => 'Operadora', 'fecha_ingreso' => '2026-01-15']);
        $item = $this->create('epp_catalogo', ['codigo' => 'CAS', 'nombre' => 'Casco']);
        $delivery = $this->create('epp_entregas', ['uuid_cliente' => (string) Str::uuid(), 'trabajador_id' => $worker['id'], 'fecha' => '2026-09-22 12:00:00']);
        $detail = $this->create('epp_entrega_detalles', ['entrega_id' => $delivery['id'], 'epp_id' => $item['id'], 'almacen_id' => $warehouse['id'], 'cantidad' => 3]);
        $this->postJson('/api/v1/inventario/movimientos', ['epp_id' => $item['id'], 'almacen_id' => $warehouse['id'], 'cantidad' => 5, 'tipo' => 'entrada', 'costo_unitario' => '10.00', 'clave_operacion' => 'entrada'])->assertCreated();
        $this->postJson('/api/v1/epp_entregas/'.$delivery['id'].'/confirmar')->assertOk();
        $return = ['entrega_detalle_id' => $detail['id'], 'cantidad' => 1, 'clave_operacion' => 'devolucion'];
        $this->postJson('/api/v1/inventario/devoluciones', $return)->assertCreated();
        $this->postJson('/api/v1/inventario/devoluciones', $return)->assertCreated();
        $this->postJson('/api/v1/inventario/devoluciones', array_merge($return, ['cantidad' => 3, 'clave_operacion' => 'exceso']))->assertUnprocessable();
        $this->postJson('/api/v1/epp_entregas/'.$delivery['id'].'/anular')->assertOk();
        $this->assertEquals(5, DB::table('epp_movimientos')->sum('cantidad'));
        $this->assertEquals(2, DB::table('epp_movimientos')->where('tipo', 'reverso')->value('cantidad'));
        $this->getJson('/api/v1/reportes/stock')->assertOk()->assertJsonPath('data.0.stock', 5);
    }

    public function test_reports_calculate_ratios_and_are_scoped_to_company(): void
    {
        $other = $this->tenant('beta');
        DB::table('indicadores')->insert(['empresa_id' => $other['company'], 'codigo' => 'PRIVADO', 'nombre' => 'Otro', 'unidad' => '%']);
        $this->loginAsTenant($this->tenant);
        $indicator = $this->create('indicadores', ['codigo' => 'I', 'nombre' => 'Inspecciones', 'unidad' => '%', 'tipo_calculo' => 'razon']);
        $this->create('indicador_mediciones', ['indicador_id' => $indicator['id'], 'periodo' => '2026-09-01', 'meta' => 95, 'numerador' => 18.0, 'denominador' => 20.0]);
        $this->getJson('/api/v1/reportes/indicadores')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.estado', 'no_cumple')->assertJsonPath('data.0.valor', 90);
        $this->getJson('/api/v1/reportes/temas')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_password_change_revokes_all_own_sessions_but_not_another_user(): void
    {
        $other = $this->tenant('beta');
        $this->loginAsTenant($other);
        $otherSession = DB::table('sesiones')->where('empresa_id', $other['company'])->value('id');
        $this->loginAsTenant($this->tenant);
        $this->getJson('/api/v1/auth/sesiones')->assertOk()->assertJsonCount(1, 'data')->assertJsonMissingPath('data.0.token_hash');
        $this->deleteJson('/api/v1/auth/sesiones/'.$otherSession)->assertNotFound();
        $this->postJson('/api/v1/auth/password', ['password_actual' => 'password-test-123', 'password' => 'new-password-test-456', 'password_confirmation' => 'new-password-test-456'])->assertOk();
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->assertNull(DB::table('sesiones')->where('id', $otherSession)->value('revocado_at'));
        $this->postJson('/api/v1/auth/login', ['email' => 'alpha@example.test', 'password' => 'password-test-123', 'empresa_codigo' => 'alpha', 'plataforma' => 'web'])->assertUnauthorized();
        $this->postJson('/api/v1/auth/login', ['email' => 'alpha@example.test', 'password' => 'new-password-test-456', 'empresa_codigo' => 'alpha', 'plataforma' => 'web'])->assertOk();
    }

    public function test_member_status_is_tenant_scoped_and_user_quota_is_enforced(): void
    {
        $other = $this->tenant('beta');
        $this->loginAsTenant($this->tenant);
        $this->getJson('/api/v1/administracion/miembros')->assertOk()->assertJsonCount(1, 'data')->assertJsonMissingPath('data.0.password_hash');
        $this->patchJson('/api/v1/administracion/miembros/'.$other['member'], ['activo' => false])->assertNotFound();
        $this->patchJson('/api/v1/administracion/miembros/'.$this->tenant['member'], ['activo' => false])->assertUnprocessable();
        DB::table('contratos')->where('empresa_id', $this->tenant['company'])->update(['limite_usuarios' => 1]);
        $this->postJson('/api/v1/administracion/miembros', ['nombre' => 'Extra', 'email' => 'extra@example.test', 'password' => 'long-password-123'])->assertUnprocessable();
        $this->assertSame(2, DB::table('usuarios')->count());
    }

    public function test_finding_cannot_close_until_corrective_actions_are_completed(): void
    {
        $this->loginAsTenant($this->tenant);
        $finding = $this->create('hallazgos', ['uuid_cliente' => (string) Str::uuid(), 'titulo' => 'Riesgo', 'descripcion' => 'Piso mojado', 'fecha' => '2026-09-22 12:00:00']);
        $action = $this->create('acciones_correctivas', ['hallazgo_id' => $finding['id'], 'responsable_id' => $this->tenant['member'], 'descripcion' => 'Limpiar', 'fecha_limite' => '2026-09-23']);
        foreach (['en_proceso', 'por_validar'] as $state) {
            $this->postJson('/api/v1/hallazgos/'.$finding['id'].'/transicion', ['estado' => $state, 'comentario' => 'Revisión'])->assertOk();
        }
        $this->postJson('/api/v1/hallazgos/'.$finding['id'].'/transicion', ['estado' => 'cerrado', 'comentario' => 'Cierre'])->assertUnprocessable();
        foreach (['en_proceso', 'por_validar', 'completada'] as $state) {
            $this->postJson('/api/v1/acciones_correctivas/'.$action['id'].'/transicion', ['estado' => $state, 'comentario' => 'Validado'])->assertOk();
        }
        $this->postJson('/api/v1/hallazgos/'.$finding['id'].'/transicion', ['estado' => 'cerrado', 'comentario' => 'Cierre'])->assertOk();
        $this->assertDatabaseHas('acciones_correctivas', ['id' => $action['id'], 'estado' => 'completada']);
    }

    public function test_question_json_configuration_survives_partial_updates_and_answer_changes_are_audited(): void
    {
        $this->loginAsTenant($this->tenant);
        $template = $this->create('checklist_plantillas', ['codigo' => 'JSON', 'nombre' => 'Inspección']);
        $section = $this->create('checklist_secciones', ['plantilla_id' => $template['id'], 'titulo' => 'General']);
        $question = $this->create('checklist_preguntas', ['plantilla_id' => $template['id'], 'seccion_id' => $section['id'], 'codigo' => 'Q1',
            'pregunta' => 'Observación opcional', 'tipo' => 'texto', 'obligatoria' => false, 'longitud_min' => 5, 'mime_permitidos' => ['image/png']]);
        $this->patchJson('/api/v1/checklist_preguntas/'.$question['id'], ['ayuda' => 'Ayuda actualizada'])->assertOk()->assertJsonPath('data.mime_permitidos.0', 'image/png');
        $this->postJson('/api/v1/checklist_plantillas/'.$template['id'].'/publicar')->assertOk();
        $record = $this->create('checklist_registros', ['plantilla_id' => $template['id'], 'uuid_cliente' => (string) Str::uuid(), 'fecha' => '2026-09-22 12:00:00']);
        $url = '/api/v1/checklist_registros/'.$record['id'];
        $this->postJson($url.'/respuestas', ['respuestas' => [['pregunta_id' => $question['id'], 'valor_texto' => 'Texto inicial']]])->assertOk();
        $this->postJson($url.'/respuestas', ['respuestas' => [['pregunta_id' => $question['id'], 'valor_texto' => null]]])->assertOk();
        $audit = DB::table('auditoria_eventos')->where('accion', 'respuestas')->orderByDesc('id')->first();
        $this->assertSame('Texto inicial', json_decode($audit->datos_antes, true)['respuestas'][0]['valor_texto']);
        $this->assertNull(json_decode($audit->datos_despues, true)['respuestas'][0]['valor_texto']);
        $this->postJson($url.'/enviar')->assertOk();
    }

    public function test_inventory_shortage_rolls_back_all_details_not_only_the_failing_one(): void
    {
        $this->loginAsTenant($this->tenant);
        $site = $this->create('sedes', ['pais' => 'PE', 'codigo' => 'S', 'nombre' => 'Sede']);
        $warehouse = $this->create('almacenes', ['sede_id' => $site['id'], 'nombre' => 'Almacén']);
        $zone = $this->create('zonas', ['sede_id' => $site['id'], 'nombre' => 'Zona 1']);
        $worker = $this->create('trabajadores', ['zona_id' => $zone['id'], 'tipo_documento' => 'DNI', 'numero_documento' => '12345678', 'nombres' => 'Ana', 'apellidos' => 'Pérez', 'puesto' => 'Operadora', 'fecha_ingreso' => '2026-01-15']);
        $item = $this->create('epp_catalogo', ['codigo' => 'CAS', 'nombre' => 'Casco']);
        $delivery = $this->create('epp_entregas', ['uuid_cliente' => (string) Str::uuid(), 'trabajador_id' => $worker['id'], 'fecha' => '2026-09-22 12:00:00']);
        foreach ([2, 4] as $quantity) {
            $this->create('epp_entrega_detalles', ['entrega_id' => $delivery['id'], 'epp_id' => $item['id'], 'almacen_id' => $warehouse['id'], 'cantidad' => $quantity]);
        }
        $this->postJson('/api/v1/inventario/movimientos', ['epp_id' => $item['id'], 'almacen_id' => $warehouse['id'], 'cantidad' => 5, 'tipo' => 'entrada', 'costo_unitario' => '10.00', 'clave_operacion' => 'entrada'])->assertCreated();
        $this->postJson('/api/v1/epp_entregas/'.$delivery['id'].'/confirmar')->assertUnprocessable();
        $this->assertSame(1, DB::table('epp_movimientos')->count());
        $this->assertEquals(5, DB::table('epp_movimientos')->sum('cantidad'));
        $this->assertDatabaseHas('epp_entregas', ['id' => $delivery['id'], 'estado' => 'borrador']);
    }
}
