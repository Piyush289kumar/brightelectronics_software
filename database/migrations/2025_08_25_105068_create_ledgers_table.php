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
        Schema::create('ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts');
            $table->date('date');

            // More detailed transaction types
            $table->enum('transaction_type', [
                'debit',
                'credit',
                'opening_balance',
                'adjustment',
                'accrual',
                'reversal',
                'transfer',
                'payment',
                'receipt',
                'refund',
                'write_off',
                'provision',
                'closing_balance'
            ]);

            $table->decimal('amount', 15, 2);
            $table->decimal('balance', 15, 2)->default(0);

            // 🔗 Reference to journal entry
            $table->foreignId('journal_entry_id')->nullable()->constrained();

            // 🔗 Polymorphic reference (invoice, payment, voucher, transfer, etc.)
            $table->nullableMorphs('ledgerable'); // allows NULL

            // Extra accounting data
            $table->string('reference')->nullable();
            $table->string('currency', 3)->default('INR');
            $table->decimal('exchange_rate', 10, 4)->default(1);

            // Reconciliation
            $table->boolean('is_reconciled')->default(false);
            $table->date('reconciled_at')->nullable();

            $table->text('narration')->nullable(); // Explanation for the entry
            $table->foreignId('created_by')->constrained('users');
            $table->json('meta')->nullable(); // For storing extra dynamic data (tags, source, import_id, etc.)
            $table->enum('status', ['draft', 'posted', 'void'])->default('posted');


            $table->softDeletes();
            $table->timestamps();

            $table->index(['account_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ledgers');
    }
};
