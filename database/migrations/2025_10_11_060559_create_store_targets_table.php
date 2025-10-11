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
        Schema::create('store_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('year')->index();
            $table->tinyInteger('month')->comment('1-12')->index();
            $table->decimal('amount', 15, 2)->default(0.00);
            $table->decimal('collected_amount', 15, 2)->default(0.00);
            $table->decimal('previous_remaining_sum', 15, 2)->nullable();
            $table->boolean('include_previous')->default(false);
            $table->boolean('distributed')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['store_id', 'year', 'month'], 'store_month_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_targets');
    }
};
