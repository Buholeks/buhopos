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
        Schema::create('proveedores', function (Blueprint $table) {
            $table->id();
            // Multi-tenant automático
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('sucursal_id')->constrained('sucursales');
            $table->foreignId('user_id')->constrained('users');

            // Datos básicos
            $table->string('nombre_comercial');
            $table->string('razon_social')->nullable();
            $table->string('rfc', 13)->nullable();

            // Contacto
            $table->string('telefono')->nullable();
            $table->string('email')->nullable();
            $table->string('contacto')->nullable(); // persona de contacto

            // Dirección
            $table->string('calle')->nullable();
            $table->string('numero')->nullable();
            $table->string('colonia')->nullable();
            $table->string('ciudad')->nullable();
            $table->string('estado')->nullable();
            $table->string('cp', 10)->nullable();

            // Información adicional
            $table->string('sitio_web')->nullable();
            $table->boolean('activo')->default(true);

            $table->boolean('credito_activo')->default(false);
            $table->unsignedInteger('dias_credito_default')->default(0);
            $table->decimal('limite_credito', 12, 2)->nullable();

            // “cache” opcional (útil para dashboard rápido)
            $table->decimal('saldo_credito_cache', 12, 2)->default(0);
            $table->decimal('total_credito_cache', 12, 2)->default(0);
            $table->decimal('total_abonos_cache', 12, 2)->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proveedores');
    }
};
