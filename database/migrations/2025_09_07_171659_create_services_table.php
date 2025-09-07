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
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('service_type');
            $table->string('category')->nullable();
            $table->string('condition')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->integer('duration')->nullable()->comment('Duration in minutes or hours depending on context');
            $table->tinyInteger('priority')->default(0)->comment('Higher value means higher priority');
            $table->boolean('is_active')->default(true)->comment('Service availability status');
            $table->json('tags')->nullable()->comment('Keywords or labels');
            $table->json('meta')->nullable()->comment('Additional metadata like vendor details, notes, etc.');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
