<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venta_detalles', function (Blueprint $table) {
            $table->foreignId('pedido_id')
                ->nullable()
                ->after('venta_id')
                ->constrained('pedidos')
                ->nullOnDelete();

            $table->foreignId('pedido_detalle_id')
                ->nullable()
                ->after('pedido_id')
                ->constrained('pedido_detalles')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('venta_detalles', function (Blueprint $table) {
            $table->dropForeign(['pedido_detalle_id']);
            $table->dropForeign(['pedido_id']);
            $table->dropColumn(['pedido_detalle_id', 'pedido_id']);
        });
    }
};
