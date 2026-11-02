<?php

namespace App\Console\Commands;

use App\Services\EmailVerification;
use App\Services\HsePermissions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class HseBootstrap extends Command
{
    protected $signature = 'hse:bootstrap {codigo} {razon_social} {email} {--pais=PE} {--identificacion=}';

    protected $description = 'Crea una empresa, contrato de prueba y administrador web/móvil; solicita contraseña sin mostrarla.';

    public function handle(): int
    {
        $input = ['codigo' => $this->argument('codigo'), 'razon_social' => $this->argument('razon_social'),
            'email' => $this->argument('email'), 'pais' => $this->option('pais'), 'identificacion_fiscal' => $this->option('identificacion'),
            'password' => $this->secret('Contraseña del nuevo administrador (mínimo 12 caracteres)')];
        Validator::make($input, ['codigo' => 'required|string|max:40|unique:empresas,codigo',
            'razon_social' => 'required|string|max:200', 'email' => 'required|email|max:190|unique:usuarios,email',
            'pais' => 'required|string|size:2', 'identificacion_fiscal' => 'required|string|max:30', 'password' => 'required|string|min:12|max:255'])->validate();
        $userId = DB::transaction(function () use ($input) {
            app(HsePermissions::class)->synchronize();
            $empresa = DB::table('empresas')->insertGetId(collect($input)->only(['codigo', 'razon_social', 'pais', 'identificacion_fiscal'])->all());
            DB::table('contratos')->insert(['empresa_id' => $empresa, 'numero' => 'INICIAL', 'fecha_inicio' => now()->toDateString(),
                'fecha_fin' => now()->addDays(30)->toDateString(), 'estado' => 'prueba']);
            $user = DB::table('usuarios')->insertGetId(['nombre' => 'Administrador', 'email' => $input['email'], 'password_hash' => Hash::make($input['password'])]);
            $member = DB::table('miembros_empresa')->insertGetId(['empresa_id' => $empresa, 'usuario_id' => $user]);
            $role = DB::table('roles')->insertGetId(['empresa_id' => $empresa, 'nombre' => 'Administrador', 'descripcion' => HsePermissions::ADMIN_DESCRIPTION]);
            foreach (DB::table('modulos')->whereIn('codigo', array_keys(config('hse.modules')))->get() as $module) {
                DB::table('empresa_modulos')->insert(['empresa_id' => $empresa, 'modulo_id' => $module->id]);
                foreach (array_keys(HsePermissions::actionsFor($module->codigo)) as $action) {
                    $code = $module->codigo.'.'.$action;
                    DB::table('rol_permisos')->insert(['empresa_id' => $empresa, 'rol_id' => $role, 'permiso_id' => DB::table('permisos')->where('codigo', $code)->value('id')]);
                }
            }
            foreach (DB::table('plataformas')->get() as $platform) {
                DB::table('accesos_aplicacion')->insert(['empresa_id' => $empresa, 'miembro_id' => $member, 'plataforma_id' => $platform->id, 'rol_id' => $role]);
            }

            return $user;
        });
        $verification = app(EmailVerification::class);
        $this->info($verification->registrationMessage($verification->send($userId)));
        $this->info('Empresa creada. Contrato de prueba: 30 días. Accesos web y móvil habilitados.');

        return self::SUCCESS;
    }
}
