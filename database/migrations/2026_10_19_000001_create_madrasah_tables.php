<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('full_name', 120);
            $table->string('username', 60)->unique();
            $table->string('password');
            $table->enum('role', ['admin', 'petugas', 'wali_kelas', 'bp', 'kepala'])->default('petugas');
            $table->boolean('active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('school_settings', function (Blueprint $table): void {
            $table->string('key', 80)->primary();
            $table->text('value')->nullable();
        });
        Schema::create('classes', function (Blueprint $table): void {
            $table->id();
            $table->string('class_name', 60);
            $table->string('academic_year', 9);
            $table->string('homeroom_teacher', 120)->nullable();
            $table->timestamps();
            $table->unique(['class_name', 'academic_year']);
        });
        Schema::create('students', function (Blueprint $table): void {
            $table->id();
            $table->string('nis', 40)->unique();
            $table->string('nisn', 30)->nullable();
            $table->string('full_name', 150);
            $table->enum('gender', ['L', 'P']);
            $table->foreignId('class_id')->constrained('classes')->restrictOnDelete();
            $table->boolean('active')->default(true);
            $table->enum('student_status', ['Aktif', 'Mutasi', 'Berhenti', 'Lulus'])->default('Aktif');
            $table->timestamps();
        });
        Schema::create('student_class_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('old_class_id')->constrained('classes')->restrictOnDelete();
            $table->foreignId('new_class_id')->constrained('classes')->restrictOnDelete();
            $table->string('academic_year_from', 9);
            $table->string('academic_year_to', 9);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('weekly_absence', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('class_id')->constrained('classes')->restrictOnDelete();
            $table->string('academic_year', 9);
            $table->date('week_start');
            $table->unsignedSmallInteger('alpa_jp')->default(0);
            $table->unsignedSmallInteger('izin_jp')->default(0);
            $table->unsignedSmallInteger('sakit_jp')->default(0);
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['student_id', 'academic_year', 'week_start'], 'unique_student_week_year');
            $table->index(['academic_year', 'week_start']);
            $table->index(['class_id', 'week_start']);
        });
        Schema::create('disciplinary_letters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->string('academic_year', 9);
            $table->enum('semester', ['Ganjil', 'Genap']);
            $table->unsignedTinyInteger('level');
            $table->string('letter_number', 100);
            $table->date('letter_date');
            $table->date('meeting_date')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['draft', 'issued', 'completed'])->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['student_id', 'academic_year', 'semester', 'level'], 'unique_semester_letter_level');
        });
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 80);
            $table->string('detail', 500)->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('disciplinary_letters');
        Schema::dropIfExists('weekly_absence');
        Schema::dropIfExists('student_class_history');
        Schema::dropIfExists('students');
        Schema::dropIfExists('classes');
        Schema::dropIfExists('school_settings');
        Schema::dropIfExists('users');
    }
};
