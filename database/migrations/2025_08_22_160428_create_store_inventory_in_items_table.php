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
        Schema::create('store_inventory_in_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_inventory_in_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();

            // Stock details
            $table->unsignedInteger('quantity')->default(0);
            $table->decimal('purchase_price', 15, 2)->default(0);
            $table->decimal('selling_price', 15, 2)->default(0)->nullable();
            $table->decimal('tax_rate', 5, 2)->default(0); // GST / VAT
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2)->default(0);

            $table->string('batch_no')->nullable();   // For batch tracking
            $table->date('mfg_date')->nullable();
            $table->date('exp_date')->nullable();

            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_inventory_in_items');
    }
};
