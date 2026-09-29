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
        Schema::create('website_branding', function (Blueprint $table) {
            $table->id();

            $table->foreignId('website_id')
                ->constrained('websites')
                ->cascadeOnDelete();

            $table->foreignId('logo_media_id')
                ->nullable()
                ->constrained('media')
                ->nullOnDelete();

            $table->foreignId('logo_light_media_id')
                ->nullable()
                ->constrained('media')
                ->nullOnDelete();

            $table->foreignId('logo_dark_media_id')
                ->nullable()
                ->constrained('media')
                ->nullOnDelete();

            $table->foreignId('favicon_media_id')
                ->nullable()
                ->constrained('media')
                ->nullOnDelete();

            $table->json('theme')->nullable();

            $table->timestamps();

            $table->unique('website_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('website_branding');
    }
};
