<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categorias', function (Blueprint $table) {
            $table->id();

            // ── Multi-empresa / Multi-sucursal ──────────────────────────
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('sucursal_id')->constrained('sucursales');
            $table->foreignId('user_id')->constrained('users');

            // ── Jerarquía ───────────────────────────────────────────────
            $table->foreignId('categoria_padre_id')
                ->nullable()
                ->constrained('categorias')
                ->nullOnDelete(); // Si se elimina el padre, los hijos quedan como raíz

            // ── Datos ───────────────────────────────────────────────────
            $table->string('nombre', 150);
            $table->string('slug', 160);
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->unsignedInteger('orden')->default(0); // Orden dentro del mismo nivel
            $table->unsignedInteger('profundidad')->default(0); // Nivel (0 = raíz)

            $table->timestamps();
            $table->softDeletes();

            // ── Unicidad por empresa ─────────────────────────────────────
            // El mismo nombre no puede repetirse entre hermanos de la misma empresa.
            // Sí puede existir el mismo nombre en empresas distintas.
            $table->unique(['empresa_id', 'slug'],'uq_categorias_empresa_slug');
            $table->unique(['empresa_id', 'categoria_padre_id', 'nombre'], 'uq_categorias_empresa_padre_nombre');

            // ── Índices para búsquedas frecuentes ───────────────────────
            $table->index(['empresa_id', 'sucursal_id'],'idx_categorias_empresa_sucursal');
            $table->index(['empresa_id', 'activo'],'idx_categorias_empresa_activo');
            $table->index(['empresa_id', 'categoria_padre_id', 'orden'],   'idx_categorias_empresa_padre_orden');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categorias');
    }
};
