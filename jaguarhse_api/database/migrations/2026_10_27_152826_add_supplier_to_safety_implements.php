<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('implementos', function (Blueprint $table) {
            $table->foreignId('proveedor_id')->nullable()->constrained('implementos_proveedores');
        });
    }

    public function down(): void
    {
        Schema::table('implementos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('proveedor_id');
        });
    }
};
