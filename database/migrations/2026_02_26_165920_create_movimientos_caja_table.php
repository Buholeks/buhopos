<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('movimientos_caja', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('corte_id');
            $table->unsignedBigInteger('user_id');

            $table->enum('tipo', ['ingreso','egreso']);
            $table->enum('forma_pago', ['efectivo','tarjeta','transferencia']);
            $table->decimal('monto', 12, 2);

            $table->string('concepto', 255);
            $table->timestamps();

            $table->index(['corte_id','forma_pago']);
            $table->foreign('corte_id')->references('id')->on('cortes_caja')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_caja');
    }
};