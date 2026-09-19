<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->string('type')->index();

            /** Signed amount: negative consumes stock, positive adds it back. */
            $table->decimal('quantity', 12, 3);

            /** Which order caused a consumption movement — unique, so paying twice never deducts twice. */
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();

            $table->string('reason')->nullable();

            /** Order lineage keeps payment ids unique even when orders are purged. */
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['payment_id', 'inventory_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
