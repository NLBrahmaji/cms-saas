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
        Schema::create('website_seo_settings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('website_id')
                ->constrained('websites')
                ->cascadeOnDelete();

            $table->string('title_suffix')->nullable();
            $table->text('default_description')->nullable();

            $table->foreignId('default_og_image_id')
                ->nullable()
                ->constrained('media')
                ->nullOnDelete();

            $table->boolean('robots_index')->default(true);
            $table->boolean('robots_follow')->default(true);

            $table->timestamps();

            $table->unique('website_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('website_seo_settings');
    }
};
