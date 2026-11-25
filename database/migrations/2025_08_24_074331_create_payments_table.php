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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            // 🔹 Polymorphic relation: Payment can be linked to Invoice, Purchase, Credit Note, etc.
            $table->nullableMorphs('payable'); // payable_id + payable_type (e.g., Invoice, VendorBill, etc.)

            // 🔹 Link to invoice (optional but useful for tracking)
            $table->foreignId('invoice_id')->nullable()->constrained()->cascadeOnDelete();

            // 🔹 Link to account
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();

            // 🔹 Direction of payment
            $table->enum('type', ['incoming', 'outgoing']);
            // incoming = receipt from customer, outgoing = vendor/supplier payment

            // 🔹 Payment details
            $table->decimal('amount', 15, 2);
            $table->date('payment_date')->default(now());

            // 🔹 Payment Method & Reference
            $table->string('method')->nullable(); // cash, bank, UPI, cheque, card, etc.
            $table->string('reference_no')->nullable(); // Transaction ID, cheque no, UTR no, etc.
            $table->string('currency', 3)->default('INR');
            $table->decimal('exchange_rate', 12, 4)->default(1);

            // 🔹 Status
            $table->enum('status', ['pending', 'completed', 'failed', 'refunded'])->default('completed');

            // 🔹 Notes & attachments
            $table->text('notes')->nullable();
            $table->string('attachment')->nullable(); // payment receipt, bank slip, etc.

            // 🔹 Who processed
            $table->foreignId('received_by')->nullable()->constrained('users');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');

            $table->timestamps();
            $table->softDeletes();

            // 🔑 Indexes for performance
            $table->index(['type', 'status']);
            $table->index(['payment_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
