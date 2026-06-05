<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedidos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->foreignId('venta_id')->nullable()->constrained('ventas')->nullOnDelete();
            $table->string('folio', 40)->index();
            $table->enum('tipo', ['pedido', 'apartado'])->default('pedido')->index();
            $table->enum('estado', ['pendiente', 'en_proceso', 'disponible', 'parcial', 'entregado', 'cancelado', 'vencido'])->default('pendiente')->index();
            $table->enum('estado_pago', ['sin_anticipo', 'con_anticipo', 'pagado', 'saldo_pendiente'])->default('sin_anticipo')->index();
            $table->date('fecha_promesa')->nullable()->index();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('anticipo', 14, 2)->default(0);
            $table->decimal('saldo_pendiente', 14, 2)->default(0);
            $table->text('notas')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['empresa_id', 'sucursal_id', 'folio']);
            $table->index(['empresa_id', 'sucursal_id', 'tipo', 'estado']);
        });

        Schema::create('pedido_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete();
            $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
            $table->foreignId('variante_id')->nullable()->constrained('producto_variantes')->nullOnDelete();
            $table->string('descripcion', 255);
            $table->string('marca_texto', 120)->nullable();
            $table->string('modelo_texto', 120)->nullable();
            $table->string('color_texto', 80)->nullable();
            $table->string('talla_texto', 80)->nullable();
            $table->unsignedInteger('cantidad')->default(1);
            $table->decimal('precio_acordado', 14, 2)->default(0);
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->enum('estado', ['pendiente', 'disponible', 'reservado', 'entregado', 'cancelado'])->default('pendiente')->index();
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->index(['producto_id', 'variante_id']);
        });

        Schema::create('inventario_reservas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete();
            $table->foreignId('pedido_detalle_id')->constrained('pedido_detalles')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->foreignId('variante_id')->nullable()->constrained('producto_variantes')->nullOnDelete();
            $table->decimal('cantidad', 14, 2);
            $table->enum('estado', ['activa', 'liberada', 'consumida'])->default('activa')->index();
            $table->timestamps();

            $table->index(['empresa_id', 'sucursal_id', 'producto_id', 'variante_id', 'estado'], 'idx_reservas_stock');
        });

        Schema::create('cliente_saldo_movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->foreignId('pedido_id')->nullable()->constrained('pedidos')->nullOnDelete();
            $table->foreignId('venta_id')->nullable()->constrained('ventas')->nullOnDelete();
            $table->foreignId('corte_id')->nullable()->constrained('cortes_caja')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('tipo', ['abono', 'aplicacion', 'devolucion', 'ajuste'])->index();
            $table->enum('forma_pago', ['efectivo', 'tarjeta', 'transferencia', 'credito', 'saldo_favor'])->nullable();
            $table->decimal('monto', 14, 2);
            $table->decimal('saldo_resultante', 14, 2)->default(0);
            $table->string('concepto', 255)->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'sucursal_id', 'cliente_id'], 'idx_cliente_saldo_tenant');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cliente_saldo_movimientos');
        Schema::dropIfExists('inventario_reservas');
        Schema::dropIfExists('pedido_detalles');
        Schema::dropIfExists('pedidos');
    }
};
