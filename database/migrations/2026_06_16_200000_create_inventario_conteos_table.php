<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventario_conteos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('sucursal_id')->constrained('sucursales');
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('revisado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('ajustado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('folio', 40);
            $table->enum('modo', ['ciego'])->default('ciego');
            $table->enum('estado', ['en_conteo', 'en_revision', 'ajustado', 'cancelado'])->default('en_conteo');
            $table->timestamp('snapshot_at')->nullable();
            $table->timestamp('cerrado_at')->nullable();
            $table->timestamp('ajustado_at')->nullable();
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'folio'], 'uq_inv_conteos_empresa_folio');
            $table->index(['empresa_id', 'sucursal_id', 'estado'], 'idx_inv_conteos_scope');
        });

        Schema::create('inventario_conteo_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conteo_id')->constrained('inventario_conteos')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos');
            $table->foreignId('variante_id')->nullable()->constrained('producto_variantes')->nullOnDelete();
            $table->decimal('stock_sistema', 12, 3)->default(0);
            $table->decimal('cantidad_fisica', 12, 3)->default(0);
            $table->decimal('diferencia', 12, 3)->default(0);
            $table->decimal('costo_unitario', 12, 2)->default(0);
            $table->enum('estado', ['no_contado', 'completo', 'faltante', 'sobrante', 'nuevo_encontrado'])->default('no_contado');
            $table->json('series_contadas')->nullable();
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->unique(['conteo_id', 'producto_id', 'variante_id'], 'uq_inv_conteo_item');
            $table->index(['conteo_id', 'estado'], 'idx_inv_conteo_det_estado');
        });

        Schema::create('inventario_movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('sucursal_id')->constrained('sucursales');
            $table->foreignId('producto_id')->constrained('productos');
            $table->foreignId('variante_id')->nullable()->constrained('producto_variantes')->nullOnDelete();
            $table->foreignId('conteo_id')->nullable()->constrained('inventario_conteos')->nullOnDelete();
            $table->foreignId('conteo_detalle_id')->nullable()->constrained('inventario_conteo_detalles')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->enum('tipo', ['ajuste_positivo', 'ajuste_negativo']);
            $table->decimal('cantidad_anterior', 12, 3)->default(0);
            $table->decimal('cantidad_movimiento', 12, 3)->default(0);
            $table->decimal('cantidad_nueva', 12, 3)->default(0);
            $table->string('motivo', 160);
            $table->timestamps();

            $table->index(['empresa_id', 'sucursal_id', 'created_at'], 'idx_inv_mov_scope');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_movimientos');
        Schema::dropIfExists('inventario_conteo_detalles');
        Schema::dropIfExists('inventario_conteos');
    }
};
