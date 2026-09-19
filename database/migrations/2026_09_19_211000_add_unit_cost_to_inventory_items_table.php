<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            /** Toman per unit — the last purchase cost, the input of cost pricing. */
            $table->unsignedBigInteger('unit_cost')->default(0)->after('unit');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropColumn('unit_cost');
        });
    }
};
