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
        Schema::create('daily_item_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')
                ->constrained('shops')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('item_id')
                ->constrained('items')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->date('stat_date');

            $table->integer('opening_stock')->nullable();
            $table->integer('closing_stock')->nullable();

            $table->integer('sold_quantity')->default(0);       
            $table->integer('purchased_quantity')->default(0);  
            $table->integer('adjusted_in_quantity')->default(0);  
            $table->integer('adjusted_out_quantity')->default(0); 

            $table->decimal('revenue', 12, 2)->default(0);        
            $table->decimal('total_cost', 12, 2)->default(0);     
            $table->decimal('profit', 12, 2)->default(0);       

            $table->timestamps();

            $table->unique(['shop_id', 'item_id', 'stat_date'], 'daily_item_stats_unique');

            $table->index(['shop_id', 'stat_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_item_stats');
    }
};
