<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comision_liquidaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');          // empleado al que se le paga
            $table->date('desde');
            $table->date('hasta');
            $table->decimal('total', 14, 2)->default(0);
            $table->string('estado', 20)->default('borrador');           // borrador | pagada | cancelada

            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('pagado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('pagado_en')->nullable();
            $table->foreignId('movimiento_caja_id')->nullable()->constrained('movimientos_caja')->nullOnDelete();
            $table->foreignId('cancelado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('cancelado_en')->nullable();

            $table->text('notas')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'user_id', 'estado'], 'idx_comision_liq_empresa_user_estado');
        });

        // Libro de comisiones: cada fila es una comisión calculada (positiva) o una
        // reversión (negativa). Guarda snapshot de la regla para no depender de cambios futuros.
        Schema::create('comision_movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users');          // beneficiario
            $table->string('beneficiario', 20);                          // vendedor | cajero
            $table->string('tipo', 20);                                  // venta | cancelacion | devolucion | ajuste
            $table->date('fecha');

            $table->foreignId('venta_id')->nullable()->constrained('ventas')->nullOnDelete();
            $table->foreignId('venta_detalle_id')->nullable()->constrained('venta_detalles')->nullOnDelete();
            $table->foreignId('devolucion_id')->nullable()->constrained('devoluciones')->nullOnDelete();

            // Snapshot de cómo se calculó
            $table->foreignId('esquema_id')->nullable()->constrained('comision_esquemas')->nullOnDelete();
            $table->foreignId('regla_id')->nullable()->constrained('comision_reglas')->nullOnDelete();
            $table->string('lista_precio', 20)->nullable();
            $table->string('base', 20)->nullable();                      // venta | utilidad
            $table->string('regla_tipo', 20)->nullable();                // porcentaje | fijo_por_pieza
            $table->decimal('cantidad', 10, 3)->default(0);
            $table->decimal('base_calculo', 14, 2)->default(0);
            $table->decimal('tasa', 12, 4)->default(0);
            $table->decimal('monto', 14, 2);                             // con signo

            $table->string('concepto', 255)->nullable();                 // para ajustes manuales
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('liquidacion_id')->nullable()->constrained('comision_liquidaciones')->nullOnDelete();
            $table->timestamps();

            $table->index(['empresa_id', 'user_id', 'liquidacion_id'], 'idx_comision_mov_pendientes');
            $table->index(['empresa_id', 'fecha'], 'idx_comision_mov_empresa_fecha');
            $table->index(['venta_id', 'tipo'], 'idx_comision_mov_venta_tipo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comision_movimientos');
        Schema::dropIfExists('comision_liquidaciones');
    }
};
