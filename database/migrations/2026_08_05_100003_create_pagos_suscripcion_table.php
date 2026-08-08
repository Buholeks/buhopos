<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos_suscripcion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('suscripcion_id')->constrained('suscripciones')->cascadeOnDelete();
            $table->foreignId('registrado_por')->nullable()->constrained('plataforma_administradores')->nullOnDelete();
            $table->decimal('importe', 12, 2);
            $table->date('fecha_pago');
            $table->date('periodo_inicio');
            $table->date('periodo_fin');
            $table->string('metodo', 50)->default('transferencia');
            $table->string('referencia')->nullable();
            $table->enum('estado', ['pendiente', 'confirmado', 'rechazado', 'reembolsado'])->default('confirmado');
            $table->text('notas')->nullable();
            $table->timestamps();
            $table->index(['fecha_pago', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos_suscripcion');
    }
};
