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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            // Generic document number (auto-generated per type)
            $table->string('number')->unique();

            // Document type (unifies all billing documents)
            $table->enum('document_type', [
                'purchase_order',
                'purchase',
                'invoice',
                'estimate',
                'quotation',
                'credit_note',
                'debit_note',
                'delivery_note',
                'proforma',
                'receipt',
                'payment_voucher',
                'transfer_order',
            ])->default('invoice');

            // Polymorphic billing party: customer, vendor OR source store
            $table->morphs('billable'); // billable_id + billable_type

            // Destination store (nullable, only for transfer orders)
            $table->foreignId('destination_store_id')
                ->nullable()
                ->constrained('stores')
                ->nullOnDelete();

            // Dates
            $table->date('document_date'); // works for invoice_date, po_date, etc.
            $table->date('due_date')->nullable();

            // Tax related
            $table->string('place_of_supply')->nullable();
            $table->decimal('taxable_value', 15, 2)->default(0);
            $table->decimal('cgst_amount', 15, 2)->default(0);
            $table->decimal('sgst_amount', 15, 2)->default(0);
            $table->decimal('igst_amount', 15, 2)->default(0);
            $table->decimal('total_tax', 15, 2)->default(0);

            // Discount and totals
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);

            // Payment / process status
            $table->enum('status', [
                'draft',
                'pending',
                'approved',
                'rejected',
                'paid',
                'partial',
                'cancelled',
                'completed',
            ])->default('draft');


            $table->enum('payment_mode', [
                'cash',
                'bank_transfer',
                'cheque',
                'upi',
                'card',
                'other',
            ])->nullable();

            $table->string('payment_terms')->nullable(); // e.g., "Net 30"
            $table->string('currency', 3)->default('INR');
            $table->decimal('exchange_rate', 10, 4)->default(1);

            // Shipping
            $table->string('shipping_address')->nullable();
            $table->string('shipping_method')->nullable();
            $table->decimal('shipping_charges', 15, 2)->default(0);

            $table->text('notes')->nullable();

            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();

            // User/admin who created the document
            $table->foreignId('created_by')->constrained('users');

            $table->string('document_path')->nullable();

            $table->softDeletes();
            $table->timestamps();

            // 🔑 Composite index for performance
            $table->index(['document_type', 'number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
