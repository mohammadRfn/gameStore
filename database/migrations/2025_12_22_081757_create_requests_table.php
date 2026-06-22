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
        Schema::create('requests', function (Blueprint $table) {
            $table->id();
            $table->string('customer_name');
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->text('description');
            $table->string('status');
            $table->foreignId('shop_id')
                ->nullable()
                ->after('id')
                ->constrained('shops')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->index('shop_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('requests');
    }
};
