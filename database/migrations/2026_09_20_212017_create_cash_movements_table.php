<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('shift_id')->constrained('staff_shifts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            /** Withdrawal (cash out), Deposit (cash in), Adjustment. */
            $table->string('type');
            $table->unsignedBigInteger('amount');
            $table->string('reason');

            $table->timestamps();

            $table->index(['shift_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
    }
};
