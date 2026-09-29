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
        Schema::create('website_settings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('website_id')
                ->constrained('websites')
                ->cascadeOnDelete();

            $table->string('site_name');
            $table->string('tagline')->nullable();

            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 50)->nullable();

            $table->json('address')->nullable();
            $table->json('social_links')->nullable();

            $table->timestamps();

            $table->unique('website_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('website_settings');
    }
};
