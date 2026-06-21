<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('empresa_id')->index();
            $table->string('hash', 64);
            $table->string('ruta', 500);
            $table->string('nombre_original', 255);
            $table->string('carpeta', 100);   // e.g. 'productos/1', 'marcas/logos'
            $table->unsignedInteger('tamanio'); // bytes
            $table->string('mime_type', 100);
            $table->timestamps();

            $table->unique(['empresa_id', 'hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
