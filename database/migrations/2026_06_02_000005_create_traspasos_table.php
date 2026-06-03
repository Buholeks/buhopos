<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('traspasos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('origen_sucursal_id')->constrained('sucursales');
            $table->foreignId('destino_sucursal_id')->constrained('sucursales');
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('cancelado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('folio', 40);
            $table->enum('estado', ['completado', 'cancelado'])->default('completado');
            $table->decimal('total_items', 12, 3)->default(0);
            $table->text('notas')->nullable();
            $table->timestamp('cancelado_at')->nullable();
            $table->text('motivo_cancelacion')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'folio']);
            $table->index(['empresa_id', 'origen_sucursal_id', 'estado']);
            $table->index(['empresa_id', 'destino_sucursal_id', 'estado']);
            $table->index(['created_at']);
        });

        Schema::create('traspaso_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('traspaso_id')->constrained('traspasos')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos');
            $table->foreignId('variante_id')->nullable()->constrained('producto_variantes')->nullOnDelete();
            $table->foreignId('serie_id')->nullable()->constrained('series')->nullOnDelete();
            $table->string('producto_nombre');
            $table->string('variante_nombre')->nullable();
            $table->string('serie_identificador')->nullable();
            $table->decimal('cantidad', 12, 3);
            $table->timestamps();

            $table->index(['producto_id', 'variante_id']);
            $table->index('serie_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('traspaso_detalles');
        Schema::dropIfExists('traspasos');
    }
};
