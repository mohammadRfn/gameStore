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
        Schema::create('service_job_items', function (Blueprint $table) {
            $table->id();
              $table->foreignId('service_job_id')
                ->constrained('service_jobs')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('item_id')
                ->constrained('items')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->integer('quantity')->default(1);

            $table->decimal('unit_price', 10, 2);  
            $table->decimal('total_price', 10, 2);  
            $table->timestamps();

            $table->index(['service_job_id', 'item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_job_items');
    }
};
