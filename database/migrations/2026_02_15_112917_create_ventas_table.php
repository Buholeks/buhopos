<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('sucursal_id')->constrained('sucursales');
            $table->foreignId('user_id')->constrained('users');

            $table->string('folio', 100)->nullable();
            $table->date('fecha');
            $table->enum('forma_pago', ['efectivo', 'credito', 'transferencia', 'tarjeta'])->default('efectivo');

            $table->decimal('subtotal',  14, 2)->default(0);
            $table->decimal('descuento', 14, 2)->default(0); // monto absoluto
            $table->decimal('total',     14, 2)->default(0);

            $table->text('notas')->nullable();
            $table->enum('estado', ['borrador', 'confirmada', 'cancelada'])->default('confirmada');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['empresa_id', 'fecha'],  'idx_ventas_empresa_fecha');
            $table->index(['empresa_id', 'estado'], 'idx_ventas_empresa_estado');
        });

        Schema::create('venta_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos');
            $table->foreignId('variante_id')->nullable()->constrained('producto_variantes')->nullOnDelete();

            $table->decimal('cantidad',      10, 3);
            $table->decimal('precio_venta',  12, 2);   // precio unitario en el momento de la venta
            $table->decimal('precio_costo',  12, 2)->nullable(); // snapshot para calcular margen
            $table->decimal('descuento',     12, 2)->default(0); // descuento por línea
            $table->decimal('subtotal',      14, 2);   // (cantidad * precio_venta) - descuento

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venta_detalles');
        Schema::dropIfExists('ventas');
    }
};