<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cliente_saldo_movimientos', function (Blueprint $table) {
            $table->enum('alcance', ['legacy', 'general', 'pedido'])->default('legacy')->after('tipo')->index();
            $table->foreignId('movimiento_origen_id')->nullable()->after('movimiento_caja_id')
                ->constrained('cliente_saldo_movimientos')->nullOnDelete();
            $table->json('metadata')->nullable()->after('concepto');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE cliente_saldo_movimientos MODIFY tipo ENUM('abono','aplicacion','devolucion','ajuste','ajuste_credito','ajuste_debito','reverso_credito','reverso_aplicacion') NOT NULL");
        }
    }

    public function down(): void
    {
        DB::table('cliente_saldo_movimientos')->whereIn('tipo', [
            'ajuste_credito', 'ajuste_debito', 'reverso_credito', 'reverso_aplicacion',
        ])->delete();
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE cliente_saldo_movimientos MODIFY tipo ENUM('abono','aplicacion','devolucion','ajuste') NOT NULL");
        }
        Schema::table('cliente_saldo_movimientos', function (Blueprint $table) {
            $table->dropForeign(['movimiento_origen_id']);
            $table->dropColumn(['alcance', 'movimiento_origen_id', 'metadata']);
        });
    }
};
