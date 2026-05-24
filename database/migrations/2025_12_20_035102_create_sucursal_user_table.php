<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sucursal_user', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('sucursal_id')
                ->constrained('sucursales')
                ->cascadeOnDelete();

            // opcional: para marcar principal sin tocar users.sucursal_id
            // $table->boolean('principal')->default(false);

            $table->timestamps();

            $table->unique(['user_id', 'sucursal_id']);
            $table->index(['sucursal_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sucursal_user');
    }
};
