<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compra_detalles', function (Blueprint $table) {
            $table->json('etiqueta_snapshot')->nullable()->after('subtotal');
        });

        Schema::create('etiqueta_plantillas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('nombre', 120);
            $table->enum('tipo', ['compra', 'precio']);
            $table->decimal('ancho_mm', 7, 2)->default(62);
            $table->decimal('alto_mm', 7, 2)->default(29);
            $table->json('diseno');
            $table->boolean('predeterminada')->default(false);
            $table->boolean('activa')->default(true);
            $table->timestamps();
            $table->index(['empresa_id', 'tipo', 'activa']);
        });

        Schema::create('etiqueta_perfiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('nombre', 120);
            $table->string('impresora', 120)->default('Brother QL-800');
            $table->enum('material', ['precortada', 'continua', 'hoja'])->default('precortada');
            $table->decimal('ancho_mm', 7, 2)->default(62);
            $table->decimal('alto_mm', 7, 2)->default(29);
            $table->decimal('separacion_mm', 7, 2)->default(0);
            $table->decimal('offset_x_mm', 7, 2)->default(0);
            $table->decimal('offset_y_mm', 7, 2)->default(0);
            $table->decimal('escala', 6, 3)->default(1);
            $table->boolean('corte_automatico')->default(true);
            $table->boolean('predeterminado')->default(false);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->index(['empresa_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('etiqueta_perfiles');
        Schema::dropIfExists('etiqueta_plantillas');
        Schema::table('compra_detalles', fn(Blueprint $table) => $table->dropColumn('etiqueta_snapshot'));
    }
};
