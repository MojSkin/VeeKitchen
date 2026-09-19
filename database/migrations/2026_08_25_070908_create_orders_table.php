<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('restaurant_table_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();

            /** Identifies a guest across visits without an account. */
            $table->string('guest_token', 64)->nullable()->index();
            $table->string('guest_name')->nullable();

            /** Assigned only once the cashier confirms payment, and resettable per day. */
            $table->unsignedInteger('order_number')->nullable();
            $table->string('status')->default('awaiting_payment')->index();

            $table->unsignedBigInteger('subtotal')->default(0)->comment('Toman');
            $table->unsignedBigInteger('discount_total')->default(0)->comment('Toman');
            $table->unsignedBigInteger('total')->default(0)->comment('Toman, ceiling-rounded to 100');

            $table->text('notes')->nullable();
            $table->timestamp('placed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
            $table->index(['branch_id', 'order_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
