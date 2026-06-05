<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedido_detalles', function (Blueprint $table) {
            $table->foreignId('compra_detalle_id')
                ->nullable()
                ->after('variante_id')
                ->constrained('compra_detalles')
                ->nullOnDelete();

            $table->index(['producto_id', 'variante_id', 'estado'], 'idx_pedido_detalles_producto_estado');
        });
    }

    public function down(): void
    {
        Schema::table('pedido_detalles', function (Blueprint $table) {
            $table->dropForeign(['compra_detalle_id']);
            $table->dropIndex('idx_pedido_detalles_producto_estado');
            $table->dropColumn('compra_detalle_id');
        });
    }
};
