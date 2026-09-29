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
        Schema::create('navigations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('website_id')
                ->constrained('websites')
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('key', 100);

            // FK constraints added later after navigation_versions exists.
            $table->unsignedBigInteger('published_version_id')->nullable();
            $table->unsignedBigInteger('draft_version_id')->nullable();

            $table->timestamps();

            $table->unique(['website_id', 'key']);

            $table->index('published_version_id');
            $table->index('draft_version_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('navigations');
    }
};
