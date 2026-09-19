<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name');

            /** Storage/recipe unit, e.g. kg, g, l, ml, piece. */
            $table->string('unit', 16);

            /** Remaining stock expressed in the item's own unit. */
            $table->decimal('current_stock', 12, 3)->default(0);

            /** When stock drops to or below this, the low-stock alert fires. */
            $table->decimal('low_stock_threshold', 12, 3)->default(0);

            /** Physical barcode/QR label for fast warehouse lookup. */
            $table->string('qr_label')->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['branch_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
