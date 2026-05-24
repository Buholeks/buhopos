<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('producto_variantes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('producto_id')
                  ->constrained('productos')
                  ->cascadeOnDelete();

            $table->foreignId('empresa_id')->constrained('empresas');

            // ── Identificación de la variante ─────────────────────────────
            $table->string('sku', 100)->nullable();           // generado o manual
            $table->string('codigo_barras', 100)->nullable();
            $table->string('imagen', 500)->nullable();        // imagen propia de la variante

            // ── Precios propios (NULL = hereda del producto padre) ─────────
            $table->decimal('precio_costo', 12, 2)->nullable();
            $table->decimal('precio_venta', 12, 2)->nullable();
            $table->decimal('precio1', 12, 2)->nullable();
            $table->decimal('precio2', 12, 2)->nullable();
            $table->decimal('precio3', 12, 2)->nullable();
            $table->decimal('precio4', 12, 2)->nullable();
            $table->decimal('precio5', 12, 2)->nullable();

            // ── Oferta propia por variante ────────────────────────────────
            // Cada variante puede tener su propia oferta independiente
            $table->decimal('precio_oferta', 12, 2)->nullable();
            $table->boolean('oferta_activa')->default(false);
            $table->date('oferta_hasta')->nullable(); // null = sin vencimiento

            // ── Control ───────────────────────────────────────────────────
            $table->decimal('stock_minimo', 10, 2)->nullable(); // NULL = hereda padre
            $table->boolean('activo')->default(true);

            $table->timestamps();
            $table->softDeletes();

            // ── Unicidad ──────────────────────────────────────────────────
            $table->unique(['empresa_id', 'sku'],          'uq_variantes_empresa_sku');
            $table->unique(['empresa_id', 'codigo_barras'],'uq_variantes_empresa_barras');

            // ── Índices ───────────────────────────────────────────────────
            $table->index(['producto_id', 'activo'],   'idx_variantes_producto_activo');
            $table->index(['empresa_id',  'activo'],   'idx_variantes_empresa_activo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('producto_variantes');
    }
};