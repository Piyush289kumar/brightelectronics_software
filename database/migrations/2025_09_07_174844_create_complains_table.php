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
        Schema::create('complains', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('mobile')->nullable();
            $table->string('customer_email')->nullable();
            $table->text('address')->nullable();
            $table->string('google_map_location')->nullable(); // Default can be set via frontend JS
            $table->foreignId('lead_source_id')->constrained('lead_sources')->onDelete('cascade');
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('complain_id')->unique();
            $table->json('product_id')->nullable(); // Multi-select devices (Products)
            $table->json('size')->nullable();   // Multi-select sizes
            $table->json('service_type')->nullable(); // Multi-select services
            $table->string('first_action_code')->default('NEW');
            $table->timestamp('rsd_time')->nullable(); // Reschedule visit
            $table->timestamp('pkd_time')->nullable();
            $table->timestamp('visit_time')->nullable();
            $table->text('cancel_reason')->nullable(); // Job Cancel reason
            $table->string('status')->default('Pending');
            $table->string('pon')->nullable();
            $table->decimal('estimate_repair_amount', 10, 2)->nullable(); // Repair cost
            $table->decimal('estimate_new_amount', 10, 2)->nullable();    // New cost
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('assigned_engineers')->nullable(); // List of engineer IDs
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('complains');
    }
};
