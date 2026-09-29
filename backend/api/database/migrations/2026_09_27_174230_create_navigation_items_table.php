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
        Schema::create('navigation_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('navigation_version_id')
                ->constrained('navigation_versions')
                ->cascadeOnDelete();

            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('navigation_items')
                ->nullOnDelete();

            $table->string('type', 20);

            $table->foreignId('page_id')
                ->nullable()
                ->constrained('pages')
                ->nullOnDelete();

            $table->string('label')->nullable();
            $table->string('url', 2048)->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('open_in_new_tab')->default(false);

            $table->timestamps();

            $table->index([
                'navigation_version_id',
                'parent_id',
                'sort_order',
            ]);

            $table->index('page_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('navigation_items');
    }
};
