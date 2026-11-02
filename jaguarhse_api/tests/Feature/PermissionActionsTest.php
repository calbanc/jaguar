<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\Support\HseDatabase;
use Tests\TestCase;

class PermissionActionsTest extends TestCase
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

    private function grant(string $code, ?int $role = null): void
    {
        DB::table('rol_permisos')->insertOrIgnore(['empresa_id' => $this->tenant['company'],
            'rol_id' => $role ?? $this->tenant['role'], 'permiso_id' => DB::table('permisos')->where('codigo', $code)->value('id')]);
    }

    public function test_consult_edit_delete_and_download_are_independent(): void
    {
        $role = $this->postJson('/api/v1/administracion/roles', ['nombre' => 'Consulta', 'permisos' => []])->assertCreated()->json('data.id');
        DB::table('rol_permisos')->where('rol_id', $this->tenant['role'])->delete();
        $this->grant('administracion.ver');
        $this->getJson('/api/v1/administracion/roles')->assertOk();
        $this->get('/api/v1/administracion/roles/excel')->assertForbidden();
        $this->postJson('/api/v1/administracion/roles', ['nombre' => 'Nuevo', 'permisos' => []])->assertForbidden();
        $this->patchJson('/api/v1/administracion/roles/'.$role, ['nombre' => 'Cambio', 'permisos' => []])->assertForbidden();
        $this->grant('administracion.editar');
        $this->patchJson('/api/v1/administracion/roles/'.$role, ['nombre' => 'Cambio', 'permisos' => []])->assertOk();
        $this->patchJson('/api/v1/administracion/roles/'.$role, ['nombre' => 'Cambio', 'permisos' => [], 'activo' => false])->assertForbidden();
        $this->deleteJson('/api/v1/administracion/roles/'.$role)->assertForbidden();
        $this->grant('administracion.eliminar');
        $this->deleteJson('/api/v1/administracion/roles/'.$role)->assertOk();
        $this->grant('administracion.descargar');
        $response = $this->get('/api/v1/administracion/roles/excel')->assertOk();
        unlink($response->baseResponse->getFile()->getPathname());
    }

    public function test_resource_deactivation_and_exports_require_their_specific_permissions(): void
    {
        $site = DB::table('sedes')->insertGetId(['pais' => 'PE', 'empresa_id' => $this->tenant['company'], 'nombre' => 'Lima', 'codigo' => 'LIM']);
        DB::table('rol_permisos')->where('rol_id', $this->tenant['role'])->delete();
        $this->grant('organizacion.ver');
        $this->grant('organizacion.editar');
        $this->getJson('/api/v1/sedes')->assertOk();
        $this->deleteJson('/api/v1/sedes/'.$site)->assertForbidden();
        $this->patchJson('/api/v1/sedes/'.$site, ['activo' => false])->assertForbidden();
        $this->get('/api/v1/exportar/sedes')->assertForbidden();
        $this->grant('organizacion.descargar');
        $response = $this->get('/api/v1/exportar/sedes?q=Lima')->assertOk();
        $zip = new \ZipArchive;
        $path = $response->baseResponse->getFile()->getPathname();
        $zip->open($path);
        $this->assertStringContainsString('Lima', $zip->getFromName('xl/worksheets/sheet1.xml'));
        $zip->close();
        unlink($path);
        $this->grant('organizacion.eliminar');
        $this->deleteJson('/api/v1/sedes/'.$site)->assertOk();
    }

    public function test_migration_consolidates_old_actions_without_granting_confidential_documents(): void
    {
        $read = DB::table('roles')->insertGetId(['empresa_id' => $this->tenant['company'], 'nombre' => 'Lector']);
        $manager = DB::table('roles')->insertGetId(['empresa_id' => $this->tenant['company'], 'nombre' => 'Gestor']);
        $this->grant('documentos.ver', $read);
        $legacy = DB::table('permisos')->insertGetId(['modulo_id' => DB::table('modulos')->where('codigo', 'administracion')->value('id'),
            'codigo' => 'administracion.gestionar', 'descripcion' => 'Gestionar']);
        DB::table('rol_permisos')->insert(['empresa_id' => $this->tenant['company'], 'rol_id' => $manager, 'permiso_id' => $legacy]);
        $migration = require database_path('migrations/2026_10_04_030326_simplify_hse_permission_actions.php');
        $migration->up();
        $this->assertSame(51, DB::table('permisos')->count());
        $codes = fn ($role) => DB::table('rol_permisos as rp')->join('permisos as p', 'p.id', '=', 'rp.permiso_id')->where('rol_id', $role)->pluck('p.codigo')->all();
        $this->assertEqualsCanonicalizing(['documentos.ver', 'documentos.descargar'], $codes($read));
        $this->assertEqualsCanonicalizing(['administracion.crear', 'administracion.editar', 'administracion.eliminar'], $codes($manager));
        $this->assertDatabaseMissing('permisos', ['codigo' => 'administracion.gestionar']);
    }
}
