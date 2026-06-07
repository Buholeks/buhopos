<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devoluciones_proveedor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('sucursal_id')->constrained('sucursales');
            $table->foreignId('compra_id')->constrained('compras');
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->date('fecha');
            $table->string('referencia', 100)->nullable();
            $table->text('motivo');
            $table->decimal('total', 14, 2);
            $table->decimal('aplicado_saldo', 14, 2)->default(0);
            $table->decimal('reembolso_pendiente', 14, 2)->default(0);
            $table->enum('estado', ['confirmada', 'cancelada'])->default('confirmada');
            $table->timestamps();

            $table->index(['empresa_id', 'sucursal_id', 'fecha'], 'idx_dev_proveedor_tenant_fecha');
        });

        Schema::create('devolucion_proveedor_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('devolucion_proveedor_id')->constrained('devoluciones_proveedor')->cascadeOnDelete();
            $table->foreignId('compra_detalle_id')->constrained('compra_detalles');
            $table->foreignId('producto_id')->constrained('productos');
            $table->foreignId('variante_id')->nullable()->constrained('producto_variantes')->nullOnDelete();
            $table->decimal('cantidad', 10, 3);
            $table->decimal('precio_compra', 12, 2);
            $table->decimal('subtotal', 14, 2);
            $table->timestamps();
        });

        Schema::create('devolucion_proveedor_series', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('devolucion_proveedor_detalle_id');
            $table->foreignId('serie_id')->constrained('series');
            $table->unique('serie_id');
            $table->foreign('devolucion_proveedor_detalle_id', 'fk_dev_prov_serie_detalle')
                ->references('id')
                ->on('devolucion_proveedor_detalles')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devolucion_proveedor_series');
        Schema::dropIfExists('devolucion_proveedor_detalles');
        Schema::dropIfExists('devoluciones_proveedor');
    }
};
