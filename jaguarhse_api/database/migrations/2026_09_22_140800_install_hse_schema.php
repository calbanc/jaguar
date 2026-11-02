<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            throw new RuntimeException('El esquema HSE requiere MySQL 8.0.16 o superior.');
        }
        $metadata = json_decode(file_get_contents(database_path('schema/metadata.json')), true, flags: JSON_THROW_ON_ERROR);
        foreach (array_keys($metadata) as $table) {
            if (Schema::hasTable($table)) {
                throw new RuntimeException('La instalación HSE requiere tablas vacías. Ya existe: '.$table);
            }
        }
        $sql = preg_replace('/^--.*$/m', '', file_get_contents(database_path('schema/hse.sql')));
        foreach (explode(';', $sql) as $statement) {
            if (trim($statement) !== '') {
                DB::unprepared($statement);
            }
        }
    }

    public function down(): void
    {
        throw new RuntimeException('HSE conserva históricos. La reversión destructiva requiere un procedimiento manual y respaldo.');
    }
};
