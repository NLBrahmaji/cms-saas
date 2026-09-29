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
        Schema::create('plan_prices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('plan_id')
                ->constrained('plans')
                ->cascadeOnDelete();

            $table->string('billing_period', 20);
            $table->string('currency', 3);
            $table->unsignedBigInteger('amount');

            $table->string('provider_price_id')->nullable()->unique();

            $table->string('status', 30)->default('active');

            $table->timestamps();

            $table->index(['plan_id', 'status']);
            $table->unique(['plan_id', 'billing_period', 'currency']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_prices');
    }
};
