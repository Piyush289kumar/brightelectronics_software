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
        Schema::create('payment_advice_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('payment_advice_id')->constrained('payment_advices')->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->constrained('invoices')->cascadeOnDelete();
            
            $table->date('po_date')->nullable();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('payment_doc_no')->nullable();
            
            $table->string('invoice_no')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_advice_items');
    }
};
