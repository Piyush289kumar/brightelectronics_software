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
        Schema::create('payment_advices', function (Blueprint $table) {
            $table->id();
            $table->string('date');
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->constrained('invoices')->cascadeOnDelete();
            $table->string('invoice_id')->nullable();
            $table->decimal('invoice_amount', 12, 2)->default(0);
            $table->string('payment_doc_no')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_advice');
    }
};
