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
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('sync_status')->default('idle')->after('last_synced_at');
            $table->text('sync_error')->nullable()->after('sync_status');
            $table->unsignedInteger('synced_reviews_count')->default(0)->after('sync_error');
            $table->timestamp('last_sync_started_at')->nullable()->after('synced_reviews_count');
            $table->timestamp('last_sync_finished_at')->nullable()->after('last_sync_started_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn([
                'sync_status',
                'sync_error',
                'synced_reviews_count',
                'last_sync_started_at',
                'last_sync_finished_at',
            ]);
        });
    }
};
