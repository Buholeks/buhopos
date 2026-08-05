<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guarda la respuesta exacta de un envío exitoso para poder "repetirla" tal cual
        // si el mismo idempotency_key vuelve a llegar (doble clic, reintento de red por
        // timeout). A diferencia de `ventas.idempotency_key` (que apunta a una fila que
        // siempre se crea), acciones como cancelar/abonar un pedido no siempre crean una
        // fila nueva, así que aquí se guarda la respuesta en vez de una referencia.
        Schema::create('idempotencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('accion', 100);
            $table->string('clave', 64);
            $table->unsignedSmallInteger('status_code');
            $table->json('respuesta');
            $table->timestamps();

            $table->unique(['empresa_id', 'accion', 'clave'], 'idx_idempotencias_unica');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotencias');
    }
};
