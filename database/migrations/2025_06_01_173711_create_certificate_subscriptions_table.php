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
        Schema::create('certificate_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('certificate_order_id')->constrained();
            $table->string('square_subscription_id');
            $table->enum('status', ['active', 'paused', 'cancelled']);
            $table->timestamp('next_billing_date');
            $table->enum('billing_interval', ['monthly', 'yearly']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificate_subscriptions');
    }
};
