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
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // Who made the change

            // Movement details
            $table->enum('type', [
                'purchase',
                'sale',
                'transfer_in',
                'transfer_out',
                'adjustment_in',
                'adjustment_out',
                'store_demand',
                'store_demand_approved'
            ]);
            $table->integer('quantity'); // Positive for in, negative for out
            $table->decimal('price', 15, 2)->default(0); // Per unit price at movement time

            // References
            $table->string('reference_type')->nullable(); // e.g., PurchaseOrder, Invoice
            $table->unsignedBigInteger('reference_id')->nullable();

            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
