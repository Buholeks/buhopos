<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Agregar columnas a compras ────────────────────────────────────
        Schema::table('compras', function (Blueprint $table) {
            $table->decimal('pagado', 14, 2)->default(0)->after('total');
            $table->decimal('saldo',  14, 2)->default(0)->after('pagado');
        });

        // ── 2. Poblar saldo en compras existentes ────────────────────────────
        // Crédito sin pagar → saldo = total
        DB::statement("
            UPDATE compras
            SET pagado = 0,
                saldo  = total
            WHERE forma_pago = 'credito'
        ");

        // Efectivo → ya pagado, saldo = 0
        DB::statement("
            UPDATE compras
            SET pagado = total,
                saldo  = 0
            WHERE forma_pago = 'efectivo'
        ");

        // ── 3. Crear tabla pagos_proveedor ───────────────────────────────────
        Schema::create('pagos_proveedor', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('sucursal_id');
            $table->unsignedBigInteger('compra_id');
            $table->unsignedBigInteger('user_id');
            $table->decimal('monto', 14, 2);
            $table->date('fecha_pago');
            $table->enum('forma_pago', ['efectivo', 'transferencia', 'cheque', 'tarjeta'])->default('efectivo');
            $table->string('referencia', 100)->nullable();
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->foreign('compra_id')->references('id')->on('compras')->onDelete('cascade');
            $table->index(['empresa_id', 'sucursal_id']);
            $table->index('compra_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos_proveedor');

        Schema::table('compras', function (Blueprint $table) {
            $table->dropColumn(['pagado', 'saldo']);
        });
    }
};