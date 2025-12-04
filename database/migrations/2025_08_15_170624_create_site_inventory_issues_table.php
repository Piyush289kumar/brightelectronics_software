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
        Schema::create('site_inventory_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('job_card_id')->constrained()->restrictOnDelete();
            $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();

            $table->enum('status', ['issued', 'returned', 'damaged'])->default('issued')->index();
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();

            $table->integer('return_qty')->default(0);
            $table->foreignId('returned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('returned_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_inventory_issues');
    }
};
