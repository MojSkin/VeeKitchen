<?php

use App\Enums\CostComponentType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cost_components', function (Blueprint $table) {
            $table->id();

            /** A product's recipe history outlives the product itself. */
            $table->foreignId('product_id')->constrained()->restrictOnDelete();

            /** Tax / management profit / overhead / packaging / ... */
            $table->string('label');
            $table->string('type')->default(CostComponentType::Fixed->value);
            $table->unsignedBigInteger('value')->comment('Fixed: Toman; Percent: basis points, 100 = 1%');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_components');
    }
};
