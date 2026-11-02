<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Support\HseDatabase;
use Tests\TestCase;
use ZipArchive;

class AdministrationTest extends TestCase
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

    private function role(string $name = 'Consulta'): int
    {
        return $this->postJson('/api/v1/administracion/roles', ['nombre' => $name, 'permisos' => []])->assertCreated()->json('data.id');
    }

    private function user(int $role): int
    {
        return $this->postJson('/api/v1/administracion/usuarios', ['nombre' => 'Ana Torres', 'email' => 'ana@example.test',
            'password' => 'password-user-123', 'rol_id' => $role, 'plataforma_id' => 1])->assertCreated()->json('data.id');
    }

    public function test_six_sections_include_readable_relationships_and_respect_company_scope(): void
    {
        $other = $this->tenant('beta');
        $this->getJson('/api/v1/administracion/usuarios')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.nombre', 'alpha')->assertJsonMissingPath('data.0.password_hash');
        $this->getJson('/api/v1/administracion/modulos')->assertOk()->assertJsonCount(10, 'data');
        $this->getJson('/api/v1/administracion/roles')->assertOk()->assertJsonCount(1, 'data')->assertJsonCount(51, 'data.0.permisos');
        $this->getJson('/api/v1/administracion/permisos')->assertOk()->assertJsonPath('total', 51)->assertJsonStructure(['data' => [['modulo', 'descripcion', 'codigo']]]);
        $this->getJson('/api/v1/administracion/permisos-usuarios')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.usuario', 'alpha')->assertJsonPath('data.0.rol', 'Admin')->assertJsonCount(51, 'data.0.permisos');
        $this->getJson('/api/v1/administracion/permisos-roles?q=Documentos')->assertOk()->assertJsonPath('total', 6)
            ->assertJsonStructure(['data' => [['rol', 'modulo', 'descripcion']]]);
        $this->getJson('/api/v1/administracion/opciones')->assertOk()->assertJsonCount(1, 'data.usuarios')->assertJsonCount(1, 'data.roles');
        $this->patchJson('/api/v1/administracion/usuarios/'.$other['member'], ['nombre' => 'Cruce'])->assertNotFound();
        $this->deleteJson('/api/v1/administracion/roles/'.$other['role'])->assertNotFound();
        $this->deleteJson('/api/v1/administracion/permisos-usuarios/'.$other['access'])->assertNotFound();
    }

    public function test_user_create_update_and_deactivate_protect_credentials_and_revoke_sessions(): void
    {
        $role = $this->role();
        $member = $this->user($role);
        $user = DB::table('miembros_empresa')->where('id', $member)->value('usuario_id');
        $this->assertTrue(Hash::check('password-user-123', DB::table('usuarios')->where('id', $user)->value('password_hash')));
        $this->patchJson('/api/v1/administracion/usuarios/'.$member, ['nombre' => 'Ana Pérez', 'email' => 'ana.perez@example.test', 'password' => 'new-password-123', 'activo' => true])
            ->assertOk()->assertJsonPath('data.nombre', 'Ana Pérez')->assertJsonMissingPath('data.password_hash');
        $this->assertTrue(Hash::check('new-password-123', DB::table('usuarios')->where('id', $user)->value('password_hash')));
        DB::table('usuarios')->where('id', $user)->update(['email_verified_at' => now()]);
        $this->postJson('/api/v1/auth/login', ['email' => 'ana.perez@example.test', 'password' => 'new-password-123', 'empresa_codigo' => 'alpha', 'plataforma' => 'web'])->assertOk();
        $this->deleteJson('/api/v1/administracion/usuarios/'.$member)->assertOk();
        $this->assertDatabaseHas('miembros_empresa', ['id' => $member, 'activo' => false]);
        $access = DB::table('accesos_aplicacion')->where('miembro_id', $member)->value('id');
        $this->assertSame(0, DB::table('sesiones')->where('acceso_id', $access)->whereNull('revocado_at')->count());
        $audit = DB::table('auditoria_eventos')->get()->toJson();
        $this->assertStringNotContainsString('password_hash', $audit);
        $this->assertStringNotContainsString('new-password-123', $audit);
        $this->patchJson('/api/v1/administracion/usuarios/'.$member, ['activo' => true])->assertOk();
    }

    public function test_role_permissions_and_user_assignments_support_full_lifecycle(): void
    {
        $role = $this->role();
        $read = DB::table('permisos')->where('codigo', 'documentos.ver')->value('id');
        $edit = DB::table('permisos')->where('codigo', 'documentos.editar')->value('id');
        $grant = $this->postJson('/api/v1/administracion/permisos-roles', ['rol_id' => $role, 'permiso_id' => $read])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/administracion/permisos-roles', ['rol_id' => $role, 'permiso_id' => $read])->assertUnprocessable();
        $this->patchJson('/api/v1/administracion/permisos-roles/'.$grant, ['rol_id' => $role, 'permiso_id' => $edit])->assertOk();
        $member = $this->user($role);
        $access = DB::table('accesos_aplicacion')->where('miembro_id', $member)->value('id');
        $this->postJson('/api/v1/administracion/permisos-usuarios', ['miembro_id' => $member, 'rol_id' => $role, 'plataforma_id' => 1, 'activo' => true])->assertUnprocessable();
        $this->patchJson('/api/v1/administracion/permisos-usuarios/'.$access, ['rol_id' => $role, 'activo' => false])->assertOk();
        $this->patchJson('/api/v1/administracion/permisos-usuarios/'.$access, ['rol_id' => $role, 'activo' => true])->assertOk();
        $this->deleteJson('/api/v1/administracion/permisos-roles/'.$grant)->assertOk();
        $this->assertDatabaseMissing('rol_permisos', ['id' => $grant]);
        $this->patchJson('/api/v1/administracion/roles/'.$role, ['nombre' => 'Operador', 'descripcion' => 'Consulta documental', 'activo' => true, 'permisos' => [$read]])->assertOk();
        $this->deleteJson('/api/v1/administracion/roles/'.$role)->assertOk();
        $this->deleteJson('/api/v1/administracion/permisos-usuarios/'.$access)->assertOk();
        $this->assertDatabaseHas('roles', ['id' => $role, 'activo' => false]);
        $this->assertDatabaseHas('accesos_aplicacion', ['id' => $access, 'activo' => false]);
    }

    public function test_self_lockout_and_privilege_escalation_are_rejected(): void
    {
        $this->deleteJson('/api/v1/administracion/usuarios/'.$this->tenant['member'])->assertUnprocessable();
        $this->deleteJson('/api/v1/administracion/roles/'.$this->tenant['role'])->assertUnprocessable();
        $this->deleteJson('/api/v1/administracion/permisos-usuarios/'.$this->tenant['access'])->assertUnprocessable();
        $grant = DB::table('rol_permisos')->where('rol_id', $this->tenant['role'])->first();
        $this->deleteJson('/api/v1/administracion/permisos-roles/'.$grant->id)->assertUnprocessable();
        $role = $this->role();
        DB::table('rol_permisos')->where('id', $grant->id)->delete();
        $this->postJson('/api/v1/administracion/permisos-roles', ['rol_id' => $role, 'permiso_id' => $grant->permiso_id])->assertForbidden();
        $this->postJson('/api/v1/administracion/modulos', ['nombre' => 'Cambio'])->assertStatus(405);
        $this->postJson('/api/v1/administracion/permisos', ['descripcion' => 'Cambio'])->assertStatus(405);
    }

    public function test_shared_identity_cannot_be_changed_by_another_company_administrator(): void
    {
        $other = $this->tenant('beta');
        $member = DB::table('miembros_empresa')->insertGetId(['empresa_id' => $this->tenant['company'], 'usuario_id' => $other['user']]);
        $this->patchJson('/api/v1/administracion/usuarios/'.$member, ['email' => 'changed@example.test'])->assertUnprocessable();
        $this->deleteJson('/api/v1/administracion/usuarios/'.$member)->assertOk();
        $this->assertDatabaseHas('miembros_empresa', ['id' => $other['member'], 'activo' => true]);
        $this->assertDatabaseHas('usuarios', ['id' => $other['user'], 'email' => 'beta@example.test']);
    }

    public function test_excel_exports_all_filtered_results_with_names_and_safe_literal_cells(): void
    {
        $this->tenant('beta');
        DB::table('usuarios')->where('id', $this->tenant['user'])->update(['nombre' => '=HYPERLINK("x") & Ana']);
        foreach (['usuarios', 'modulos', 'roles', 'permisos', 'permisos-usuarios', 'permisos-roles'] as $section) {
            $response = $this->get('/api/v1/administracion/'.$section.'/excel')->assertOk()
                ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            $path = $response->baseResponse->getFile()->getPathname();
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($path));
            $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
            $sheet = simplexml_load_string($xml);
            $sheet->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $this->assertCount(0, $sheet->xpath('//s:f'));
            $this->assertStringNotContainsString('beta@example.test', $xml);
            $this->assertStringNotContainsString('password', $xml);
            if ($section === 'permisos') {
                $this->assertCount(52, $sheet->xpath('//s:row'));
            }
            if ($section === 'usuarios') {
                $this->assertStringContainsString('HYPERLINK', $xml);
                $this->assertStringContainsString('Admin', $xml);
            }
            $zip->close();
            unlink($path);
        }
        $response = $this->get('/api/v1/administracion/permisos/excel?q=Documentos')->assertOk();
        $path = $response->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        $zip->open($path);
        $sheet = simplexml_load_string($zip->getFromName('xl/worksheets/sheet1.xml'));
        $sheet->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $this->assertCount(7, $sheet->xpath('//s:row'));
        $zip->close();
        unlink($path);
    }

    public function test_read_only_administrator_cannot_mutate_and_cannot_export_without_read_permission(): void
    {
        $manage = DB::table('permisos')->where('codigo', 'administracion.crear')->value('id');
        DB::table('rol_permisos')->where('rol_id', $this->tenant['role'])->where('permiso_id', $manage)->delete();
        $this->getJson('/api/v1/administracion/usuarios')->assertOk();
        $this->postJson('/api/v1/administracion/roles', ['nombre' => 'Nuevo', 'permisos' => []])->assertForbidden();
        $view = DB::table('permisos')->where('codigo', 'administracion.ver')->value('id');
        DB::table('rol_permisos')->where('rol_id', $this->tenant['role'])->where('permiso_id', $view)->delete();
        $this->get('/api/v1/administracion/usuarios/excel')->assertForbidden();
    }
}
