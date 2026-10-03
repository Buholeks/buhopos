<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('esquema_vendedor_id')->nullable()->after('sucursal_id')
                ->constrained('comision_esquemas')->nullOnDelete();
            $table->foreignId('esquema_cajero_id')->nullable()->after('esquema_vendedor_id')
                ->constrained('comision_esquemas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['esquema_vendedor_id']);
            $table->dropForeign(['esquema_cajero_id']);
            $table->dropColumn(['esquema_vendedor_id', 'esquema_cajero_id']);
        });
    }
};
