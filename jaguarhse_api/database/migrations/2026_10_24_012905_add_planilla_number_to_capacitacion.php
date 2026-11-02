<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('capacitacion', function (Blueprint $table) {
            $table->unsignedBigInteger('numero_planilla')->nullable();
        });

        foreach (DB::table('capacitacion')->distinct()->pluck('empresa_id') as $companyId) {
            DB::transaction(function () use ($companyId) {
                DB::table('empresas')->where('id', $companyId)->lockForUpdate()->first();
                $number = 0;
                DB::table('capacitacion')->where('empresa_id', $companyId)->orderBy('id')->chunkById(500, function ($rows) use (&$number) {
                    foreach ($rows as $row) {
                        DB::table('capacitacion')->where('id', $row->id)->update(['numero_planilla' => ++$number]);
                    }
                });
            });
        }

        Schema::table('capacitacion', function (Blueprint $table) {
            $table->unsignedBigInteger('numero_planilla')->nullable(false)->change();
            $table->unique(['empresa_id', 'numero_planilla'], 'uq_capacitacion_planilla_empresa');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Se conserva la numeración de las planillas de capacitación.');
    }
};
