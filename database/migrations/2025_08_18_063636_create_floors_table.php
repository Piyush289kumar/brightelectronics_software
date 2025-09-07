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
        Schema::create('floors', function (Blueprint $table) {
            $table->id();

            // Relation to Store
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();

            // Identification
            $table->string('name'); // e.g. "Ground Floor", "1st Floor"
            $table->string('code')->nullable(); // e.g. "GF", "F1"

            // Metadata
            $table->unsignedInteger('level')->nullable(); // for sorting: 0 = Ground, 1 = First, etc
            $table->enum('type', ['retail', 'storage', 'office', 'mixed'])->default('retail'); // flexible categorization
            $table->text('description')->nullable();

            // Status / Config
            $table->enum('status', ['active', 'inactive', 'under_maintenance'])->default('active');
            $table->json('settings')->nullable(); // for future custom configurations (like opening hours, access rules)

            $table->timestamps();

            // Ensure code uniqueness per store
            $table->unique(['store_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('floors');
    }
};
