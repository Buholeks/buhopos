<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('producto_variante_grupos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->foreignId('tipo_atributo_id')->constrained('tipo_atributos')->cascadeOnDelete();
            $table->foreignId('atributo_id')->constrained('atributos')->cascadeOnDelete();
            $table->string('codigo', 100);
            $table->boolean('es_personalizado')->default(false);
            $table->timestamps();

            $table->unique(['empresa_id', 'codigo'], 'uq_variante_grupos_empresa_codigo');
            $table->unique(
                ['producto_id', 'tipo_atributo_id', 'atributo_id'],
                'uq_variante_grupos_producto_atributo'
            );
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('producto_variante_grupos');
    }
};
