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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('account_id')
                ->constrained('accounts')
                ->cascadeOnDelete();

            $table->foreignId('plan_id')
                ->constrained('plans')
                ->restrictOnDelete();

            $table->foreignId('plan_price_id')
                ->nullable()
                ->constrained('plan_prices')
                ->nullOnDelete();

            $table->string('provider', 50)->nullable();
            $table->string('provider_customer_id')->nullable();
            $table->string('provider_subscription_id')->nullable();

            $table->string('status', 30);

            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('cancel_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

            $table->index(['account_id', 'status']);
            $table->index('provider_customer_id');
            $table->unique(['provider', 'provider_subscription_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
