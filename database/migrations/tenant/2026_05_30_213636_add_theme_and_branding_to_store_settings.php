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
            $table->string('theme_name')->default('tech-dark')->after('logo_url');
            $table->string('store_tagline')->nullable()->after('store_name');
            $table->string('favicon_url')->nullable()->after('logo_url');
            $table->json('social_links')->nullable()->after('theme_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            $table->dropColumn([
                'theme_name',
                'store_tagline',
                'favicon_url',
                'social_links',
            ]);
        });
    }
};
