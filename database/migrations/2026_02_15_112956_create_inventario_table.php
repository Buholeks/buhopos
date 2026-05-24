<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();
            $table->foreignId('producto_id')->constrained('productos');
            $table->foreignId('variante_id')->nullable()->constrained('producto_variantes')->nullOnDelete();

            $table->decimal('stock',       10, 3)->default(0);
            $table->decimal('stock_minimo', 10, 3)->default(0);  // copia local para alertas rápidas

            $table->timestamps();

            // Una fila por empresa+sucursal+producto+variante
            $table->unique(
                ['empresa_id', 'sucursal_id', 'producto_id', 'variante_id'],
                'uq_inventario'
            );
            $table->index(['empresa_id', 'variante_id'], 'idx_inv_variante');
            $table->index(['empresa_id', 'producto_id'], 'idx_inv_producto');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario');
    }
};