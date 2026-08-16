<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // ID de la preferencia generada al crear la orden (antes del pago)
            $table->string('mp_preference_id')->nullable()->after('role_applied');
            // ID del pago confirmado por MercadoPago (llega via webhook)
            $table->string('mp_payment_id')->nullable()->after('mp_preference_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['mp_preference_id', 'mp_payment_id']);
        });
    }
};
