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
        Schema::create('page_section_contents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('page_section_id')
                ->constrained('page_sections')
                ->cascadeOnDelete();

            $table->json('content');

            $table->timestamps();

            $table->unique('page_section_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('page_section_contents');
    }
};
