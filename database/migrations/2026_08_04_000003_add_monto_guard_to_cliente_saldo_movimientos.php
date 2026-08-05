<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // El signo del saldo lo decide siempre la columna `tipo` (ver
        // ClienteSaldoMovimiento::TIPOS_CREDITO / expresionSaldo()); `monto` debe ser
        // siempre positivo. Esto era solo una convención de la capa de aplicación —
        // aquí queda como defensa a nivel de base de datos.
        Schema::table('cliente_saldo_movimientos', function (Blueprint $table) {
            $table->decimal('monto', 14, 2)->unsigned()->change();
        });

        DB::statement('ALTER TABLE cliente_saldo_movimientos ADD CONSTRAINT chk_cliente_saldo_monto_positivo CHECK (monto > 0)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE cliente_saldo_movimientos DROP CHECK chk_cliente_saldo_monto_positivo');

        Schema::table('cliente_saldo_movimientos', function (Blueprint $table) {
            $table->decimal('monto', 14, 2)->change();
        });
    }
};
