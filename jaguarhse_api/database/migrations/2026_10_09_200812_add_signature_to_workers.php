<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trabajadores', function (Blueprint $table) {
            $table->boolean('firma_almacenada')->default(false);
            $table->string('firma_ruta', 512)->nullable();
            $table->unsignedBigInteger('firma_bytes')->default(0);
            $table->timestamp('firma_actualizada_at')->nullable();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Se conservan los vínculos a las firmas almacenadas.');
    }
};
