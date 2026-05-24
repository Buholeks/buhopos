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
        Schema::create('compra_detalles_proveedor', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('compra_id')->index();
            $table->unsignedBigInteger('producto_id')->nullable()->index();

            $table->string('descripcion')->nullable(); // por si no usas catálogo
            $table->decimal('cantidad', 12, 2)->default(1);
            $table->decimal('costo_unitario', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);

            $table->timestamps();

            $table->foreign('compra_id')->references('id')->on('compras_proveedor')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compra_detalles_proveedor');
    }
};
