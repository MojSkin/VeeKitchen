<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_recipe_versions', function (Blueprint $table) {
            $table->id();

            /** A product carrying recipes/versions is already undeletable. */
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            /** Sequential per product: 1, 2, 3, ... */
            $table->unsignedInteger('version_number');

            /** Frozen recipe lines with the unit costs of that moment. */
            $table->json('lines');

            /** Frozen cost-component chain (config + computed amounts). */
            $table->json('components')->nullable();

            /** Toman, ceiling-rounded per the §5 rule. */
            $table->unsignedBigInteger('material_cost');
            $table->unsignedBigInteger('cost_price');
            $table->unsignedBigInteger('suggested_sale_price');

            $table->timestamps();

            $table->unique(['product_id', 'version_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_recipe_versions');
    }
};
