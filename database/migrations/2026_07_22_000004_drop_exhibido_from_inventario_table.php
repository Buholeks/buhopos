<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('inventario', 'exhibido')) {
            Schema::table('inventario', function (Blueprint $table) {
                $table->dropColumn('exhibido');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('inventario', 'exhibido')) {
            Schema::table('inventario', function (Blueprint $table) {
                $table->boolean('exhibido')->default(false)->after('stock_minimo');
            });
        }
    }
};
