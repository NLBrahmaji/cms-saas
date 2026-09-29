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
        Schema::create('page_version_seo_settings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('page_version_id')
                ->constrained('page_versions')
                ->cascadeOnDelete();

            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();

            $table->string('og_title')->nullable();
            $table->text('og_description')->nullable();

            $table->foreignId('og_image_id')
                ->nullable()
                ->constrained('media')
                ->nullOnDelete();

            $table->string('canonical_url', 2048)->nullable();

            $table->boolean('robots_index')->nullable();
            $table->boolean('robots_follow')->nullable();

            $table->timestamps();

            $table->unique('page_version_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('page_version_seo_settings');
    }
};
