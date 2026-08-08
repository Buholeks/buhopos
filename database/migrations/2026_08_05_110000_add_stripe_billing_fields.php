<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('stripe_customer_id')->nullable()->unique()->after('activo');
        });
        Schema::table('planes', function (Blueprint $table) {
            $table->string('stripe_product_id')->nullable()->unique()->after('activo');
            $table->string('stripe_price_id')->nullable()->unique()->after('stripe_product_id');
            $table->timestamp('stripe_sincronizado_en')->nullable()->after('stripe_price_id');
        });
        Schema::table('suscripciones', function (Blueprint $table) {
            $table->string('stripe_subscription_id')->nullable()->unique()->after('empresa_id');
            $table->string('stripe_status')->nullable()->after('estado');
            $table->boolean('cancelar_al_final')->default(false)->after('stripe_status');
        });
        Schema::table('pagos_suscripcion', function (Blueprint $table) {
            $table->string('stripe_invoice_id')->nullable()->unique()->after('suscripcion_id');
            $table->string('stripe_payment_intent_id')->nullable()->after('stripe_invoice_id');
        });
        Schema::create('stripe_eventos', function (Blueprint $table) {
            $table->id();
            $table->string('stripe_event_id')->unique();
            $table->string('tipo');
            $table->timestamp('procesado_en');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_eventos');
        Schema::table('pagos_suscripcion', fn (Blueprint $table) => $table->dropColumn(['stripe_invoice_id', 'stripe_payment_intent_id']));
        Schema::table('suscripciones', fn (Blueprint $table) => $table->dropColumn(['stripe_subscription_id', 'stripe_status', 'cancelar_al_final']));
        Schema::table('planes', fn (Blueprint $table) => $table->dropColumn(['stripe_product_id', 'stripe_price_id', 'stripe_sincronizado_en']));
        Schema::table('empresas', fn (Blueprint $table) => $table->dropColumn('stripe_customer_id'));
    }
};
