<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suscripciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->unique()->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('planes')->nullOnDelete();
            $table->enum('estado', ['pendiente', 'prueba', 'activa', 'vencida', 'suspendida', 'cancelada'])->default('pendiente');
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_vencimiento')->nullable();
            $table->unsignedSmallInteger('dias_gracia')->default(3);
            $table->decimal('precio_acordado', 12, 2)->nullable();
            $table->text('notas')->nullable();
            $table->timestamp('cancelada_en')->nullable();
            $table->timestamps();
            $table->index(['estado', 'fecha_vencimiento']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suscripciones');
    }
};
