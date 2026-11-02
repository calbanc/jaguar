<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sedes', function (Blueprint $table) {
            $table->char('pais', 2)->nullable();
        });
        DB::table('sedes')->update(['pais' => DB::raw('(SELECT pais FROM empresas WHERE empresas.id = sedes.empresa_id)')]);
        Schema::table('sedes', function (Blueprint $table) {
            $table->char('pais', 2)->nullable(false)->change();
        });
        Schema::create('zonas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('sede_id');
            $table->string('nombre', 150);
            $table->boolean('activo')->default(true);
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent();
            $table->unique(['empresa_id', 'id'], 'uq_zonas_empresa');
            $table->unique(['empresa_id', 'sede_id', 'nombre'], 'uq_zona');
            $table->foreign('empresa_id')->references('id')->on('empresas');
            $table->foreign(['empresa_id', 'sede_id'])->references(['empresa_id', 'id'])->on('sedes');
        });
        Schema::table('areas', function (Blueprint $table) {
            $table->unsignedBigInteger('zona_id')->nullable();
        });
        foreach (DB::table('areas')->select('empresa_id', 'sede_id')->distinct()->get() as $site) {
            $zoneId = DB::table('zonas')->insertGetId([
                'empresa_id' => $site->empresa_id, 'sede_id' => $site->sede_id, 'nombre' => 'Zona general',
            ]);
            DB::table('areas')->where('empresa_id', $site->empresa_id)->where('sede_id', $site->sede_id)->update(['zona_id' => $zoneId]);
        }
        $siteForeignKey = collect(Schema::getForeignKeys('areas'))->first(fn (array $key) => $key['columns'] === ['empresa_id', 'sede_id']);
        $alterAreas = function () use ($siteForeignKey) {
            Schema::table('areas', function (Blueprint $table) use ($siteForeignKey) {
                $table->dropForeign($siteForeignKey['name'] ?? ['empresa_id', 'sede_id']);
                $table->dropUnique('uq_area');
                $table->dropColumn('sede_id');
                $table->unsignedBigInteger('zona_id')->nullable(false)->change();
                $table->unique(['empresa_id', 'zona_id', 'nombre'], 'uq_area_zona');
                $table->foreign(['empresa_id', 'zona_id'])->references(['empresa_id', 'id'])->on('zonas');
            });
        };
        if (DB::getDriverName() === 'sqlite') {
            Schema::withoutForeignKeyConstraints($alterAreas);
        } else {
            $alterAreas();
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Se conservan los países y las zonas para no perder la organización de las áreas existentes.');
    }
};
