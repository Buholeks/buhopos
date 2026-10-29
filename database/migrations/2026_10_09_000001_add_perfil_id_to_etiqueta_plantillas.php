<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('etiqueta_plantillas', function (Blueprint $table) {
            $table->foreignId('perfil_id')->nullable()->constrained('etiqueta_perfiles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('etiqueta_plantillas', fn (Blueprint $table) => $table->dropConstrainedForeignId('perfil_id'));
    }
};
