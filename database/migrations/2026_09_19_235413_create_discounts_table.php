<?php

use App\Enums\DiscountScope;
use App\Enums\DiscountType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discounts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();

            /** Public face of the discount, e.g. «جشنوارهٔ پیتزا». */
            $table->string('name');

            /** Optional coupon code; null = automatic discount, no code needed. */
            $table->string('code')->nullable()->unique();

            $table->string('type')->default(DiscountType::Percentage->value);
            $table->unsignedBigInteger('value')->comment('Percentage: 0-100; Fixed: Toman');

            /** What the discount applies to, plus the optional target row. */
            $table->string('applies_to')->default(DiscountScope::EntireOrder->value);
            $table->foreignId('menu_category_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->cascadeOnDelete();

            /** Cart must reach this subtotal (Toman) before the discount bites. */
            $table->unsignedBigInteger('min_order_total')->default(0);

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->unsignedInteger('usage_limit_total')->nullable();
            $table->unsignedInteger('usage_limit_per_user')->nullable();
            $table->unsignedInteger('used_count')->default(0);

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['branch_id', 'is_active']);
            $table->index(['applies_to', 'menu_category_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discounts');
    }
};
