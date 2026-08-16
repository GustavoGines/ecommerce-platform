<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FIX-03: Add a composite index on (ip_address, created_at) to the page_visits table.
     *
     * The TrackVisits middleware runs this query on every HTTP GET request:
     *   SELECT EXISTS(SELECT 1 FROM page_visits WHERE ip_address = ? AND DATE(created_at) = ?)
     *
     * Without an index this is a full table scan. With thousands of accumulated visits
     * this can noticeably slow down every page load, especially under bot traffic.
     */
    public function up(): void
    {
        Schema::table('page_visits', function (Blueprint $table) {
            $table->index(['ip_address', 'created_at'], 'idx_page_visits_ip_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('page_visits', function (Blueprint $table) {
            $table->dropIndex('idx_page_visits_ip_date');
        });
    }
};
