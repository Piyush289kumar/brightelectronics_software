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
        Schema::create('attendance_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // Reporting Period
            $table->date('from_date');
            $table->date('to_date');

            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');

            // Attendance Summary
            $table->unsignedSmallInteger('working_days')->default(0);
            $table->unsignedSmallInteger('present_count')->default(0);
            $table->unsignedSmallInteger('absent_count')->default(0);
            $table->unsignedSmallInteger('leave_count')->default(0);
            $table->unsignedSmallInteger('half_day_count')->default(0);
            $table->unsignedSmallInteger('late_punch_count')->default(0);

            // Optional Payroll Support
            $table->decimal('overtime_hours', 8, 2)->default(0);

            // Attachment
            $table->string('pdf_file')->nullable();

            // Draft / Approved
            $table->enum('status', [
                'draft',
                'submitted',
                'approved',
                'rejected'
            ])->default('draft');

            $table->text('remarks')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['store_id']);
            $table->index(['user_id']);
            $table->index(['month', 'year']);

            $table->unique([
                'user_id',
                'month',
                'year'
            ], 'attendance_unique_month');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_reports');
    }
};
