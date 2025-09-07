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
        Schema::create('blocks', function (Blueprint $table) {
            $table->id();

            // Relation to Floor
            $table->foreignId('floor_id')->constrained()->cascadeOnDelete();

            // Relation to Store
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();

            // Identification
            $table->string('name'); // e.g. "Block A", "Rack B1"
            $table->string('code')->nullable(); // short unique code per floor

            // Metadata
            $table->string('zone')->nullable(); // optional zoning (e.g. "North Wing", "Zone C")
            $table->enum('type', ['rack', 'room', 'shelf', 'area'])->default('rack');
            $table->unsignedInteger('capacity')->nullable(); // max stock capacity (for warehouse mgmt)
            $table->text('description')->nullable();

            // Status / Config
            $table->enum('status', ['active', 'inactive', 'under_maintenance'])->default('active');
            $table->json('settings')->nullable(); // for storing automation rules, temperature settings, etc

            $table->timestamps();

            // Ensure code uniqueness per floor
            $table->unique(['floor_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blocks');
    }
};
