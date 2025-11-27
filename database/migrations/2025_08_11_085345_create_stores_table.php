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
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Store name
            $table->string('code')->unique(); // Short code for store
            $table->string('location')->nullable(); // General location
            $table->string('address')->nullable(); // Full address
            $table->string('city')->nullable();
            $table->string('state')->nullable(); // State (important for GST)
            $table->string('pincode', 6)->nullable(); // Indian PIN code
            $table->string('country')->default('India');

            // Accounting Details

            $table->string('account_holder_name')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('ifsc_code')->nullable();
            $table->string('account_type')->nullable(); // savings/current
            $table->string('branch_name')->nullable();

            // GST & Tax Info
            $table->string('gst_number', 15)->nullable(); // GSTIN format: 15 chars
            $table->string('pan_number', 10)->nullable(); // PAN for store entity
            $table->decimal('default_tax_rate', 5, 2)->default(0.00); // default GST %

            // Contact Info
            $table->string('phone', 15)->nullable();
            $table->string('email')->nullable();

            // Store Settings
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->json('settings')->nullable(); // JSON for store-specific settings
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
