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
        Schema::create('section_templates', function (Blueprint $table) {
            $table->id();

            $table->foreignId('section_type_id')
                ->constrained('section_types')
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('key', 100)->unique();

            $table->json('settings_schema')->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 30)->default('active');

            $table->timestamps();

            $table->index(['section_type_id', 'status', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('section_templates');
    }
};
