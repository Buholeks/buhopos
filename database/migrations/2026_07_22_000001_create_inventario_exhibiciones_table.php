<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventario_exhibiciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('sucursal_id')->constrained('sucursales');
            $table->foreignId('producto_id')->constrained('productos');
            $table->foreignId('variante_id')->nullable()->constrained('producto_variantes')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('tipo_cobertura', ['producto', 'variante'])->default('producto');
            $table->enum('estado_exhibicion', ['perfecto', 'caja_abierta', 'con_detalles']);
            $table->enum('estado', ['activa', 'vendida', 'retirada'])->default('activa');
            $table->boolean('activo')->default(true);
            $table->foreignId('venta_id')->nullable()->constrained('ventas')->nullOnDelete();
            $table->foreignId('venta_detalle_id')->nullable()->constrained('venta_detalles')->nullOnDelete();
            $table->timestamp('vendido_at')->nullable();
            $table->timestamp('retirado_at')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'sucursal_id', 'producto_id', 'activo'], 'idx_inv_exh_producto_activo');
            $table->index(['empresa_id', 'sucursal_id', 'producto_id', 'variante_id', 'activo'], 'idx_inv_exh_variante_activa');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_exhibiciones');
    }
};
