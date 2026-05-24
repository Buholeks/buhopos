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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('empresa_id')
                ->nullable()
                ->after('id')
                ->constrained('empresas')
                ->nullOnDelete();

            $table->foreignId('sucursal_id')
                ->nullable()
                ->after('empresa_id')
                ->constrained('sucursales')
                ->nullOnDelete();

            // (Opcional) Para consultas rápidas por empresa/sucursal
            $table->index(['empresa_id', 'sucursal_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
           $table->dropIndex(['empresa_id', 'sucursal_id']);

            $table->dropForeign(['sucursal_id']);
            $table->dropColumn('sucursal_id');

            $table->dropForeign(['empresa_id']);
            $table->dropColumn('empresa_id');
        });
    }
};
