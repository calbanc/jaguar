<?php

use App\Services\EppPlanillas;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('epp_planillas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('numero');
            $table->uuid('uuid_cliente');
            $table->date('fecha');
            $table->text('zona');
            $table->unsignedBigInteger('registrado_por')->nullable();
            $table->string('responsable');
            $table->unsignedInteger('trabajadores');
            $table->json('entregas');
            $table->timestamp('created_at');
            $table->unique(['empresa_id', 'numero']);
            $table->unique(['empresa_id', 'uuid_cliente']);
            $table->foreign('empresa_id')->references('id')->on('empresas');
            $table->foreign(['empresa_id', 'registrado_por'])->references(['empresa_id', 'id'])->on('miembros_empresa');
        });

        foreach (DB::table('empresas')->orderBy('id')->get() as $company) {
            DB::transaction(function () use ($company) {
                DB::table('empresas')->where('id', $company->id)->lockForUpdate()->first();
                $batches = [];
                foreach (DB::table('epp_entrega_lotes')->where('empresa_id', $company->id)->get() as $batch) {
                    foreach (json_decode($batch->entregas, true, flags: JSON_THROW_ON_ERROR) as $id) {
                        $batches[$id] = $batch;
                    }
                }
                $processed = [];
                foreach (DB::table('epp_entregas')->where('empresa_id', $company->id)->whereIn('estado', ['confirmada', 'anulada'])->orderBy('id')->get() as $delivery) {
                    $batch = $batches[$delivery->id] ?? null;
                    $uuid = $batch?->uuid_cliente ?? $delivery->uuid_cliente;
                    if (isset($processed[$uuid])) {
                        continue;
                    }
                    $ids = $batch ? json_decode($batch->entregas, true, flags: JSON_THROW_ON_ERROR) : [$delivery->id];
                    $actor = DB::table('auditoria_eventos')->where('empresa_id', $company->id)->where('entidad', 'epp_entregas')
                        ->where('registro_id', $delivery->id)->whereIn('accion', ['entregar', 'crear'])->orderBy('id')->value('actor_id');
                    app(EppPlanillas::class)->register((object) ['empresa_id' => $company->id, 'zona_horaria' => $company->zona_horaria, 'miembro_id' => $actor], $uuid, $ids);
                    $processed[$uuid] = true;
                }
            });
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Se conservan las planillas y su numeración para evitar referencias duplicadas.');
    }
};
