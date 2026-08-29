<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planes', function (Blueprint $table) {
            $table->decimal('precio_anual', 12, 2)->nullable()->after('precio_mensual');
            $table->string('stripe_price_anual_id')->nullable()->after('stripe_price_id');
        });

        Schema::table('suscripciones', function (Blueprint $table) {
            $table->enum('periodicidad', ['mensual', 'anual'])->default('mensual')->after('plan_id');
            $table->enum('periodicidad_pendiente', ['mensual', 'anual'])->nullable()->after('plan_pendiente_id');
        });
    }

    public function down(): void
    {
        Schema::table('suscripciones', function (Blueprint $table) {
            $table->dropColumn(['periodicidad', 'periodicidad_pendiente']);
        });

        Schema::table('planes', function (Blueprint $table) {
            $table->dropColumn(['precio_anual', 'stripe_price_anual_id']);
        });
    }
};
