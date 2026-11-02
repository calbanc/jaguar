<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('epp_entregas', function (Blueprint $table) {
            $table->enum('motivo', ['nuevo', 'reposicion'])->nullable();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Se conserva el motivo de las entregas registradas.');
    }
};
