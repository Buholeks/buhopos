<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venta_pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();

            $table->enum('forma_pago', ['efectivo', 'tarjeta', 'transferencia', 'saldo_favor']);
            $table->decimal('monto', 14, 2);

            $table->foreignId('cuenta_bancaria_id')->nullable()->constrained('cuentas_bancarias')->nullOnDelete();
            $table->foreignId('terminal_pago_id')->nullable()->constrained('terminales_pago')->nullOnDelete();

            $table->decimal('monto_recibido', 12, 2)->nullable();
            $table->decimal('cambio', 12, 2)->nullable();

            $table->timestamps();

            $table->index(['venta_id', 'forma_pago']);
        });

        // Backfill: cada venta ya creada obtiene su(s) línea(s) equivalente(s) a partir
        // de sus columnas actuales, para que venta_pagos sea la única fuente de verdad
        // también para ventas viejas (cancelaciones, reportes, corte de caja).
        DB::table('ventas')
            ->orderBy('id')
            ->chunkById(500, function ($ventas) {
                $filas = [];
                $ahora = now();

                foreach ($ventas as $venta) {
                    $saldoAplicado = round((float) ($venta->saldo_aplicado ?? 0), 2);

                    if (in_array($venta->forma_pago, ['efectivo', 'tarjeta', 'transferencia'], true)) {
                        $monto = round(max(0, (float) $venta->total - $saldoAplicado), 2);

                        if ($monto > 0) {
                            $filas[] = [
                                'venta_id'           => $venta->id,
                                'forma_pago'          => $venta->forma_pago,
                                'monto'               => $monto,
                                'cuenta_bancaria_id'  => $venta->cuenta_bancaria_id,
                                'terminal_pago_id'    => $venta->terminal_pago_id,
                                'monto_recibido'      => $venta->forma_pago === 'efectivo' ? $venta->monto_recibido : null,
                                'cambio'              => $venta->forma_pago === 'efectivo' ? $venta->cambio : null,
                                'created_at'          => $ahora,
                                'updated_at'          => $ahora,
                            ];
                        }
                    }

                    if ($saldoAplicado > 0) {
                        $filas[] = [
                            'venta_id'           => $venta->id,
                            'forma_pago'          => 'saldo_favor',
                            'monto'               => $saldoAplicado,
                            'cuenta_bancaria_id'  => null,
                            'terminal_pago_id'    => null,
                            'monto_recibido'      => null,
                            'cambio'              => null,
                            'created_at'          => $ahora,
                            'updated_at'          => $ahora,
                        ];
                    }
                }

                if (!empty($filas)) {
                    DB::table('venta_pagos')->insert($filas);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('venta_pagos');
    }
};
