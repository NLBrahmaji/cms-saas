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
        Schema::create('navigation_versions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('navigation_id')
                ->constrained('navigations')
                ->cascadeOnDelete();

            $table->unsignedInteger('version');

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

            $table->unique(['navigation_id', 'version']);

            $table->index([
                'navigation_id',
                'published_at',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('navigation_versions');
    }
};
