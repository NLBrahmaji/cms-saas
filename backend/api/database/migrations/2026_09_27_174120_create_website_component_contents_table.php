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
        Schema::create('website_component_contents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('component_version_id')
                ->constrained('website_component_versions')
                ->cascadeOnDelete();

            $table->json('content');

            $table->timestamps();

            $table->unique('component_version_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('website_component_contents');
    }
};
