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
        Schema::create('page_sections', function (Blueprint $table) {
            $table->id();

            $table->foreignId('page_version_id')
                ->constrained('page_versions')
                ->cascadeOnDelete();

            $table->foreignId('section_template_id')
                ->constrained('section_templates')
                ->restrictOnDelete();

            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);

            $table->json('settings')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['page_version_id', 'sort_order']);
            $table->index('section_template_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('page_sections');
    }
};
