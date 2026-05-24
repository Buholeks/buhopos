<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('variante_atributos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('variante_id')
                  ->constrained('producto_variantes')
                  ->cascadeOnDelete();

            $table->foreignId('tipo_atributo_id')
                  ->constrained('tipo_atributos')
                  ->cascadeOnDelete();

            $table->foreignId('atributo_id')
                  ->constrained('atributos')
                  ->cascadeOnDelete();

            // Una variante no puede tener dos valores del mismo tipo
            // Ej: no puede ser Rojo Y Azul al mismo tiempo
            $table->unique(
                ['variante_id', 'tipo_atributo_id'],
                'uq_variante_un_valor_por_tipo'
            );

            $table->index(['variante_id',      'atributo_id'], 'idx_va_variante_atributo');
            $table->index(['tipo_atributo_id', 'atributo_id'], 'idx_va_tipo_atributo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variante_atributos');
    }
};