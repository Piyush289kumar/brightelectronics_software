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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            // Basic Info
            $table->string('name'); // e.g., "Cement", "Bricks"
            $table->string('code')->unique(); // Short code, useful for import/export
            $table->string('slug')->unique(); // SEO-friendly name for URLs

            // Description & Media
            $table->text('description')->nullable();
            $table->string('image_path')->nullable(); // Optional category image/logo

            // Hierarchy
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('categories')
                ->nullOnDelete(); // For subcategories

            // Tax Settings (India GST Specific)
            $table->string('hsn_code', 8)->nullable(); // Default HSN for all products in this category
            $table->decimal('default_gst_rate', 5, 2)->default(0.00); // GST % (can be overridden at product level)
            $table->foreignId('tax_slab_id')->nullable()->constrained()->nullOnDelete(); // Link to tax slab table

            // Inventory & Alerts
            $table->boolean('track_inventory')->default(true); // Allow disabling stock tracking
            $table->integer('default_min_stock')->default(0); // Default reorder point
            $table->integer('default_max_stock')->nullable();

            // Display & Sorting
            $table->integer('sort_order')->default(0); // For arranging in menus
            $table->boolean('is_active')->default(true);

            // Metadata for integrations
            $table->json('meta')->nullable(); // For storing extra data like POS/ecommerce sync IDs

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
