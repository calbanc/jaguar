<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trabajadores', function (Blueprint $table) {
            $table->unsignedBigInteger('zona_id')->nullable();
        });
        DB::table('trabajadores')->whereNotNull('area_id')->update([
            'zona_id' => DB::raw('(SELECT zona_id FROM areas WHERE areas.id = trabajadores.area_id AND areas.empresa_id = trabajadores.empresa_id)'),
        ]);
        $addConstraints = function () {
            Schema::table('areas', function (Blueprint $table) {
                $table->unique(['empresa_id', 'zona_id', 'id'], 'uq_area_zona_id');
            });
            Schema::table('trabajadores', function (Blueprint $table) {
                $table->foreign(['empresa_id', 'zona_id'], 'fk_trabajador_zona')->references(['empresa_id', 'id'])->on('zonas');
                $table->foreign(['empresa_id', 'zona_id', 'area_id'], 'fk_trabajador_area_zona')->references(['empresa_id', 'zona_id', 'id'])->on('areas');
            });
        };
        if (DB::getDriverName() === 'sqlite') {
            $legacyAlter = (int) DB::selectOne('PRAGMA legacy_alter_table')->legacy_alter_table;
            DB::statement('PRAGMA legacy_alter_table = ON');
            try {
                Schema::withoutForeignKeyConstraints($addConstraints);
            } finally {
                DB::statement('PRAGMA legacy_alter_table = '.$legacyAlter);
            }
        } else {
            $addConstraints();
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Se conserva la zona asignada a cada trabajador.');
    }
};
