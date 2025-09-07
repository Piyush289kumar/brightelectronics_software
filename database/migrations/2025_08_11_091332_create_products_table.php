<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            // Basic Info
            $table->string('name');
            $table->string('sku')->unique(); // Internal unique code
            $table->string('barcode')->nullable(); // Optional barcode/EAN
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();

            // Category & Tax
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('hsn_code', 8)->nullable(); // GST HSN/SAC
            $table->foreignId('tax_slab_id')->nullable()->constrained()->nullOnDelete(); // For GST linking
            $table->decimal('gst_rate', 5, 2)->nullable(); // Override category slab if needed

            // Pricing
            $table->decimal('purchase_price', 15, 2)->default(0);
            $table->decimal('selling_price', 15, 2)->default(0);
            $table->decimal('mrp', 15, 2)->nullable();

            // Stock Management
            $table->boolean('track_inventory')->default(true);
            $table->integer('min_stock')->default(0); // Alert threshold
            $table->integer('max_stock')->nullable();

            // Metadata
            $table->string('image_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('meta')->nullable(); // Custom integrations

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
