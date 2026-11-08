<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->string('nis', 40)->nullable()->change();
            $table->enum('boarding_status', ['Tidak', 'Mondok'])->default('Tidak');
            $table->string('boarding_school', 120)->nullable();
            $table->date('status_date')->nullable();
            $table->string('status_note', 255)->nullable();
        });
        Schema::create('monthly_absence', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('class_id')->constrained('classes')->restrictOnDelete();
            $table->string('academic_year', 9);
            $table->date('month_start');
            $table->unsignedSmallInteger('alpa_jp')->default(0);
            $table->unsignedSmallInteger('izin_jp')->default(0);
            $table->unsignedSmallInteger('sakit_jp')->default(0);
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['student_id', 'academic_year', 'month_start'], 'unique_student_month_year');
            $table->index(['academic_year', 'month_start']);
        });
        Schema::create('student_status_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->string('old_status', 30);
            $table->string('new_status', 30);
            $table->date('effective_date');
            $table->string('note', 255)->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('disciplinary_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->string('academic_year', 9);
            $table->enum('semester', ['Ganjil', 'Genap']);
            $table->unsignedTinyInteger('level');
            $table->enum('status', ['pending', 'processed', 'completed'])->default('pending');
            $table->date('action_date')->nullable();
            $table->string('note', 500)->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['student_id', 'academic_year', 'semester', 'level'], 'unique_student_disciplinary_action');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('disciplinary_actions');
        Schema::dropIfExists('student_status_history');
        Schema::dropIfExists('monthly_absence');
        Schema::table('students', function (Blueprint $table): void {
            $table->dropColumn(['boarding_status', 'boarding_school', 'status_date', 'status_note']);
            // NIS is intentionally left nullable: restoring NOT NULL would break existing students without NIS.
        });
    }
};
