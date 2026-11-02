<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\Support\HseDatabase;
use Tests\TestCase;
use ZipArchive;

class TableFiltersTest extends TestCase
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

    private function sheet(string $url): string
    {
        $response = $this->get($url)->assertOk();
        $file = $response->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        $zip->open($file);
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        unlink($file);

        return $xml;
    }

    public function test_resource_filters_combine_before_pagination_and_match_excel(): void
    {
        $other = $this->tenant('beta');
        foreach (range(1, 25) as $number) {
            DB::table('sedes')->insert(['pais' => 'PE', 'empresa_id' => $this->tenant['company'], 'codigo' => 'S'.$number, 'nombre' => 'Sede '.$number, 'activo' => $number !== 25]);
        }
        DB::table('sedes')->insert(['pais' => 'PE', 'empresa_id' => $other['company'], 'codigo' => 'OTHER', 'nombre' => 'Sede 25', 'activo' => false]);
        $query = '?columns[nombre]=Sede%2025&columns[activo]=0&per_page=1';
        $this->getJson('/api/v1/sedes'.$query)->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.codigo', 'S25');
        $xml = $this->sheet('/api/v1/exportar/sedes'.$query);
        $this->assertStringContainsString('S25', $xml);
        $this->assertStringNotContainsString('OTHER', $xml);
        $this->assertStringNotContainsString('S24', $xml);
        $this->getJson('/api/v1/sedes?columns[nombre]=NoExiste')->assertOk()->assertJsonPath('total', 0)->assertJsonFragment(['columns' => ['id', 'codigo', 'nombre', 'direccion', 'activo', 'created_at', 'updated_at', 'pais']]);
    }

    public function test_literal_search_and_invalid_filters_are_handled_safely(): void
    {
        DB::table('sedes')->insert(['pais' => 'PE', 'empresa_id' => $this->tenant['company'], 'codigo' => 'PCT', 'nombre' => '100%_cumplido']);
        DB::table('sedes')->insert(['pais' => 'PE', 'empresa_id' => $this->tenant['company'], 'codigo' => 'OTHER', 'nombre' => '100X cumplido']);
        $this->getJson('/api/v1/sedes?columns[nombre]=%25_')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.codigo', 'PCT');
        $this->getJson('/api/v1/sedes?q=%25_')->assertOk()->assertJsonPath('total', 1);
        foreach (['columns[empresa_id]=2', 'columns[password_hash]=x', 'columns[nombre][nested]=x', 'columns[activo]=false'] as $query) {
            $this->getJson('/api/v1/sedes?'.$query)->assertUnprocessable();
        }
    }

    public function test_administration_filters_relations_and_permissions_and_exports_same_rows(): void
    {
        $this->getJson('/api/v1/administracion/usuarios?columns[nombre]=alpha&columns[roles]=Admin')->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/api/v1/administracion/usuarios?columns[roles]=NoExiste')->assertOk()->assertJsonPath('total', 0);
        $this->getJson('/api/v1/administracion/permisos-usuarios?columns[rol]=Admin&columns[plataforma]=web&columns[permisos_detalle]=ver')
            ->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/api/v1/administracion/roles?columns[permisos_detalle]=NoExiste')->assertOk()->assertJsonPath('total', 0);
        $url = '/api/v1/administracion/permisos-roles';
        $query = '?columns[rol]=Admin&columns[modulo]=Documentos&columns[descripcion]=editar';
        $this->getJson($url.$query)->assertOk()->assertJsonPath('total', 1);
        $xml = $this->sheet($url.'/excel'.$query);
        $this->assertStringContainsString('Documentos', $xml);
        $this->assertStringNotContainsString('Hallazgos', $xml);
        $this->assertStringNotContainsString('eliminar', $xml);
        $this->getJson('/api/v1/administracion/permisos?columns[modulo]=Documentos&columns[editar]=1')->assertOk()->assertJsonPath('total', 6);
        $this->getJson('/api/v1/administracion/permisos?columns[editar]=0')->assertOk()->assertJsonPath('total', 0);
    }

    public function test_disabled_modules_and_unknown_admin_columns(): void
    {
        $module = DB::table('modulos')->where('codigo', 'documentos')->value('id');
        DB::table('empresa_modulos')->where('empresa_id', $this->tenant['company'])->where('modulo_id', $module)->delete();
        $this->getJson('/api/v1/administracion/modulos?columns[activo]=0')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.codigo', 'documentos');
        $this->getJson('/api/v1/administracion/usuarios?columns[password_hash]=x')->assertUnprocessable();
    }

    public function test_report_filters_are_applied_to_list_and_export(): void
    {
        $module = DB::table('modulos')->where('codigo', 'documentos')->value('id');
        foreach (['Tema Uno', 'Tema Dos'] as $index => $name) {
            DB::table('temas_hse')->insert(['empresa_id' => $this->tenant['company'], 'modulo_id' => $module, 'codigo' => 'T'.$index, 'nombre' => $name]);
        }
        $this->getJson('/api/v1/reportes/temas?columns[nombre]=Tema%20Dos')->assertOk()->assertJsonPath('total', 1);
        $xml = $this->sheet('/api/v1/exportar/reportes-temas?columns[nombre]=Tema%20Dos');
        $this->assertStringContainsString('Tema Dos', $xml);
        $this->assertStringNotContainsString('Tema Uno', $xml);
    }
}
