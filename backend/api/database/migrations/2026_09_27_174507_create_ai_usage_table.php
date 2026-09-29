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
        Schema::create('ai_usage', function (Blueprint $table) {
            $table->id();

            $table->foreignId('account_id')
                ->constrained('accounts')
                ->cascadeOnDelete();

            $table->foreignId('website_id')
                ->nullable()
                ->constrained('websites')
                ->nullOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('operation', 50);

            $table->string('provider', 50)->nullable();
            $table->string('model', 100)->nullable();

            $table->unsignedBigInteger('input_tokens')->nullable();
            $table->unsignedBigInteger('output_tokens')->nullable();

            $table->timestamps();

            $table->index(['account_id', 'created_at']);
            $table->index(['website_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['account_id', 'operation', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_usage');
    }
};
