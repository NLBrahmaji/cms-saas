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
        Schema::create('website_component_versions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('website_component_id')
                ->constrained('website_components')
                ->cascadeOnDelete();

            $table->unsignedInteger('version');

            $table->foreignId('template_id')
                ->constrained('component_templates')
                ->restrictOnDelete();

            $table->json('settings')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('published_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('published_at')->nullable();

            $table->timestamps();

            $table->unique([
                'website_component_id',
                'version',
            ]);

            $table->index([
                'website_component_id',
                'published_at',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('website_component_versions');
    }
};
