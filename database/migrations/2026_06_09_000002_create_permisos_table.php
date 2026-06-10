<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permisos', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 80)->unique();
            $table->string('modulo', 50);
            $table->string('descripcion', 255);
            $table->timestamps();

            $table->index('modulo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permisos');
    }
};
