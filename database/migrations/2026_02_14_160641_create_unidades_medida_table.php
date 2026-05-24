<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unidades_medida', function (Blueprint $table) {
            $table->id();

            // ── Multi-empresa ─────────────────────────────────────────────
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('sucursal_id')->constrained('sucursales');
            $table->foreignId('user_id')->constrained('users');

            // ── Datos ─────────────────────────────────────────────────────
            $table->string('nombre', 100);         // Pieza, Par, Caja, Kilogramo…
            $table->string('abreviatura', 20);     // pza, par, caja, kg…
            $table->enum('tipo', [                 // Clasificación del tipo
                'cantidad',   // Pieza, Par, Caja, Docena
                'peso',       // Kilogramo, Gramo, Libra
                'volumen',    // Litro, Mililitro, Galón
                'longitud',   // Metro, Centímetro, Pulgada
            ])->default('cantidad');
            $table->boolean('activo')->default(true);

            $table->timestamps();
            $table->softDeletes();

            // ── Unicidad por empresa ──────────────────────────────────────
            // No puede haber dos unidades con el mismo nombre en la misma empresa
            $table->unique(['empresa_id', 'nombre'],       'uq_um_empresa_nombre');
            // Tampoco la misma abreviatura en la misma empresa
            $table->unique(['empresa_id', 'abreviatura'],  'uq_um_empresa_abreviatura');

            // ── Índices ───────────────────────────────────────────────────
            $table->index(['empresa_id', 'tipo'],   'idx_um_empresa_tipo');
            $table->index(['empresa_id', 'activo'], 'idx_um_empresa_activo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unidades_medida');
    }
};