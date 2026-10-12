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
        Schema::create('pos_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('direction', 10); // 'inbound' | 'outbound'
            $table->string('event_type', 50); // 'inventory.update', 'order.created', etc.
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->longText('payload')->nullable();
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->text('response_body')->nullable();
            $table->string('status', 20)->default('success'); // 'success', 'failed', 'retrying'
            $table->text('error_message')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['direction', 'created_at']);
            $table->index(['event_type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_sync_logs');
    }
};
