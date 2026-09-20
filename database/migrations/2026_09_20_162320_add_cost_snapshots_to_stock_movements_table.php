<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            /**
             * Historical cost snapshots. `unit_cost_at` is the price the row
             * is valued at; `unit_cost_source` keeps how it got there so a
             * cost auditor can tell a recorded purchase price from a
             * fallback valuation.
             */
            $table->unsignedInteger('unit_cost_at')->nullable()->after('quantity');
            $table->string('unit_cost_source')->nullable()->after('unit_cost_at');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropColumn(['unit_cost_at', 'unit_cost_source']);
        });
    }
};
