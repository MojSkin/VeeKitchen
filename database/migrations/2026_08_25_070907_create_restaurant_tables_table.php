<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->unsignedSmallInteger('capacity')->default(4);
            $table->string('qr_token', 64)->unique();
            $table->string('status')->default('free')->index();
            $table->timestamp('occupied_at')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'label']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_tables');
    }
};
