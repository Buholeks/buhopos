<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devoluciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('sucursal_id')->constrained('sucursales');
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('corte_id')->nullable()->constrained('cortes_caja')->nullOnDelete();
            $table->string('folio', 100)->nullable();
            $table->dateTime('fecha');
            $table->enum('forma_devolucion', ['efectivo', 'tarjeta', 'transferencia', 'credito'])->default('efectivo');
            $table->decimal('total_devuelto', 14, 2)->default(0);
            $table->boolean('regresa_inventario')->default(true);
            $table->string('motivo', 255);
            $table->enum('estado', ['confirmada', 'cancelada'])->default('confirmada');
            $table->timestamps();

            $table->index(['empresa_id', 'sucursal_id', 'fecha']);
            $table->index(['venta_id', 'estado']);
        });

        Schema::create('devolucion_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('devolucion_id')->constrained('devoluciones')->cascadeOnDelete();
            $table->foreignId('venta_detalle_id')->constrained('venta_detalles')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos');
            $table->foreignId('variante_id')->nullable()->constrained('producto_variantes')->nullOnDelete();
            $table->foreignId('serie_id')->nullable()->constrained('series')->nullOnDelete();
            $table->decimal('cantidad', 10, 3);
            $table->decimal('precio_unitario', 12, 2);
            $table->decimal('importe', 14, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devolucion_detalles');
        Schema::dropIfExists('devoluciones');
    }
};
