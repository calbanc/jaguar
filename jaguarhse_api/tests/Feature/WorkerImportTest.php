<?php

namespace Tests\Feature;

use App\Services\ExcelExport;
use App\Services\WorkerSpreadsheet;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\Support\HseDatabase;
use Tests\TestCase;
use ZipArchive;

class WorkerImportTest extends TestCase
{
    use HseDatabase;

    private array $tenant;

    private array $paths = [];

    private int $zone;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installHseFixture();
        $this->tenant = $this->tenant('alpha');
        $this->loginAsTenant($this->tenant);
        $site = DB::table('sedes')->insertGetId(['empresa_id' => $this->tenant['company'], 'codigo' => 'PIU', 'nombre' => 'Piura', 'pais' => 'PE']);
        $this->zone = DB::table('zonas')->insertGetId(['empresa_id' => $this->tenant['company'], 'sede_id' => $site, 'nombre' => 'Zona 1']);
    }

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) {
            @unlink($path);
        }
        parent::tearDown();
    }

    private function file(array $rows, ?callable $edit = null): UploadedFile
    {
        $path = app(ExcelExport::class)->create('Trabajadores', WorkerSpreadsheet::HEADERS, $rows, [1]);
        $this->paths[] = $path;
        if ($edit) {
            $zip = new ZipArchive;
            $zip->open($path);
            $edit($zip);
            $zip->close();
        }

        return new UploadedFile($path, 'trabajadores.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function row(string $dni = '01234567'): array
    {
        return [$dni, 'Ana', 'Torres', 'Operadora', '2026-01-15'];
    }

    public function test_preview_does_not_save_and_confirmation_creates_workers_with_audit_and_leading_zero(): void
    {
        $file = $this->file([$this->row()]);
        $this->postJson('/api/v1/trabajadores/importar', ['zona_id' => $this->zone, 'archivo' => $file])->assertOk()->assertJsonPath('validos', 1)->assertJsonPath('filas.0.datos.numero_documento', '01234567');
        $this->assertSame(0, DB::table('trabajadores')->count());
        $this->postJson('/api/v1/trabajadores/importar', ['zona_id' => $this->zone, 'archivo' => $file, 'confirmar' => true])->assertCreated()->assertJsonPath('importados', 1);
        $this->assertDatabaseHas('trabajadores', ['empresa_id' => $this->tenant['company'], 'numero_documento' => '01234567', 'puesto' => 'Operadora', 'fecha_ingreso' => '2026-01-15']);
        $this->assertDatabaseHas('auditoria_eventos', ['entidad' => 'trabajadores', 'accion' => 'importar_excel']);
        $this->assertDatabaseHas('trabajador_asignaciones', ['empresa_id' => $this->tenant['company'], 'zona_id' => $this->zone, 'fecha_inicio' => '2026-01-15', 'fecha_fin' => null]);
        $this->postJson('/api/v1/trabajadores/importar', ['zona_id' => $this->zone, 'archivo' => $file, 'confirmar' => true])->assertOk()->assertJsonPath('invalidos', 1);
        $this->assertSame(1, DB::table('trabajadores')->count());
    }

    public function test_dni_position_date_and_duplicates_are_validated_and_import_is_atomic(): void
    {
        $rows = [$this->row(), ['123', 'Luis', 'Paz', '', '2026-02-30'], $this->row('87654321'), $this->row('87654321')];
        $this->postJson('/api/v1/trabajadores/importar', ['zona_id' => $this->zone, 'archivo' => $this->file($rows), 'confirmar' => true])
            ->assertOk()->assertJsonPath('invalidos', 3)->assertJsonPath('validos', 1)->assertJsonPath('filas.1.fila', 3);
        $this->assertSame(0, DB::table('trabajadores')->count());
    }

    public function test_excel_serial_dates_shared_strings_and_1904_workbooks_are_supported(): void
    {
        foreach ([false, true] as $date1904) {
            $file = $this->file([$this->row()], function (ZipArchive $zip) use ($date1904) {
                $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
                $sheet = preg_replace('/<c r="E2".*?<\/c>/', '<c r="E2"><v>'.($date1904 ? '44575' : '46037').'</v></c>', $sheet);
                $sheet = preg_replace('/<c r="B2".*?<\/c>/', '<c r="B2" t="s"><v>0</v></c>', $sheet);
                $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
                $zip->addFromString('xl/sharedStrings.xml', '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><t>Ana</t></si></sst>');
                if ($date1904) {
                    $zip->addFromString('xl/workbook.xml', str_replace('<sheets>', '<workbookPr date1904="1"/><sheets>', $zip->getFromName('xl/workbook.xml')));
                }
            });
            $this->postJson('/api/v1/trabajadores/importar', ['zona_id' => $this->zone, 'archivo' => $file])->assertOk()->assertJsonPath('validos', 1)->assertJsonPath('filas.0.datos.fecha_ingreso', '2026-01-15');
        }
    }

    public function test_wrong_headers_empty_files_and_formulas_are_rejected(): void
    {
        $this->postJson('/api/v1/trabajadores/importar', ['zona_id' => $this->zone, 'archivo' => $this->file([])])->assertUnprocessable();
        $file = $this->file([$this->row()], fn (ZipArchive $zip) => $zip->addFromString('xl/worksheets/sheet1.xml', str_replace('Fecha de ingreso', 'Fecha incorrecta', $zip->getFromName('xl/worksheets/sheet1.xml'))));
        $this->postJson('/api/v1/trabajadores/importar', ['zona_id' => $this->zone, 'archivo' => $file])->assertUnprocessable();
        $file = $this->file([$this->row()], fn (ZipArchive $zip) => $zip->addFromString('xl/worksheets/sheet1.xml', preg_replace('/<c r="A2".*?<\/c>/', '<c r="A2"><f>12345678</f><v>12345678</v></c>', $zip->getFromName('xl/worksheets/sheet1.xml'))));
        $this->postJson('/api/v1/trabajadores/importar', ['zona_id' => $this->zone, 'archivo' => $file, 'confirmar' => true])->assertOk()->assertJsonPath('invalidos', 1);
        $this->assertSame(0, DB::table('trabajadores')->count());
    }

    public function test_unsafe_xml_and_rows_beyond_the_limit_are_rejected(): void
    {
        $file = $this->file([$this->row()], fn (ZipArchive $zip) => $zip->addFromString('xl/sharedStrings.xml', '<!DOCTYPE sst [<!ENTITY example "value">]><sst/>'));
        $this->postJson('/api/v1/trabajadores/importar', ['zona_id' => $this->zone, 'archivo' => $file])->assertUnprocessable();
        $file = $this->file([$this->row()], fn (ZipArchive $zip) => $zip->addFromString('xl/worksheets/sheet1.xml', str_replace('<row r="2">', '<row r="2002">', $zip->getFromName('xl/worksheets/sheet1.xml'))));
        $this->postJson('/api/v1/trabajadores/importar', ['zona_id' => $this->zone, 'archivo' => $file])->assertUnprocessable();
        $this->assertSame(0, DB::table('trabajadores')->count());
    }

    public function test_template_has_expected_headers_and_text_format_for_dni(): void
    {
        $response = $this->get('/api/v1/trabajadores/plantilla')->assertOk();
        $path = $response->baseResponse->getFile()->getPathname();
        $this->paths[] = $path;
        $zip = new ZipArchive;
        $zip->open($path);
        $this->assertStringContainsString('Fecha de ingreso', $zip->getFromName('xl/worksheets/sheet1.xml'));
        $this->assertStringContainsString('numFmtId="49"', $zip->getFromName('xl/styles.xml'));
        $this->assertStringContainsString('s="2" t="inlineStr"', $zip->getFromName('xl/worksheets/sheet1.xml'));
        $zip->close();
    }

    public function test_manual_crud_validates_fields_and_preserves_history(): void
    {
        $body = ['zona_id' => $this->zone, 'tipo_documento' => 'DNI', 'numero_documento' => '01234567', 'nombres' => 'Ana', 'apellidos' => 'Torres', 'puesto' => 'Operadora', 'fecha_ingreso' => '2026-01-15'];
        foreach (['numero_documento' => '123', 'puesto' => '', 'fecha_ingreso' => '2026-02-30'] as $key => $value) {
            $this->postJson('/api/v1/trabajadores', array_replace($body, [$key => $value]))->assertUnprocessable();
        }
        $id = $this->postJson('/api/v1/trabajadores', $body)->assertCreated()->json('data.id');
        $this->getJson('/api/v1/trabajadores/'.$id)->assertOk()->assertJsonPath('data.puesto', 'Operadora');
        $this->patchJson('/api/v1/trabajadores/'.$id, ['puesto' => 'Supervisora'])->assertOk();
        $this->patchJson('/api/v1/trabajadores/'.$id, ['fecha_cese' => '2025-01-01'])->assertUnprocessable();
        $this->deleteJson('/api/v1/trabajadores/'.$id)->assertOk();
        $this->assertDatabaseHas('trabajadores', ['id' => $id, 'activo' => false, 'puesto' => 'Supervisora']);
    }

    public function test_company_scope_and_creation_permissions_are_enforced(): void
    {
        $other = $this->tenant('beta');
        DB::table('trabajadores')->insert(['empresa_id' => $other['company'], 'tipo_documento' => 'DNI', 'numero_documento' => '01234567', 'nombres' => 'Otra', 'apellidos' => 'Empresa']);
        $this->postJson('/api/v1/trabajadores/importar', ['zona_id' => $this->zone, 'archivo' => $this->file([$this->row()])])->assertOk()->assertJsonPath('validos', 1);
        $permission = DB::table('permisos')->where('codigo', 'organizacion.crear')->value('id');
        DB::table('rol_permisos')->where('rol_id', $this->tenant['role'])->where('permiso_id', $permission)->delete();
        $this->postJson('/api/v1/trabajadores/importar', ['zona_id' => $this->zone, 'archivo' => $this->file([$this->row()]), 'confirmar' => true])->assertForbidden();
        $this->get('/api/v1/trabajadores/plantilla')->assertForbidden();
    }
}
