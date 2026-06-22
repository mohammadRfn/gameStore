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
        Schema::create('monthly_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')
                ->constrained('shops')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->unsignedSmallInteger('year');   
            $table->unsignedTinyInteger('month'); 

            $table->unsignedInteger('total_invoices')->default(0);
            $table->unsignedInteger('confirmed_invoices')->default(0);

            $table->decimal('total_revenue', 14, 2)->default(0);        
            $table->decimal('products_revenue', 14, 2)->default(0);      
            $table->decimal('services_revenue', 14, 2)->default(0);      

            $table->decimal('total_cost', 14, 2)->default(0);         
            $table->decimal('profit', 14, 2)->default(0);                

            $table->unsignedInteger('unique_customers')->default(0);   
            $table->unsignedInteger('new_customers')->default(0);       
            $table->timestamps();

            $table->unique(['shop_id', 'year', 'month'], 'monthly_sales_unique');

            $table->index(['shop_id', 'year', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monthly_sales');
    }
};
