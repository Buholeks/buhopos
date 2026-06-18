<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventario_conteos', function (Blueprint $table) {
            $table->string('alcance_tipo', 30)->default('total')->after('modo');
            $table->unsignedBigInteger('alcance_id')->nullable()->after('alcance_tipo');
            $table->string('alcance_nombre', 160)->nullable()->after('alcance_id');
            $table->index(['empresa_id', 'sucursal_id', 'alcance_tipo', 'alcance_id'], 'idx_inv_conteos_alcance');
        });

        Schema::create('inventario_conteo_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conteo_id')->constrained('inventario_conteos')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tipo', 40);
            $table->string('descripcion', 255);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['conteo_id', 'created_at'], 'idx_inv_conteo_eventos');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_conteo_eventos');

        Schema::table('inventario_conteos', function (Blueprint $table) {
            $table->dropIndex('idx_inv_conteos_alcance');
            $table->dropColumn(['alcance_tipo', 'alcance_id', 'alcance_nombre']);
        });
    }
};
