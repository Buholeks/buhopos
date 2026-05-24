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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('abonos_proveedor');
    }
};
