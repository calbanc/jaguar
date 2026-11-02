<?php

namespace Tests\Support;

use App\Services\HsePermissions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

trait HseDatabase
{
    protected function installHseFixture(bool $withZones = true, bool $withWorkerZones = true, bool $withAssignments = true): void
    {
        $sql = preg_replace('/^--.*$/m', '', file_get_contents(database_path('schema/hse.sql')));
        preg_match_all('/CREATE TABLE (\w+) \((.*?)\) ENGINE=.*?;/s', $sql, $tables, PREG_SET_ORDER);
        foreach ($tables as $table) {
            $body = $table[2];
            $body = preg_replace('/id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT/', 'id INTEGER PRIMARY KEY AUTOINCREMENT', $body, 1);
            $body = preg_replace('/^\s*PRIMARY KEY \(id\),?\n/m', "\n", $body);
            $body = preg_replace('/ENUM\([^)]*\)/', 'TEXT', $body);
            $body = preg_replace('/BIGINT UNSIGNED|INT UNSIGNED/', 'INTEGER', $body);
            $body = preg_replace('/CHARACTER SET \w+( COLLATE \w+)?/', '', $body);
            $body = str_replace(' ON UPDATE CURRENT_TIMESTAMP', '', $body);
            preg_match_all('/UNIQUE KEY (\w+) \(([^)]+)\)/', $body, $indexes, PREG_SET_ORDER);
            $body = preg_replace('/^\s*UNIQUE KEY \w+ .*?,?\n/m', "\n", $body);
            $body = preg_replace('/^\s*KEY \w+ .*?,?\n/m', "\n", $body);
            $body = str_replace('JSON_TYPE(mime_permitidos)', 'UPPER(JSON_TYPE(mime_permitidos))', $body);
            $body = str_replace('DAYOFMONTH(periodo)', "CAST(strftime('%d', periodo) AS INTEGER)", $body);
            DB::statement('CREATE TABLE '.$table[1].' ('.rtrim(trim($body), ',').')');
            foreach ($indexes as $index) {
                DB::statement('CREATE UNIQUE INDEX '.$index[1].' ON '.$table[1].' ('.$index[2].')');
            }
        }
        preg_match_all('/CREATE SQL SECURITY INVOKER VIEW .*?;/s', $sql, $views);
        foreach ($views[0] as $view) {
            $view = str_replace('CREATE SQL SECURITY INVOKER VIEW', 'CREATE VIEW', $view);
            $view = str_replace('m.numerador / NULLIF', 'CAST(m.numerador AS REAL) / NULLIF', $view);
            DB::statement($view);
        }
        (require database_path('migrations/2026_10_08_015026_add_epp_purchases_and_valuation.php'))->up();
        (require database_path('migrations/2026_10_08_015720_add_currency_to_epp_purchases.php'))->up();
        (require database_path('migrations/2026_10_08_015908_add_worker_epp_cost_report.php'))->up();
        (require database_path('migrations/2026_10_08_020359_add_epp_movement_detail_view.php'))->up();
        if ($withZones) {
            (require database_path('migrations/2026_10_09_173241_add_zones_and_site_countries.php'))->up();
            if ($withWorkerZones) {
                (require database_path('migrations/2026_10_09_174218_add_zone_to_workers.php'))->up();
                if ($withAssignments) {
                    (require database_path('migrations/2026_10_09_174837_create_worker_assignments.php'))->up();
                }
            }
        }
        (require database_path('migrations/2026_10_09_200812_add_signature_to_workers.php'))->up();
        (require database_path('migrations/2026_10_09_201511_add_reason_to_epp_deliveries.php'))->up();
        (require database_path('migrations/2026_10_09_201923_create_epp_delivery_batches_table.php'))->up();
        (require database_path('migrations/2026_10_14_135511_create_epp_planillas_table.php'))->up();
        preg_match_all('/INSERT INTO (?:plataformas|modulos).*?;/s', $sql, $inserts);
        foreach ($inserts[0] as $insert) {
            DB::statement($insert);
        }
    }

    protected function tenant(string $code): array
    {
        $company = DB::table('empresas')->insertGetId(['codigo' => $code, 'razon_social' => $code, 'pais' => 'PE', 'identificacion_fiscal' => $code]);
        DB::table('contratos')->insert(['empresa_id' => $company, 'numero' => 'TEST', 'fecha_inicio' => '2020-01-01', 'estado' => 'activo']);
        $user = DB::table('usuarios')->insertGetId(['nombre' => $code, 'email' => $code.'@example.test', 'password_hash' => Hash::make('password-test-123'), 'email_verified_at' => now()]);
        $member = DB::table('miembros_empresa')->insertGetId(['empresa_id' => $company, 'usuario_id' => $user]);
        $role = DB::table('roles')->insertGetId(['empresa_id' => $company, 'nombre' => 'Admin']);
        foreach (DB::table('modulos')->get() as $module) {
            DB::table('empresa_modulos')->insert(['empresa_id' => $company, 'modulo_id' => $module->id]);
            foreach (array_keys(HsePermissions::actionsFor($module->codigo)) as $action) {
                $permission = DB::table('permisos')->where('codigo', $module->codigo.'.'.$action)->value('id') ??
                    DB::table('permisos')->insertGetId(['modulo_id' => $module->id, 'codigo' => $module->codigo.'.'.$action, 'descripcion' => $action]);
                DB::table('rol_permisos')->insert(['empresa_id' => $company, 'rol_id' => $role, 'permiso_id' => $permission]);
            }
        }
        $access = DB::table('accesos_aplicacion')->insertGetId(['empresa_id' => $company, 'miembro_id' => $member, 'rol_id' => $role,
            'plataforma_id' => DB::table('plataformas')->where('codigo', 'web')->value('id')]);

        return compact('company', 'user', 'member', 'role', 'access', 'code');
    }

    protected function loginAsTenant(array $tenant): string
    {
        $response = $this->postJson('/api/v1/auth/login', ['email' => $tenant['code'].'@example.test', 'password' => 'password-test-123',
            'empresa_codigo' => $tenant['code'], 'plataforma' => 'web'])->assertOk();
        $token = $response->json('token');
        $this->withToken($token);

        return $token;
    }
}
