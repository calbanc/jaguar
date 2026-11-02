<?php

namespace App\Console\Commands;

use App\Services\HsePermissions;
use Illuminate\Console\Command;

class HseSyncPermissions extends Command
{
    protected $signature = 'hse:sincronizar-permisos';

    protected $description = 'Sincroniza el catálogo de permisos y los roles Administrador con los módulos habilitados; conserva los roles personalizados.';

    public function handle(HsePermissions $permissions): int
    {
        $permissions->synchronize(updateAdministrators: true);
        $this->info('Permisos y roles Administrador sincronizados con los módulos habilitados.');

        return self::SUCCESS;
    }
}
