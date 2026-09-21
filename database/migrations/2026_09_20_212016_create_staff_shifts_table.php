<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_shifts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();

            /** Toman integers — no decimals, sqlite and MySQL agree. */
            $table->unsignedBigInteger('opening_cash')->default(0);
            $table->unsignedBigInteger('closing_cash')->nullable();
            $table->unsignedBigInteger('expected_cash')->nullable();
            $table->integer('discrepancy')->nullable();

            /** The admin (or colleague) who closed the shift. */
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['branch_id', 'user_id', 'closed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_shifts');
    }
};
