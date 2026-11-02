<?php

namespace Tests\Feature;

use App\Mail\VerifyHseEmail;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Support\HseDatabase;
use Tests\TestCase;

class HseModulesTest extends TestCase
{
    use HseDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installHseFixture();
    }

    public function test_catalog_and_new_companies_use_the_ten_modules(): void
    {
        Mail::fake();
        $this->travelTo(now()->startOfDay()->addHours(12));
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);
        $this->artisan('hse:bootstrap', ['codigo' => 'new', 'razon_social' => 'Nueva empresa',
            'email' => 'new@example.test', '--identificacion' => '20123456789'])
            ->expectsQuestion('Contraseña del nuevo administrador (mínimo 12 caracteres)', 'password-test-123')
            ->assertExitCode(0);
        $this->assertSame(10, DB::table('empresa_modulos')->count());
        Mail::assertSent(VerifyHseEmail::class, function ($mail) {
            $this->post($mail->verificationUrl)->assertOk();

            return $mail->hasTo('new@example.test');
        });
        $this->postJson('/api/v1/auth/login', ['email' => 'new@example.test', 'password' => 'password-test-123',
            'empresa_codigo' => 'new', 'plataforma' => 'web'])->assertOk();
        $tenant = $this->tenant('alpha');
        $this->loginAsTenant($tenant);
        $response = $this->getJson('/api/v1/catalogos')->assertOk()->assertJsonCount(10, 'data.modulos');
        $this->assertEqualsCanonicalizing(array_keys(config('hse.modules')), array_column($response->json('data.modulos'), 'codigo'));
        foreach (['organizacion.ver', 'gestion_sst.ver', 'inspecciones.ver', 'capacitaciones.ver'] as $code) {
            $this->assertDatabaseHas('permisos', ['codigo' => $code]);
        }
    }

    public function test_migration_preserves_themes_and_transfers_existing_access_without_duplicates(): void
    {
        $tenant = $this->tenant('alpha');
        foreach (['organizacion', 'gestion_sst', 'inspecciones'] as $code) {
            $id = DB::table('modulos')->where('codigo', $code)->value('id');
            $permissions = DB::table('permisos')->where('modulo_id', $id)->pluck('id');
            DB::table('rol_permisos')->whereIn('permiso_id', $permissions)->delete();
            DB::table('permisos')->where('modulo_id', $id)->delete();
            DB::table('empresa_modulos')->where('modulo_id', $id)->delete();
            DB::table('modulos')->where('id', $id)->delete();
        }
        foreach (['checklists', 'liderazgo', 'planificacion'] as $code) {
            $module = DB::table('modulos')->insertGetId(['codigo' => $code, 'nombre' => $code]);
            DB::table('empresa_modulos')->insert(['empresa_id' => $tenant['company'], 'modulo_id' => $module]);
            $permission = DB::table('permisos')->insertGetId(['modulo_id' => $module, 'codigo' => $code.'.ver', 'descripcion' => 'ver']);
            DB::table('rol_permisos')->insert(['empresa_id' => $tenant['company'], 'rol_id' => $tenant['role'], 'permiso_id' => $permission]);
            DB::table('temas_hse')->insert(['empresa_id' => $tenant['company'], 'modulo_id' => $module, 'codigo' => $code, 'nombre' => $code]);
        }
        $migration = require database_path('migrations/2026_10_04_021648_consolidate_hse_modules.php');
        $migration->up();
        $migration->up();
        $this->assertSame(9, DB::table('modulos')->count());
        $this->assertSame(9, DB::table('empresa_modulos')->count());
        $this->assertSame(3, DB::table('temas_hse')->count());
        $sst = DB::table('modulos')->where('codigo', 'gestion_sst')->value('id');
        $this->assertDatabaseHas('temas_hse', ['codigo' => 'liderazgo', 'modulo_id' => $sst]);
        $this->assertDatabaseHas('temas_hse', ['codigo' => 'planificacion', 'modulo_id' => $sst]);
        $this->assertDatabaseMissing('permisos', ['codigo' => 'checklists.ver']);
        $this->loginAsTenant($tenant);
        foreach (['sedes', 'temas_hse', 'checklist_plantillas', 'reportes/temas'] as $resource) {
            $this->getJson('/api/v1/'.$resource)->assertOk();
        }
        DB::table('empresa_modulos')->where('modulo_id', $sst)->update(['activo' => false]);
        $this->getJson('/api/v1/temas_hse')->assertForbidden();
        $this->getJson('/api/v1/reportes/temas')->assertForbidden();
        $this->getJson('/api/v1/documento_versiones')->assertOk();
    }

    public function test_training_module_preserves_disabled_companies_and_role_permissions(): void
    {
        $tenant = $this->tenant('alpha');
        $module = DB::table('modulos')->where('codigo', 'capacitaciones')->value('id');
        $permissions = DB::table('permisos')->where('modulo_id', $module)->pluck('id');
        DB::table('rol_permisos')->whereIn('permiso_id', $permissions)->delete();
        DB::table('permisos')->where('modulo_id', $module)->delete();
        DB::table('empresa_modulos')->where('modulo_id', $module)->delete();
        DB::table('modulos')->where('id', $module)->delete();
        $source = DB::table('modulos')->where('codigo', 'gestion_sst')->value('id');
        DB::table('empresa_modulos')->where('modulo_id', $source)->update(['activo' => false]);
        $create = DB::table('permisos')->where('codigo', 'gestion_sst.crear')->value('id');
        DB::table('rol_permisos')->where('permiso_id', $create)->delete();
        $migration = require database_path('migrations/2026_10_04_021951_add_capacitaciones_module.php');
        $migration->up();
        $migration->up();
        $this->assertSame(10, DB::table('modulos')->count());
        $this->assertSame(10, DB::table('empresa_modulos')->count());
        $module = DB::table('modulos')->where('codigo', 'capacitaciones')->value('id');
        $this->assertDatabaseHas('empresa_modulos', ['empresa_id' => $tenant['company'], 'modulo_id' => $module, 'activo' => false]);
        $permission = DB::table('permisos')->where('codigo', 'capacitaciones.ver')->value('id');
        $this->assertDatabaseHas('rol_permisos', ['rol_id' => $tenant['role'], 'permiso_id' => $permission]);
        $create = DB::table('permisos')->where('codigo', 'capacitaciones.crear')->value('id');
        $this->assertDatabaseMissing('rol_permisos', ['rol_id' => $tenant['role'], 'permiso_id' => $create]);
    }
}
