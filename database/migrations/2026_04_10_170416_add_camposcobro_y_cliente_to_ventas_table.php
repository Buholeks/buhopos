<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            if (!Schema::hasColumn('ventas', 'cliente_id')) {
                $table->unsignedBigInteger('cliente_id')
                    ->nullable()
                    ->after('user_id');
            }

            if (!Schema::hasColumn('ventas', 'vendedor_id')) {
                $table->unsignedBigInteger('vendedor_id')
                    ->nullable()
                    ->after('cliente_id');
            }

            if (!Schema::hasColumn('ventas', 'monto_recibido')) {
                $table->decimal('monto_recibido', 12, 2)
                    ->nullable()
                    ->after('total');
            }

            if (!Schema::hasColumn('ventas', 'cambio')) {
                $table->decimal('cambio', 12, 2)
                    ->default(0)
                    ->after('monto_recibido');
            }

            if (!Schema::hasColumn('ventas', 'metodo_cobro_detalle')) {
                $table->string('metodo_cobro_detalle', 120)
                    ->nullable()
                    ->after('forma_pago');
            }

            $table->foreign('cliente_id')
                ->references('id')
                ->on('clientes')
                ->nullOnDelete();

            $table->foreign('vendedor_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            if (Schema::hasColumn('ventas', 'cliente_id')) {
                $table->dropForeign(['cliente_id']);
            }

            if (Schema::hasColumn('ventas', 'vendedor_id')) {
                $table->dropForeign(['vendedor_id']);
            }

            if (Schema::hasColumn('ventas', 'cliente_id')) {
                $table->dropColumn('cliente_id');
            }

            if (Schema::hasColumn('ventas', 'vendedor_id')) {
                $table->dropColumn('vendedor_id');
            }

            if (Schema::hasColumn('ventas', 'monto_recibido')) {
                $table->dropColumn('monto_recibido');
            }

            if (Schema::hasColumn('ventas', 'cambio')) {
                $table->dropColumn('cambio');
            }

            if (Schema::hasColumn('ventas', 'metodo_cobro_detalle')) {
                $table->dropColumn('metodo_cobro_detalle');
            }
        });
    }
};