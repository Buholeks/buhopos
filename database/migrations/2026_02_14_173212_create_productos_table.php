<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();

            // ── Multi-empresa ─────────────────────────────────────────────
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('sucursal_id')->constrained('sucursales');
            $table->foreignId('user_id')->constrained('users');

            // ── Relaciones del catálogo ───────────────────────────────────
            $table->foreignId('categoria_id')
                  ->nullable()->constrained('categorias')->nullOnDelete();

            $table->foreignId('marca_id')
                  ->nullable()->constrained('marcas')->nullOnDelete();

            $table->foreignId('modelo_id')
                  ->nullable()->constrained('modelos')->nullOnDelete();

            $table->foreignId('unidad_medida_id')
                  ->nullable()->constrained('unidades_medida')->nullOnDelete();

            // ── Identificación ────────────────────────────────────────────
            $table->string('nombre', 200);
            $table->string('codigo', 100);       // SKU interno único por empresa
            $table->text('descripcion')->nullable();
            $table->string('imagen', 500)->nullable(); // una sola imagen de referencia

            // ── Precios base ──────────────────────────────────────────────
            // Las variantes pueden sobreescribir estos valores (NULL = hereda)
            $table->decimal('precio_costo', 12, 2)->default(0);
            $table->decimal('precio_venta', 12, 2)->default(0);
            $table->decimal('precio1', 12, 2)->nullable(); // Ej. Menudeo
            $table->decimal('precio2', 12, 2)->nullable(); // Ej. Medio
            $table->decimal('precio3', 12, 2)->nullable(); // Ej. Mayoreo
            $table->decimal('precio4', 12, 2)->nullable(); // Ej. Especial
            $table->decimal('precio5', 12, 2)->nullable(); // Ej. VIP

            // ── Control ───────────────────────────────────────────────────
            // NOTA: precio_oferta, oferta_activa y oferta_hasta se manejan
            // por variante en producto_variantes (cada variante tiene su propia oferta)
            $table->decimal('stock_minimo', 10, 2)->default(0);
            $table->decimal('peso', 8, 3)->nullable(); // kg, para envíos

            // ── Flags ─────────────────────────────────────────────────────
            $table->boolean('tiene_variantes')->default(false);
            $table->boolean('activo')->default(true);

            $table->timestamps();
            $table->softDeletes();

            // ── Unicidad: código único por empresa ────────────────────────
            $table->unique(['empresa_id', 'codigo'], 'uq_productos_empresa_codigo');

            // ── Índices ───────────────────────────────────────────────────
            $table->index(['empresa_id', 'categoria_id'],    'idx_productos_empresa_categoria');
            $table->index(['empresa_id', 'marca_id'],        'idx_productos_empresa_marca');
            $table->index(['empresa_id', 'activo'],          'idx_productos_empresa_activo');
            $table->index(['empresa_id', 'tiene_variantes'], 'idx_productos_empresa_variantes');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};