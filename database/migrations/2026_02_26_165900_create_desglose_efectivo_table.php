<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('corte_desglose_efectivo', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('corte_id')->unique();

            $table->unsignedInteger('billetes_1000')->default(0);
            $table->unsignedInteger('billetes_500')->default(0);
            $table->unsignedInteger('billetes_200')->default(0);
            $table->unsignedInteger('billetes_100')->default(0);
            $table->unsignedInteger('billetes_50')->default(0);
            $table->unsignedInteger('billetes_20')->default(0);

            $table->unsignedInteger('monedas_20')->default(0);
            $table->unsignedInteger('monedas_10')->default(0);
            $table->unsignedInteger('monedas_5')->default(0);
            $table->unsignedInteger('monedas_2')->default(0);
            $table->unsignedInteger('monedas_1')->default(0);
            $table->unsignedInteger('monedas_050')->default(0);

            $table->decimal('total_calculado', 12, 2)->default(0);

            $table->timestamps();

            $table->foreign('corte_id')->references('id')->on('cortes_caja')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corte_desglose_efectivo');
    }
};