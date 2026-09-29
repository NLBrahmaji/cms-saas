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
        Schema::create('forms', function (Blueprint $table) {
            $table->id();

            $table->foreignId('website_id')
                ->constrained('websites')
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('key', 100);

            $table->string('status', 30)->default('active');

            $table->json('fields');

            $table->string('success_type', 20)->default('message');
            $table->text('success_message')->nullable();
            $table->string('redirect_url', 2048)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['website_id', 'key']);
            $table->index(['website_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('forms');
    }
};
