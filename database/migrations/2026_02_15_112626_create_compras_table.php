<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('sucursal_id')->constrained('sucursales');
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores')->nullOnDelete();

            $table->string('folio', 100)->nullable();          // Nº factura del proveedor
            $table->date('fecha');                             // Fecha de compra
            $table->enum('forma_pago', ['efectivo', 'credito', 'transferencia', 'tarjeta_debito', 'tarjeta_credito'])->default('efectivo');
            $table->date('fecha_vencimiento')->nullable();     // Para compras a crédito
            $table->text('notas')->nullable();

            // Totales calculados
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('total',    14, 2)->default(0);

            $table->enum('estado', ['borrador', 'confirmada', 'cancelada'])->default('borrador');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['empresa_id', 'fecha'],   'idx_compras_empresa_fecha');
            $table->index(['empresa_id', 'estado'],  'idx_compras_empresa_estado');
            $table->index(['proveedor_id'],           'idx_compras_proveedor');
        });

        Schema::create('compra_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compra_id')->constrained('compras')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos');
            $table->foreignId('variante_id')->nullable()->constrained('producto_variantes')->nullOnDelete();

            $table->decimal('cantidad',       10, 3);
            $table->decimal('precio_compra',  12, 2);           // Costo unitario en esta compra
            $table->decimal('precio_venta',   12, 2)->nullable(); // Precio venta a actualizar
            $table->decimal('subtotal',       14, 2);           // cantidad * precio_compra

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compra_detalles');
        Schema::dropIfExists('compras');
    }
};