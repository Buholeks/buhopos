<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE proveedor_saldo_movimientos MODIFY tipo ENUM('credito','aplicacion','ajuste','ajuste_credito','ajuste_debito','reverso_credito','reverso_aplicacion') NOT NULL");
    }

    public function down(): void
    {
        DB::table('proveedor_saldo_movimientos')
            ->whereIn('tipo', ['ajuste_credito', 'ajuste_debito', 'reverso_credito', 'reverso_aplicacion'])
            ->delete();
        DB::statement("ALTER TABLE proveedor_saldo_movimientos MODIFY tipo ENUM('credito','aplicacion','ajuste') NOT NULL");
    }
};
