<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('receipt')->unique();
            $table->string('razorpay_order_id')->unique();
            $table->string('razorpay_payment_id')->nullable()->unique();
            $table->unsignedBigInteger('amount');              // smallest unit (paise)
            $table->string('currency', 3)->default('INR');
            $table->string('status', 30)->default('created')->index();
            $table->string('method', 30)->nullable();
            $table->unsignedBigInteger('amount_refunded')->default(0);
            $table->string('description')->nullable();
            $table->json('notes')->nullable();
            $table->string('failure_code')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
