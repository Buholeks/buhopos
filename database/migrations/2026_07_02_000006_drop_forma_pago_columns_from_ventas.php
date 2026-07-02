<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cuenta_bancaria_id');
            $table->dropConstrainedForeignId('terminal_pago_id');
            $table->dropColumn(['forma_pago', 'monto_recibido', 'cambio']);
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->enum('forma_pago', ['efectivo', 'credito', 'transferencia', 'tarjeta'])->default('efectivo')->after('fecha');
            $table->foreignId('cuenta_bancaria_id')->nullable()->after('forma_pago')->constrained('cuentas_bancarias')->nullOnDelete();
            $table->foreignId('terminal_pago_id')->nullable()->after('cuenta_bancaria_id')->constrained('terminales_pago')->nullOnDelete();
            $table->decimal('monto_recibido', 12, 2)->nullable()->after('total');
            $table->decimal('cambio', 12, 2)->default(0)->after('monto_recibido');
        });
    }
};
