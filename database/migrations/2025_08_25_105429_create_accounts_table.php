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
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();

            // General account details
            $table->string('account_name');                   // e.g., Cash, HDFC Bank, UPI
            $table->enum('account_type', [
                'cash',
                'bank',
                'upi',
                'credit_card',
                'other'
            ])->default('bank');                              // Type of account

            // Bank-specific details
            $table->string('bank_name')->nullable();          // Bank name
            $table->string('account_number')->nullable();     // Account number
            $table->string('ifsc_code')->nullable();          // IFSC code (for Indian banks)
            $table->string('branch_name')->nullable();        // Branch name
            $table->string('branch_address')->nullable();     // Branch address
            $table->string('branch_code')->nullable();        // Internal branch code (if any)
            $table->string('swift_code')->nullable();         // SWIFT code for international transfers

            // UPI / Digital payment details
            $table->string('upi_id')->nullable();             // UPI ID if applicable

            // Balance details
            $table->decimal('current_balance', 15, 2)->default(0.00);
            $table->decimal('opening_balance', 15, 2)->default(0.00);
            $table->enum('balance_type', ['debit', 'credit'])->default('debit'); // Opening balance type
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
