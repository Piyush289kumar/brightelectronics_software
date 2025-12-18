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
            $table->date('date');
            $table->date('payment_advice_start_date');
            $table->date('payment_advice_end_date');
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->string('invoice')->nullable();
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
