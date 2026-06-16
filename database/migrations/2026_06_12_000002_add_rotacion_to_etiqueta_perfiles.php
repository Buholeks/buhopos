<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('etiqueta_perfiles', function (Blueprint $table) {
            $table->unsignedSmallInteger('rotacion')->default(0)->after('escala');
        });
    }

    public function down(): void
    {
        Schema::table('etiqueta_perfiles', fn(Blueprint $table) => $table->dropColumn('rotacion'));
    }
};
