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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')
                ->constrained('shops')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('item_id')
                ->constrained('items')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('invoice_id')
                ->nullable()
                ->constrained('invoices')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreignId('service_job_id')
                ->nullable()
                ->constrained('service_jobs')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->string('movement_type', 32);

            $table->integer('quantity');

            $table->decimal('unit_cost', 10, 2)->nullable();

            $table->string('reason')->nullable();

            $table->text('note')->nullable();

            $table->timestamps();

            $table->index(['shop_id', 'item_id']);
            $table->index(['movement_type', 'reason']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
