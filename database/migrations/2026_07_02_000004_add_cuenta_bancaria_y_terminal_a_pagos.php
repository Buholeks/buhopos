<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->foreignId('cuenta_bancaria_id')->nullable()->after('forma_pago')
                ->constrained('cuentas_bancarias')->nullOnDelete();
            $table->foreignId('terminal_pago_id')->nullable()->after('cuenta_bancaria_id')
                ->constrained('terminales_pago')->nullOnDelete();
        });

        Schema::table('movimientos_caja', function (Blueprint $table) {
            $table->foreignId('cuenta_bancaria_id')->nullable()->after('forma_pago')
                ->constrained('cuentas_bancarias')->nullOnDelete();
            $table->foreignId('terminal_pago_id')->nullable()->after('cuenta_bancaria_id')
                ->constrained('terminales_pago')->nullOnDelete();
        });

        Schema::table('pagos_proveedor', function (Blueprint $table) {
            $table->foreignId('cuenta_bancaria_id')->nullable()->after('forma_pago')
                ->constrained('cuentas_bancarias')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cuenta_bancaria_id');
            $table->dropConstrainedForeignId('terminal_pago_id');
        });

        Schema::table('movimientos_caja', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cuenta_bancaria_id');
            $table->dropConstrainedForeignId('terminal_pago_id');
        });

        Schema::table('pagos_proveedor', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cuenta_bancaria_id');
        });
    }
};
