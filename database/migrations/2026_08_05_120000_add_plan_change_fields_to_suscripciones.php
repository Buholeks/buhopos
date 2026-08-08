<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suscripciones', function (Blueprint $table) {
            $table->foreignId('plan_pendiente_id')->nullable()->after('plan_id')->constrained('planes')->nullOnDelete();
            $table->string('stripe_schedule_id')->nullable()->unique()->after('stripe_subscription_id');
            $table->date('cambio_plan_en')->nullable()->after('fecha_vencimiento');
        });
    }

    public function down(): void
    {
        Schema::table('suscripciones', function (Blueprint $table) {
            $table->dropForeign(['plan_pendiente_id']);
            $table->dropColumn(['plan_pendiente_id', 'stripe_schedule_id', 'cambio_plan_en']);
        });
    }
};
