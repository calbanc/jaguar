<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zonas', function (Blueprint $table) {
            $table->unique(['empresa_id', 'sede_id', 'id'], 'uq_zona_sede_id');
        });
        Schema::create('trabajador_asignaciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('trabajador_id');
            $table->unsignedBigInteger('sede_id');
            $table->unsignedBigInteger('zona_id');
            $table->unsignedBigInteger('area_id')->nullable();
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->string('observaciones', 1000)->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent();
            $table->unique(['empresa_id', 'id'], 'uq_asignacion_empresa');
            $table->index(['empresa_id', 'trabajador_id', 'fecha_inicio', 'fecha_fin'], 'ix_asignacion_periodo');
            $table->foreign(['empresa_id', 'trabajador_id'])->references(['empresa_id', 'id'])->on('trabajadores');
            $table->foreign(['empresa_id', 'sede_id', 'zona_id'], 'fk_asignacion_zona')->references(['empresa_id', 'sede_id', 'id'])->on('zonas');
            $table->foreign(['empresa_id', 'zona_id', 'area_id'], 'fk_asignacion_area')->references(['empresa_id', 'zona_id', 'id'])->on('areas');
        });
        DB::table('trabajadores')->whereNotNull('zona_id')->whereNotNull('fecha_ingreso')->orderBy('id')->chunkById(250, function ($workers) {
            foreach ($workers as $worker) {
                $site = DB::table('zonas')->where('empresa_id', $worker->empresa_id)->where('id', $worker->zona_id)->value('sede_id');
                DB::table('trabajador_asignaciones')->insert([
                    'empresa_id' => $worker->empresa_id, 'trabajador_id' => $worker->id, 'sede_id' => $site,
                    'zona_id' => $worker->zona_id, 'area_id' => $worker->area_id,
                    'fecha_inicio' => $worker->fecha_ingreso, 'fecha_fin' => $worker->fecha_cese,
                    'observaciones' => 'Asignación inicial migrada desde la ficha del trabajador. Revisar las fechas si hubo traslados anteriores.',
                ]);
            }
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Se conserva el historial de sedes, zonas y áreas de los trabajadores.');
    }
};
