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
        Schema::create('website_templates', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('key', 100)->unique();

            $table->text('description')->nullable();
            $table->string('category', 100)->nullable();

            $table->string('thumbnail', 2048)->nullable();

            $table->json('structure');

            $table->string('status', 30)->default('active');
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['status', 'sort_order']);
            $table->index('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('website_templates');
    }
};
