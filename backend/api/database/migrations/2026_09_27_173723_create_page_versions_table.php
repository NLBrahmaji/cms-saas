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
        Schema::create('page_versions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('page_id')
                ->constrained('pages')
                ->cascadeOnDelete();

            $table->unsignedInteger('version');

            $table->string('name');
            $table->string('slug');

            $table->foreignId('parent_page_id')
                ->nullable()
                ->constrained('pages')
                ->nullOnDelete();

            $table->boolean('is_home')->default(false);

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

            $table->unique(['page_id', 'version']);

            $table->index('parent_page_id');
            $table->index(['page_id', 'published_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('page_versions');
    }
};
