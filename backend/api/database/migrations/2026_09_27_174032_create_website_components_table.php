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
        Schema::create('website_components', function (Blueprint $table) {
            $table->id();

            $table->foreignId('website_id')
                ->constrained('websites')
                ->cascadeOnDelete();

            $table->foreignId('component_type_id')
                ->constrained('component_types')
                ->restrictOnDelete();

            // FK constraints added later after website_component_versions exists.
            $table->unsignedBigInteger('published_version_id')->nullable();
            $table->unsignedBigInteger('draft_version_id')->nullable();

            $table->timestamps();

            $table->unique(['website_id', 'component_type_id']);

            $table->index('published_version_id');
            $table->index('draft_version_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('website_components');
    }
};
