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
        Schema::table('store_settings', function (Blueprint $table) {
            $table->boolean('pos_sync_enabled')->default(false)->after('mp_public_key');
            $table->string('pos_api_token')->nullable()->after('pos_sync_enabled');
            $table->string('pos_webhook_url')->nullable()->after('pos_api_token');
            $table->string('pos_webhook_secret')->nullable()->after('pos_webhook_url');
            $table->string('pos_sync_on_event')->default('order.created')->after('pos_webhook_secret');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            $table->dropColumn([
                'pos_sync_enabled',
                'pos_api_token',
                'pos_webhook_url',
                'pos_webhook_secret',
                'pos_sync_on_event',
            ]);
        });
    }
};
