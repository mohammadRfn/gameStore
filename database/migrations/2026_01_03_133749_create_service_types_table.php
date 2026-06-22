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
        Schema::create('service_types', function (Blueprint $table) {
            $table->id();
             $table->foreignId('shop_id')
                ->constrained('shops')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('name');        
            $table->string('platform')->nullable(); 
            $table->string('category')->nullable(); 

            $table->decimal('base_price', 10, 2)->nullable();
            $table->text('description')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['shop_id', 'platform']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_types');
    }
};
