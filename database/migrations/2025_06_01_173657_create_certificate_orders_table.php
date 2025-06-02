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
        Schema::create('certificate_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('certificate_product_id')->constrained();
            $table->string('square_payment_id')->nullable();
            $table->string('gogetssl_order_id')->nullable();
            $table->string('domain_name');
            $table->enum('status', ['pending', 'processing', 'issued', 'failed', 'expired']);
            $table->text('csr')->nullable();
            $table->text('private_key')->nullable();
            $table->text('certificate_content')->nullable();
            $table->text('ca_bundle')->nullable();
            $table->decimal('total_amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->timestamp('expires_at')->nullable();
            $table->string('approver_email')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificate_orders');
    }
};
