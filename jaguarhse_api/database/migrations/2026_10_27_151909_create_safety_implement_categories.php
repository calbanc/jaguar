<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('implementos_categorias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->string('nombre', 100);
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->unique(['empresa_id', 'nombre']);
        });
        Schema::table('implementos_catalogo', function (Blueprint $table) {
            $table->foreignId('categoria_id')->nullable()->constrained('implementos_categorias');
        });
        DB::table('implementos_catalogo')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                $name = trim($row->categoria) ?: 'Sin categoría';
                $category = DB::table('implementos_categorias')->where('empresa_id', $row->empresa_id)->where('nombre', $name)->value('id');
                if (! $category) {
                    $category = DB::table('implementos_categorias')->insertGetId(['empresa_id' => $row->empresa_id, 'nombre' => $name, 'activo' => true, 'created_at' => now(), 'updated_at' => now()]);
                }
                DB::table('implementos_catalogo')->where('id', $row->id)->update(['categoria_id' => $category]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('implementos_catalogo', function (Blueprint $table) {
            $table->dropConstrainedForeignId('categoria_id');
        });
        Schema::dropIfExists('implementos_categorias');
    }
};
