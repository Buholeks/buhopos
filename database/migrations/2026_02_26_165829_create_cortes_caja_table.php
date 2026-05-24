<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cortes_caja', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('sucursal_id');
            $table->unsignedBigInteger('user_id'); // cajero que abrió/cerró
            $table->string('terminal', 60)->nullable(); // opcional: PC/caja/host

            $table->enum('estado', ['abierto','cerrado','anulado'])->default('abierto');

            $table->timestamp('fecha_apertura');
            $table->timestamp('fecha_cierre')->nullable();

            // Apertura de efectivo (fondo)
            $table->decimal('fondo_inicial_efectivo', 12, 2)->default(0);

            // Totales por ventas (calculados)
            $table->decimal('ventas_efectivo', 12, 2)->default(0);
            $table->decimal('ventas_tarjeta', 12, 2)->default(0);
            $table->decimal('ventas_transferencia', 12, 2)->default(0);
            $table->decimal('ventas_credito', 12, 2)->default(0);
            $table->unsignedInteger('num_ventas')->default(0);

            // Movimientos extra (calculados)
            $table->decimal('movs_efectivo', 12, 2)->default(0);
            $table->decimal('movs_tarjeta', 12, 2)->default(0);
            $table->decimal('movs_transferencia', 12, 2)->default(0);

            // Esperado (calculado)
            $table->decimal('esperado_efectivo', 12, 2)->default(0);
            $table->decimal('esperado_tarjeta', 12, 2)->default(0);
            $table->decimal('esperado_transferencia', 12, 2)->default(0);

            // Contado (capturado al cierre)
            $table->decimal('contado_efectivo', 12, 2)->default(0);
            $table->decimal('contado_tarjeta', 12, 2)->default(0);
            $table->decimal('contado_transferencia', 12, 2)->default(0);

            // Diferencias (calculadas)
            $table->decimal('dif_efectivo', 12, 2)->default(0);
            $table->decimal('dif_tarjeta', 12, 2)->default(0);
            $table->decimal('dif_transferencia', 12, 2)->default(0);

            $table->text('notas_apertura')->nullable();
            $table->text('notas_cierre')->nullable();

            $table->timestamps();

            $table->index(['empresa_id','sucursal_id','estado']);
            $table->index(['empresa_id','sucursal_id','fecha_apertura']);

            // Regla práctica: 1 corte abierto por empresa+sucursal (+terminal si quieres)
            // En MySQL no hay partial unique, se resuelve con lógica en backend + index.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cortes_caja');
    }
};