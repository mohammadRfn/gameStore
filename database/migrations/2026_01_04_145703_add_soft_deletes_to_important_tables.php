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

        Schema::table('service_jobs', function (Blueprint $table) {
            $table->softDeletes();
        });
        Schema::table('shops', function (Blueprint $table) {
            $table->softDeletes();
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->softDeletes();
        });
        Schema::table('items', function (Blueprint $table) {
            $table->softDeletes();
        });
        Schema::table('customers', function (Blueprint $table) {
            $table->softDeletes();
        }); 
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->softDeletes();
        });
        Schema::table('categories', function (Blueprint $table) {
            $table->softDeletes();
        });
        Schema::table('service_job_items', function (Blueprint $table) {
            $table->softDeletes();
        });
        Schema::table('service_types', function (Blueprint $table) {
            $table->softDeletes();
        });
        Schema::table('plans', function (Blueprint $table) {
            $table->softDeletes();
        });
        Schema::table('monthly_sales', function (Blueprint $table) {
            $table->softDeletes();
        });
        Schema::table('daily_item_stats', function (Blueprint $table) {
            $table->softDeletes();
        });
        Schema::table('requests', function (Blueprint $table) {
            $table->softDeletes();
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('important_tables', function (Blueprint $table) {
            //
        });
    }
};
