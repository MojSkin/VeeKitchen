<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-3 plan: the kitchen registers entry/exit shifts too — but without
 * cash settlement. The station marks which drawer a shift is bound to:
 * `cashier` shifts settle money, `kitchen` shifts are presence only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_shifts', function (Blueprint $table): void {
            $table->string('station', 20)->default('cashier')->after('user_id');
            $table->index(['branch_id', 'station']);
        });
    }

    public function down(): void
    {
        Schema::table('staff_shifts', function (Blueprint $table): void {
            $table->dropIndex(['branch_id', 'station']);
            $table->dropColumn('station');
        });
    }
};
