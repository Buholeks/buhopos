<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cliente_saldo_movimientos', function (Blueprint $table) {
            $table->unsignedBigInteger('movimiento_caja_id')->nullable()->after('corte_id');
            $table->foreign('movimiento_caja_id')
                  ->references('id')->on('movimientos_caja')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cliente_saldo_movimientos', function (Blueprint $table) {
            $table->dropForeign(['movimiento_caja_id']);
            $table->dropColumn('movimiento_caja_id');
        });
    }
};
