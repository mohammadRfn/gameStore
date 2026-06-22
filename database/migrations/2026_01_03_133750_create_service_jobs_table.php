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
        Schema::create('service_jobs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('shop_id')
                ->constrained('shops')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('customer_id')
                ->nullable()
                ->constrained('customers')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreignId('request_id')
                ->nullable()
                ->constrained('requests')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreignId('service_type_id')
                ->nullable()
                ->constrained('service_types')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->string('device_type')->nullable();
            $table->string('device_serial')->nullable();

            $table->text('customer_problem_description')->nullable();
            $table->text('diagnosis_description')->nullable();

            $table->string('status', 32)->default('received');

            $table->foreignId('invoice_id')
                ->nullable()
                ->constrained('invoices')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->decimal('estimated_price', 10, 2)->nullable();
            $table->decimal('final_price', 10, 2)->nullable();

            $table->timestamp('received_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            $table->timestamps();

            $table->index(['shop_id', 'status']);
            $table->index(['device_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_jobs');
    }
};
