<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventario_ajustes_rapidos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('sucursal_id')->constrained('sucursales');
            $table->foreignId('user_id')->constrained('users');
            $table->string('folio', 40);
            $table->enum('tipo', ['entrada', 'salida', 'mixto']);
            $table->string('motivo', 160);
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'folio']);
            $table->index(['empresa_id', 'sucursal_id', 'created_at'], 'idx_inv_ajuste_scope');
        });

        Schema::create('inventario_ajuste_rapido_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ajuste_id')->constrained('inventario_ajustes_rapidos')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos');
            $table->foreignId('variante_id')->nullable()->constrained('producto_variantes')->nullOnDelete();
            $table->decimal('cantidad', 12, 3);
            $table->decimal('stock_antes', 12, 3);
            $table->decimal('stock_despues', 12, 3);
            $table->json('serie_ids')->nullable();
            $table->timestamps();

            $table->unique(['ajuste_id', 'producto_id', 'variante_id'], 'uq_inv_ajuste_item');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE series MODIFY estado ENUM('disponible','vendido','apartado','devuelto','baja') NOT NULL DEFAULT 'disponible'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE series MODIFY estado ENUM('disponible','vendido','apartado','devuelto') NOT NULL DEFAULT 'disponible'");
        }
        Schema::dropIfExists('inventario_ajuste_rapido_detalles');
        Schema::dropIfExists('inventario_ajustes_rapidos');
    }
};
