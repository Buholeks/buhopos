<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            if (! Schema::hasColumn('ventas', 'cancelado_por')) {
                $table->foreignId('cancelado_por')->nullable()->after('estado')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('ventas', 'cancelado_en')) {
                $table->timestamp('cancelado_en')->nullable()->after('cancelado_por');
            }

            if (! Schema::hasColumn('ventas', 'motivo_cancelacion')) {
                $table->string('motivo_cancelacion', 255)->nullable()->after('cancelado_en');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            if (Schema::hasColumn('ventas', 'cancelado_por')) {
                $table->dropConstrainedForeignId('cancelado_por');
            }

            if (Schema::hasColumn('ventas', 'cancelado_en')) {
                $table->dropColumn('cancelado_en');
            }

            if (Schema::hasColumn('ventas', 'motivo_cancelacion')) {
                $table->dropColumn('motivo_cancelacion');
            }
        });
    }
};
