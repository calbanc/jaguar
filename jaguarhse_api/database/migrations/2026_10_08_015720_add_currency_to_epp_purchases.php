<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('epp_compras', function (Blueprint $table) {
            $table->decimal('tipo_cambio', 12, 6)->default(1);
            $table->decimal('costo_total_original', 24, 6)->nullable();
        });
        Schema::table('epp_compra_detalles', function (Blueprint $table) {
            $table->decimal('costo_unitario_original', 18, 6)->nullable();
            $table->decimal('costo_total_original', 24, 6)->nullable();
        });
        DB::table('epp_compras')->update(['costo_total_original' => DB::raw('costo_total')]);
        DB::table('epp_compra_detalles')->update(['costo_unitario_original' => DB::raw('costo_unitario'), 'costo_total_original' => DB::raw('costo_total')]);
    }

    public function down(): void
    {
        throw new RuntimeException('Se conservan las monedas y tipos de cambio de las compras.');
    }
};
