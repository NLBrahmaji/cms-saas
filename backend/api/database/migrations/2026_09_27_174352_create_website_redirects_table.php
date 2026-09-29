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
        Schema::create('website_redirects', function (Blueprint $table) {
            $table->id();

            $table->foreignId('website_id')
                ->constrained('websites')
                ->cascadeOnDelete();

            $table->string('source_path', 2048);

            $table->string('destination_type', 20);

            $table->foreignId('page_id')
                ->nullable()
                ->constrained('pages')
                ->nullOnDelete();

            $table->string('destination_url', 2048)->nullable();

            $table->unsignedSmallInteger('redirect_code')->default(301);

            $table->timestamps();

            $table->unique(['website_id', 'source_path']);
            $table->index('page_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('website_redirects');
    }
};
