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
        Schema::create('store_inventory_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->restrictOnDelete();

            // Who received the stock (store staff)            
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('received_by_text')->nullable();

            // Supplier / Vendor Info (if applicable)
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete(); // Changed from supplier_id
            $table->string('invoice_no')->nullable();
            $table->date('invoice_date')->nullable();

            // Transport & Delivery Info
            $table->string('vehicle_no')->nullable();
            $table->string('driver_name')->nullable();
            $table->string('driver_contact')->nullable();
            $table->string('delivery_person')->nullable();
            $table->text('delivery_info')->nullable();

            // Financials
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->string('payment_status')->default('pending'); // pending, paid, partial
            $table->string('payment_method')->nullable(); // cash, bank, upi etc.
            $table->date('payment_date')->nullable();

            // Attachments
            $table->json('documents')->nullable(); // invoice, delivery challan, etc.

            $table->text('notes')->nullable();
            $table->string('transaction_type')->default('stock_in'); // Add this

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_inventory_ins');
    }
};
