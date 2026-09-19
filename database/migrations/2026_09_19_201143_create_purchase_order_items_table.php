<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();

            /** Materials are ledger-tracked; a supplier row must never dangle. */
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->restrictOnDelete();

            $table->decimal('quantity', 12, 3);
            $table->unsignedBigInteger('unit_cost')->comment('Toman per unit');
            $table->unsignedBigInteger('line_total')->comment('Toman');
            $table->timestamps();

            $table->index(['purchase_order_id', 'inventory_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
