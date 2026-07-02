<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kardex_movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();
            $table->foreignId('producto_id')->constrained('productos');
            $table->foreignId('variante_id')->nullable()->constrained('producto_variantes')->nullOnDelete();
            $table->foreignId('serie_id')->nullable()->constrained('series')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('tipo', 50);
            $table->string('direccion', 10);
            $table->decimal('cantidad', 12, 3)->default(0);
            $table->decimal('entrada', 12, 3)->default(0);
            $table->decimal('salida', 12, 3)->default(0);
            $table->decimal('stock_antes', 12, 3)->default(0);
            $table->decimal('stock_despues', 12, 3)->default(0);

            $table->decimal('costo_unitario', 14, 4)->nullable();
            $table->decimal('precio_unitario', 14, 4)->nullable();
            $table->decimal('importe', 14, 2)->nullable();

            $table->string('referencia_tipo', 60)->nullable();
            $table->unsignedBigInteger('referencia_id')->nullable();
            $table->unsignedBigInteger('referencia_detalle_id')->nullable();
            $table->string('folio', 100)->nullable();
            $table->string('motivo', 160)->nullable();
            $table->text('notas')->nullable();
            $table->json('metadata')->nullable();
            $table->dateTime('fecha');
            $table->timestamps();

            $table->index(['empresa_id', 'sucursal_id', 'producto_id', 'variante_id', 'fecha'], 'idx_kardex_producto_fecha');
            $table->index(['empresa_id', 'sucursal_id', 'fecha'], 'idx_kardex_sucursal_fecha');
            $table->index(['referencia_tipo', 'referencia_id'], 'idx_kardex_referencia');
            $table->index(['producto_id', 'variante_id'], 'idx_kardex_producto_variante');
            $table->index(['serie_id'], 'idx_kardex_serie');
            $table->index(['tipo', 'direccion'], 'idx_kardex_tipo_direccion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kardex_movimientos');
    }
};
