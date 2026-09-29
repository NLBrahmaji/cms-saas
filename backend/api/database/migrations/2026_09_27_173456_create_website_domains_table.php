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
        Schema::create('website_domains', function (Blueprint $table) {
            $table->id();

            $table->foreignId('website_id')
                ->constrained('websites')
                ->cascadeOnDelete();

            $table->string('domain')->unique();

            $table->boolean('is_primary')->default(false);

            $table->string('status', 30)->default('pending');

            $table->string('verification_token')->nullable();
            $table->timestamp('verified_at')->nullable();

            $table->string('ssl_status', 30)->nullable();
            $table->timestamp('ssl_issued_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['website_id', 'status']);
            $table->index(['website_id', 'is_primary']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('website_domains');
    }
};
