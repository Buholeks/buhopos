<?php

// database/migrations/xxxx_xx_xx_create_clientes_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();

            // Multi-tenant automático
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('sucursal_id')->constrained('sucursales');
            $table->foreignId('user_id')->constrained('users');

            // Datos del cliente
            $table->string('nombre', 150);
            $table->string('correo', 150)->nullable();
            $table->string('telefono', 30);
            $table->string('direccion', 255)->nullable();
            $table->boolean('activo')->default(true);

            $table->timestamps();

            // evitar duplicados por empresa 
            $table->unique(['empresa_id', 'telefono']);
       });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
