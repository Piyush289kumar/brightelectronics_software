<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('job_cards', function (Blueprint $table) {
            $table->id();
             $table->foreignId('complain_id')->constrained()->cascadeOnDelete();
            $table->string('job_id')->unique();
            $table->string('status')->default('Open');
            $table->decimal('amount', 10, 2)->nullable();
            $table->decimal('gst_amount', 10, 2)->nullable();
            $table->decimal('expense', 10, 2)->nullable();
            $table->decimal('gross_amount', 10, 2)->nullable();
            $table->string('incentive_type')->nullable();
            $table->decimal('incentive_amount', 10, 2)->nullable();
            $table->decimal('net_profit', 10, 2)->nullable();
            $table->decimal('lead_incentive_amount', 10, 2)->nullable();
            $table->decimal('bright_electronics_profit', 10, 2)->nullable();
            $table->string('job_verified_by_admin')->nullable();
            $table->text('note')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_cards');
    }
};
