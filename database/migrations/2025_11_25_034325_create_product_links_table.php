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
        Schema::create('product_links', function (Blueprint $table) {
            $table->id();
            
            // product_id = main/original product
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();

            // linked_product_id = related product
            $table->foreignId('linked_product_id')->constrained('products')->cascadeOnDelete();

            $table->unique(['product_id', 'linked_product_id']);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_links');
    }
};
