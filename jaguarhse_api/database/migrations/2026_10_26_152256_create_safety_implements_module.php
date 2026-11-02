<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('areas', function (Blueprint $table) {
            $table->json('coordenadas')->nullable();
        });
        Schema::create('implementos_catalogo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->string('codigo', 50);
            $table->string('nombre', 180);
            $table->string('categoria', 100);
            $table->text('descripcion')->nullable();
            $table->unsignedInteger('periodicidad_cantidad');
            $table->string('periodicidad_unidad', 10);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->unique(['empresa_id', 'codigo']);
        });
        Schema::create('implementos_proveedores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->string('nombre', 180);
            $table->string('documento', 30)->nullable();
            $table->string('contacto', 180)->nullable();
            $table->string('email', 200)->nullable();
            $table->string('telefono', 40)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
        Schema::create('implementos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('catalogo_id')->constrained('implementos_catalogo');
            $table->string('codigo', 50);
            $table->string('marca', 100)->nullable();
            $table->string('modelo', 100)->nullable();
            $table->string('serie', 100)->nullable();
            $table->date('fecha_adquisicion')->nullable();
            $table->string('estado', 30)->default('disponible');
            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->unique(['empresa_id', 'codigo']);
        });
        Schema::create('implemento_instalaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('implemento_id')->constrained('implementos');
            $table->foreignId('area_id')->constrained('areas');
            $table->string('ubicacion', 255);
            $table->string('piso', 50)->nullable();
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();
            $table->date('fecha_instalacion');
            $table->date('fecha_renovacion')->nullable();
            $table->date('fecha_retiro')->nullable();
            $table->timestamps();
            $table->index(['empresa_id', 'implemento_id', 'fecha_retiro'], 'implemento_instalacion_actual');
        });
        Schema::create('implemento_inspecciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('implemento_id')->constrained('implementos');
            $table->foreignId('instalacion_id')->constrained('implemento_instalaciones');
            $table->date('fecha_programada');
            $table->date('fecha_realizada')->nullable();
            $table->foreignId('responsable_id')->nullable()->constrained('miembros_empresa');
            $table->foreignId('realizado_por')->nullable()->constrained('miembros_empresa');
            $table->string('estado', 20)->default('pendiente');
            $table->string('resultado', 30)->nullable();
            $table->text('observaciones')->nullable();
            $table->unsignedInteger('periodicidad_cantidad');
            $table->string('periodicidad_unidad', 10);
            $table->timestamps();
            $table->index(['empresa_id', 'estado', 'fecha_programada'], 'implemento_agenda');
        });
        Schema::create('implemento_costos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('implemento_id')->constrained('implementos');
            $table->foreignId('proveedor_id')->constrained('implementos_proveedores');
            $table->string('tipo', 30);
            $table->date('fecha');
            $table->string('comprobante', 100)->nullable();
            $table->string('moneda', 3);
            $table->decimal('importe', 16, 2);
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
        Schema::create('implemento_archivos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('implemento_id')->constrained('implementos');
            $table->foreignId('inspeccion_id')->nullable()->constrained('implemento_inspecciones');
            $table->foreignId('archivo_id')->constrained('archivos');
            $table->timestamps();
        });
        DB::table('modulos')->updateOrInsert(['codigo' => 'implementos'], ['nombre' => 'Gestión de implementos de seguridad']);
        $module = DB::table('modulos')->where('codigo', 'implementos')->value('id');
        $source = DB::table('modulos')->where('codigo', 'inspecciones')->value('id');
        foreach (DB::table('empresa_modulos')->where('modulo_id', $source)->get() as $enabled) {
            DB::table('empresa_modulos')->insertOrIgnore(['empresa_id' => $enabled->empresa_id, 'modulo_id' => $module, 'activo' => $enabled->activo]);
        }
        foreach (['ver', 'crear', 'editar', 'eliminar', 'descargar'] as $action) {
            DB::table('permisos')->insertOrIgnore(['modulo_id' => $module, 'codigo' => 'implementos.'.$action, 'descripcion' => $action.' implementos de seguridad']);
            $permission = DB::table('permisos')->where('codigo', 'implementos.'.$action)->value('id');
            foreach (DB::table('rol_permisos as rp')->join('permisos as p', 'p.id', '=', 'rp.permiso_id')->where('p.codigo', 'inspecciones.'.$action)->select('rp.*')->get() as $grant) {
                DB::table('rol_permisos')->insertOrIgnore(['empresa_id' => $grant->empresa_id, 'rol_id' => $grant->rol_id, 'permiso_id' => $permission]);
            }
        }
    }

    public function down(): void
    {
        throw new RuntimeException('El historial de implementos requiere una reversión manual respaldada.');
    }
};
