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
        Schema::create('section_types', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('key', 100)->unique();
            $table->text('description')->nullable();

            $table->json('content_schema');

            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 30)->default('active');

            $table->timestamps();

            $table->index(['status', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('section_types');
    }
};
