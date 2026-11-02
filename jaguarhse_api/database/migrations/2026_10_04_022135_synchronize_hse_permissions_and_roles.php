<?php

use App\Services\HsePermissions;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(HsePermissions::class)->synchronize(updateAdministrators: true);
    }

    public function down(): void
    {
        throw new RuntimeException('La sincronización de permisos conserva los roles existentes; su reversión requiere un respaldo.');
    }
};
