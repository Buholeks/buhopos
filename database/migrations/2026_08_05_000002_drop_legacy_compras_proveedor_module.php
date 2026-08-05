<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Elimina el módulo "compras_proveedor / abonos_proveedor": quedó huérfano desde que
// CompraController + PagoProveedorController + ProveedorSaldoMovimiento cubrieron
// compras a proveedor, pagos y saldo a favor. Nunca se consumió desde el frontend
// y las tres tablas estaban vacías al momento de retirarlo.
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('abonos_proveedor');
        Schema::dropIfExists('compra_detalles_proveedor');
        Schema::dropIfExists('compras_proveedor');
    }

    public function down(): void
    {
        Schema::create('compras_proveedor', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('empresa_id')->index();
            $table->unsignedBigInteger('sucursal_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('proveedor_id')->index();
            $table->string('folio', 50)->nullable()->index();
            $table->date('fecha_compra')->index();
            $table->string('tipo_pago', 10)->index();
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('pagado_inicial', 12, 2)->default(0);
            $table->decimal('saldo', 12, 2)->default(0)->index();
            $table->date('fecha_vencimiento')->nullable()->index();
            $table->string('estatus', 12)->default('PENDIENTE')->index();
            $table->text('observaciones')->nullable();
            $table->foreign('proveedor_id')->references('id')->on('proveedores');
            $table->foreign('user_id')->references('id')->on('users');
            $table->timestamps();
        });

        Schema::create('compra_detalles_proveedor', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('compra_id')->index();
            $table->unsignedBigInteger('producto_id')->nullable()->index();

            $table->string('descripcion')->nullable();
            $table->decimal('cantidad', 12, 2)->default(1);
            $table->decimal('costo_unitario', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);

            $table->timestamps();

            $table->foreign('compra_id')->references('id')->on('compras_proveedor')->onDelete('cascade');
        });

        Schema::create('abonos_proveedor', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->index();
            $table->unsignedBigInteger('sucursal_id')->index();
            $table->unsignedBigInteger('user_id')->index();

            $table->unsignedBigInteger('proveedor_id')->index();
            $table->unsignedBigInteger('compra_id')->index();

            $table->date('fecha')->index();
            $table->decimal('monto', 12, 2);

            $table->string('metodo_pago', 50)->nullable();
            $table->string('referencia', 100)->nullable();
            $table->text('nota')->nullable();

            $table->timestamps();

            $table->foreign('proveedor_id')->references('id')->on('proveedores');
            $table->foreign('compra_id')->references('id')->on('compras_proveedor')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users');
        });
    }
};
