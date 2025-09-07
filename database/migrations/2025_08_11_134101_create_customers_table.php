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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();

            // Billing Address
            $table->text('billing_address')->nullable();
            $table->string('billing_city')->nullable();
            $table->string('billing_state')->nullable();
            $table->string('billing_postal_code')->nullable();

            // Shipping Address (if different)
            $table->text('shipping_address')->nullable();
            $table->string('shipping_city')->nullable();
            $table->string('shipping_state')->nullable();
            $table->string('shipping_postal_code')->nullable();

            // GST & PAN
            $table->string('gstin')->nullable()->unique();
            $table->string('pan')->nullable()->unique();

            // Place of supply - State Code as per GST (e.g. '27' for Maharashtra)
            $table->string('place_of_supply')->nullable();

            // Contact Person Details
            $table->string('contact_person_name')->nullable();
            $table->string('contact_person_phone')->nullable();
            $table->string('contact_person_email')->nullable();

            // Bank Details (useful for refunds or direct payments)
            $table->string('bank_account_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_ifsc')->nullable();
            $table->string('bank_name')->nullable();

            // Business Details
            $table->enum('business_type', ['individual', 'proprietorship', 'partnership', 'private_limited', 'public_limited', 'other'])->nullable();

            // Credit Limit
            $table->decimal('credit_limit', 15, 2)->default(0);

            // Notes / Remarks
            $table->text('notes')->nullable();

            // Status (active/inactive)
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
