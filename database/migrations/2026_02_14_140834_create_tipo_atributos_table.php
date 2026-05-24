<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipo_atributos', function (Blueprint $table) {
            $table->id();

            // ── Multi-empresa ─────────────────────────────────────────────
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('sucursal_id')->constrained('sucursales');
            $table->foreignId('user_id')->constrained('users');

            // ── Datos ─────────────────────────────────────────────────────
            $table->string('nombre', 100);  // "Color", "Talla", "Material"
            $table->boolean('activo')->default(true);

            $table->timestamps();
            $table->softDeletes();

            // ── Unicidad: mismo tipo no puede repetirse en la misma empresa ─
            $table->unique(['empresa_id', 'nombre'], 'uq_tipo_atributos_empresa_nombre');

            // ── Índices ───────────────────────────────────────────────────
            $table->index(['empresa_id', 'activo'], 'idx_tipo_atributos_empresa_activo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipo_atributos');
    }
};
