<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuentas_bancarias', function (Blueprint $table) {
            $table->id();

            // ── Multi-empresa / Multi-sucursal ───────────────────────────
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('sucursal_id')->constrained('sucursales'); // solo referencia, no filtra
            $table->foreignId('user_id')->constrained('users');

            // ── Datos ────────────────────────────────────────────────────
            $table->string('nombre', 150);
            $table->string('banco', 100)->nullable();
            $table->string('numero_cuenta', 30)->nullable();
            $table->string('clabe', 18)->nullable();
            $table->string('titular', 150)->nullable();
            $table->boolean('activo')->default(true);

            $table->timestamps();
            $table->softDeletes();

            // ── Unicidad: misma cuenta no puede repetirse en la misma empresa ──
            $table->unique(['empresa_id', 'nombre'], 'uq_cuentas_bancarias_empresa_nombre');

            // ── Índices ──────────────────────────────────────────────────
            $table->index(['empresa_id', 'activo'], 'idx_cuentas_bancarias_empresa_activo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuentas_bancarias');
    }
};
