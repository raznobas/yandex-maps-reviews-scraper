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
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('yandex_review_id')->index();
            $table->string('author_name');
            $table->decimal('rating', 2, 1);
            $table->text('text');
            $table->timestamp('publish_date')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'yandex_review_id']);
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->decimal('rating', 3, 2)->nullable();
            $table->integer('rating_count')->default(0);
            $table->integer('review_count')->default(0);
            $table->timestamp('last_synced_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['rating', 'rating_count', 'review_count', 'last_synced_at']);
        });
        Schema::dropIfExists('reviews');
    }
};
