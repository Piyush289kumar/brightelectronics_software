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
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();

            // Site Details
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('location')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('pincode', 10)->nullable();
            $table->string('country')->default('India');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();

            // Operational Info
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->time('opening_time')->nullable();
            $table->time('closing_time')->nullable();
            $table->json('settings')->nullable(); // Flexible settings
            $table->text('notes')->nullable(); // Optional notes or description

            $table->timestamps();
            $table->softDeletes(); // For safe removal without deleting
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
