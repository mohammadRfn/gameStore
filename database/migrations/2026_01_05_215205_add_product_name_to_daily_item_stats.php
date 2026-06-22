<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('daily_item_stats', function (Blueprint $table) {
            $table->string('product_name')->nullable()->after('item_id');
            $table->index('product_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_item_stats', function (Blueprint $table) {
            //
        });
    }
};
