<?php

namespace Tests\Feature;

use App\Services\HsePermissions;
use Illuminate\Support\Facades\DB;
use Tests\Support\HseDatabase;
use Tests\TestCase;

class HsePermissionsTest extends TestCase
{
    use HseDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installHseFixture();
    }

    public function test_sync_normalizes_definitions_preserves_ids_and_migrates_legacy_permissions(): void
    {
        $tenant = $this->tenant('alpha');
        $module = DB::table('modulos')->where('codigo', 'documentos')->value('id');
        $id = DB::table('permisos')->where('codigo', 'documentos.ver')->value('id');
        DB::table('permisos')->where('id', $id)->update(['descripcion' => 'Nombre anterior']);
        foreach (['aprobar', 'anular', 'exportar'] as $action) {
            DB::table('permisos')->insert(['modulo_id' => $module, 'codigo' => 'documentos.'.$action, 'descripcion' => $action]);
        }
        app(HsePermissions::class)->synchronize();
        app(HsePermissions::class)->synchronize();
        $this->assertSame(51, DB::table('permisos')->count());
        $this->assertDatabaseHas('permisos', ['id' => $id, 'codigo' => 'documentos.ver', 'descripcion' => 'Consultar · Documentos']);
        $this->assertDatabaseHas('rol_permisos', ['rol_id' => $tenant['role'], 'permiso_id' => $id]);
        $legacy = DB::table('permisos')->insertGetId(['modulo_id' => $module, 'codigo' => 'documentos.exportar', 'descripcion' => 'Asignado']);
        DB::table('rol_permisos')->insert(['empresa_id' => $tenant['company'], 'rol_id' => $tenant['role'], 'permiso_id' => $legacy]);
        app(HsePermissions::class)->synchronize();
        $download = DB::table('permisos')->where('codigo', 'documentos.descargar')->value('id');
        $this->assertDatabaseHas('rol_permisos', ['rol_id' => $tenant['role'], 'permiso_id' => $download]);
        $this->assertDatabaseMissing('permisos', ['id' => $legacy]);
    }

    public function test_sync_updates_administrators_without_granting_custom_roles_or_disabled_company_modules(): void
    {
        $alpha = $this->tenant('alpha');
        $beta = $this->tenant('beta');
        foreach ([$alpha, $beta] as $tenant) {
            DB::table('roles')->where('id', $tenant['role'])->update(['nombre' => 'Administrador']);
            DB::table('rol_permisos')->where('rol_id', $tenant['role'])->delete();
        }
        $custom = DB::table('roles')->insertGetId(['empresa_id' => $alpha['company'], 'nombre' => 'Consulta', 'descripcion' => 'Solo lectura']);
        $read = DB::table('permisos')->where('codigo', 'documentos.ver')->value('id');
        DB::table('rol_permisos')->insert(['empresa_id' => $alpha['company'], 'rol_id' => $custom, 'permiso_id' => $read]);
        $training = DB::table('modulos')->where('codigo', 'capacitaciones')->value('id');
        DB::table('empresa_modulos')->where('empresa_id', $beta['company'])->where('modulo_id', $training)->update(['activo' => false]);
        $this->artisan('hse:sincronizar-permisos')->assertExitCode(0);
        $this->artisan('hse:sincronizar-permisos')->assertExitCode(0);
        $this->assertSame(51, DB::table('rol_permisos')->where('rol_id', $alpha['role'])->count());
        $this->assertSame(46, DB::table('rol_permisos')->where('rol_id', $beta['role'])->count());
        $this->assertSame(1, DB::table('rol_permisos')->where('rol_id', $custom)->count());
        $this->assertDatabaseHas('roles', ['id' => $custom, 'descripcion' => 'Solo lectura']);
        $this->assertDatabaseHas('roles', ['id' => $alpha['role'], 'descripcion' => HsePermissions::ADMIN_DESCRIPTION]);
        $this->assertSame(0, DB::table('rol_permisos')->where('rol_id', $alpha['role'])->where('empresa_id', '!=', $alpha['company'])->count());
    }
}
