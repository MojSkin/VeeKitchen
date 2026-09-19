<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_category_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('price')->comment('Toman');
            $table->string('image_path')->nullable();
            $table->unsignedSmallInteger('prep_minutes')->default(10);
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_available')->default(true);
            $table->timestamps();

            $table->index(['branch_id', 'is_available']);
            $table->index(['menu_category_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
