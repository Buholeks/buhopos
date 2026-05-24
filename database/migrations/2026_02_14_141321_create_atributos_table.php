<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atributos', function (Blueprint $table) {
            $table->id();

            // ── Multi-empresa ─────────────────────────────────────────────
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('sucursal_id')->constrained('sucursales');
            $table->foreignId('user_id')->constrained('users');

            // ── Relación con tipo ─────────────────────────────────────────
            $table->foreignId('tipo_atributo_id')
                ->constrained('tipo_atributos')
                ->cascadeOnDelete(); // si se elimina el tipo, se eliminan sus valores

            // ── Datos ─────────────────────────────────────────────────────
            $table->string('valor', 150);  // "Rojo", "XL", "Algodón"
            $table->boolean('activo')->default(true);

            $table->timestamps();
            $table->softDeletes();

            // ── Unicidad: mismo valor no puede repetirse en el mismo tipo+empresa ──
            // "Rojo" no puede existir dos veces en Color de Empresa A
            // pero Empresa B sí puede tener su propio "Rojo"
            $table->unique(
                ['empresa_id', 'tipo_atributo_id', 'valor'],
                'uq_atributos_empresa_tipo_valor'
            );

            // ── Índices ───────────────────────────────────────────────────
            $table->index(['empresa_id', 'tipo_atributo_id'], 'idx_atributos_empresa_tipo');
            $table->index(['empresa_id', 'activo'],           'idx_atributos_empresa_activo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atributos');
    }
};
