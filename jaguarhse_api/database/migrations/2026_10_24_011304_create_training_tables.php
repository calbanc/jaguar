<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['tema_capacitacion', 'lugar_capacitacion', 'capacitador', 'empresa_capacitacion'] as $name) {
            Schema::create($name, function (Blueprint $table) use ($name) {
                $this->base($table, $name);
                $table->string('nombre', 200);
                $table->text('descripcion')->nullable();
                if ($name === 'lugar_capacitacion') {
                    $table->string('direccion', 255)->nullable();
                }
                if (in_array($name, ['capacitador', 'empresa_capacitacion'], true)) {
                    $table->string('numero_documento', 30)->nullable();
                    $table->string('email', 200)->nullable();
                    $table->string('telefono', 30)->nullable();
                }
            });
        }
        Schema::create('capacitacion', function (Blueprint $table) {
            $this->base($table, 'capacitacion');
            foreach (['tema_capacitacion', 'lugar_capacitacion', 'capacitador', 'empresa_capacitacion'] as $catalog) {
                $table->unsignedBigInteger($catalog.'_id');
                $table->foreign(['empresa_id', $catalog.'_id'], 'fk_cap_'.$catalog)
                    ->references(['empresa_id', 'id'])->on($catalog);
            }
            $table->date('fecha');
            $table->time('hora_inicio');
            $table->time('hora_termino');
            $table->unsignedBigInteger('responsable_registro_id');
            $table->foreign(['empresa_id', 'responsable_registro_id'], 'fk_cap_responsable')
                ->references(['empresa_id', 'usuario_id'])->on('miembros_empresa');
            $table->text('observaciones')->nullable();
            $table->index(['empresa_id', 'fecha']);
        });
        Schema::create('capacitacion_participantes', function (Blueprint $table) {
            $this->base($table, 'capacitacion_participantes');
            $table->unsignedBigInteger('capacitacion_id');
            $table->unsignedBigInteger('trabajador_id');
            $table->unique(['empresa_id', 'capacitacion_id', 'trabajador_id'], 'uq_cap_participante');
            $table->foreign(['empresa_id', 'capacitacion_id'], 'fk_cap_part_capacitacion')->references(['empresa_id', 'id'])->on('capacitacion');
            $table->foreign(['empresa_id', 'trabajador_id'], 'fk_cap_part_trabajador')->references(['empresa_id', 'id'])->on('trabajadores');
        });
    }

    private function base(Blueprint $table, string $name): void
    {
        $table->id();
        $table->unsignedBigInteger('empresa_id');
        $table->foreign('empresa_id')->references('id')->on('empresas');
        $table->unique(['empresa_id', 'id'], 'uq_'.$name.'_empresa');
        $table->boolean('activo')->default(true);
        $table->dateTime('created_at')->useCurrent();
        $table->dateTime('updated_at')->useCurrent();
    }

    public function down(): void
    {
        throw new RuntimeException('Se conserva el historial de capacitaciones y participantes.');
    }
};
