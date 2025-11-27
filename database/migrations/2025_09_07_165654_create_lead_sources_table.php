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
        Schema::create('lead_sources', function (Blueprint $table) {
            $table->id();
            $table->string('lead_name');
            $table->string('lead_email');
            $table->string('lead_phone_number');
            $table->string('lead_type');

            $table->string('lead_code')->unique();
            $table->string('account_holder_name')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('ifsc_code')->nullable();
            $table->string('account_type')->nullable(); // savings/current
            $table->string('branch_name')->nullable();

            $table->decimal('lead_incentive', 5, 2)->default(0);
            $table->string('campaign_name')->nullable();
            $table->string('keyword')->nullable();
            $table->string('landing_page_url')->nullable();
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('lead_status')->default('new');
            $table->text('note')->nullable();
            $table->text('other')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_sources');
    }
};
