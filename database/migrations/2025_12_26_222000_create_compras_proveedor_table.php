<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('compras_proveedor', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('empresa_id')->index();
            $table->unsignedBigInteger('sucursal_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('proveedor_id')->index();
            $table->string('folio', 50)->nullable()->index();
            $table->date('fecha_compra')->index();
            // CONTADO | CREDITO | MIXTO
            $table->string('tipo_pago', 10)->index();
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('pagado_inicial', 12, 2)->default(0);
            // saldo de esta compra (lo pendiente)
            $table->decimal('saldo', 12, 2)->default(0)->index();
            $table->date('fecha_vencimiento')->nullable()->index();
            // PENDIENTE | PARCIAL | PAGADO | VENCIDO
            $table->string('estatus', 12)->default('PENDIENTE')->index();
            $table->text('observaciones')->nullable();
            // FK (ajusta nombres de tablas reales)
            $table->foreign('proveedor_id')->references('id')->on('proveedores');
            $table->foreign('user_id')->references('id')->on('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compras_proveedor');
    }
};
