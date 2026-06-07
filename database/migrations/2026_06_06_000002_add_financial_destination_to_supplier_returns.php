<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE compras MODIFY estado ENUM('borrador','confirmada','devuelta_parcial','devuelta','cancelada') NOT NULL DEFAULT 'borrador'");

        Schema::table('devoluciones_proveedor', function (Blueprint $table) {
            $table->enum('destino_excedente', ['saldo_favor', 'caja'])->nullable()->after('reembolso_pendiente');
            $table->enum('forma_reembolso', ['efectivo', 'transferencia'])->nullable()->after('destino_excedente');
            $table->unsignedBigInteger('movimiento_caja_id')->nullable()->after('forma_reembolso');
            $table->foreign('movimiento_caja_id', 'fk_dev_prov_mov_caja')
                ->references('id')->on('movimientos_caja')->nullOnDelete();
        });

        Schema::create('proveedor_saldo_movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('sucursal_id')->constrained('sucursales');
            $table->foreignId('proveedor_id')->constrained('proveedores');
            $table->foreignId('user_id')->constrained('users');
            $table->unsignedBigInteger('devolucion_proveedor_id')->nullable();
            $table->foreignId('compra_id')->nullable()->constrained('compras')->nullOnDelete();
            $table->enum('tipo', ['credito', 'aplicacion', 'ajuste']);
            $table->decimal('monto', 14, 2);
            $table->decimal('saldo_resultante', 14, 2);
            $table->string('concepto');
            $table->timestamps();
            $table->index(['empresa_id', 'sucursal_id', 'proveedor_id'], 'idx_proveedor_saldo_tenant');
            $table->foreign('devolucion_proveedor_id', 'fk_proveedor_saldo_devolucion')
                ->references('id')->on('devoluciones_proveedor')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proveedor_saldo_movimientos');
        Schema::table('devoluciones_proveedor', function (Blueprint $table) {
            $table->dropForeign('fk_dev_prov_mov_caja');
            $table->dropColumn('movimiento_caja_id');
            $table->dropColumn(['destino_excedente', 'forma_reembolso']);
        });
        DB::statement("ALTER TABLE compras MODIFY estado ENUM('borrador','confirmada','cancelada') NOT NULL DEFAULT 'borrador'");
    }
};
