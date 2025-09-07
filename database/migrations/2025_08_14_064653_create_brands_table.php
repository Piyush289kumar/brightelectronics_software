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
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // Brand Name
            $table->string('slug')->unique(); // URL-friendly brand name

            // Contact & Vendor Info
            $table->string('owner_name')->nullable(); // Brand Owner
            $table->string('contact_number', 15)->nullable();
            $table->string('email')->nullable();

            // Address (Indian context)
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('pincode', 6)->nullable();

            // GST & Compliance
            $table->string('gst_number', 15)->nullable()->unique();
            $table->string('pan_number', 10)->nullable()->unique();

            // Branding & Media
            $table->string('logo_path')->nullable(); // Upload to public storage
            $table->text('description')->nullable();

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
        Schema::dropIfExists('brands');
    }
};
