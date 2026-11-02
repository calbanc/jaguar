<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('capacitacion_participantes', function (Blueprint $table) {
            $table->string('firma_ruta', 500)->nullable();
            $table->unsignedBigInteger('firma_bytes')->default(0);
            $table->dateTime('firma_fecha')->nullable();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Se conservan las firmas de asistencia.');
    }
};
