<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capacitacion_formatos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->foreign('empresa_id')->references('id')->on('empresas');
            $table->string('nombre', 200);
            $table->json('diseno');
            $table->unsignedInteger('version')->default(1);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->index(['empresa_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capacitacion_formatos');
    }
};
