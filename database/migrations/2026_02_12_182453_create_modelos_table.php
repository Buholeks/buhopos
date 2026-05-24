<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modelos', function (Blueprint $table) {
            $table->id();

            // ── Multi-empresa / Multi-sucursal ───────────────────────────
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('sucursal_id')->constrained('sucursales'); // solo referencia, no filtra
            $table->foreignId('user_id')->constrained('users');

            // ── Relación con marca ────────────────────────────────────────
            $table->foreignId('marca_id')
                  ->constrained('marcas')
                  ->cascadeOnDelete(); // si se elimina la marca, se eliminan sus modelos

            // ── Datos ─────────────────────────────────────────────────────
            $table->string('nombre', 150);
            $table->string('imagen', 500)->nullable(); // ruta del archivo subido
            $table->boolean('activo')->default(true);

            $table->timestamps();
            $table->softDeletes();

            // ── Unicidad: mismo modelo no puede repetirse en la misma marca ──
            // Pero sí puede existir "Corolla" en Toyota y en otra marca
            $table->unique(['marca_id', 'nombre'], 'uq_modelos_marca_nombre');

            // ── Índices ───────────────────────────────────────────────────
            $table->index(['empresa_id', 'marca_id'],   'idx_modelos_empresa_marca');
            $table->index(['empresa_id', 'activo'],     'idx_modelos_empresa_activo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modelos');
    }
};