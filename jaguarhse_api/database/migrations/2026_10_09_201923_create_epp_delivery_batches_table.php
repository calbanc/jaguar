<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('epp_entrega_lotes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->uuid('uuid_cliente');
            $table->char('huella', 64);
            $table->unsignedInteger('total');
            $table->json('entregas');
            $table->timestamp('created_at');
            $table->unique(['empresa_id', 'uuid_cliente']);
            $table->foreign('empresa_id')->references('id')->on('empresas');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Se conserva el registro de lotes para evitar entregas duplicadas.');
    }
};
