<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('opening_absence', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('class_id')->constrained('classes')->restrictOnDelete();
            $table->string('academic_year', 9);
            $table->enum('semester', ['Ganjil', 'Genap']);
            $table->date('as_of_date');
            $table->unsignedSmallInteger('sakit_jp')->default(0);
            $table->unsignedSmallInteger('izin_jp')->default(0);
            $table->unsignedSmallInteger('alpa_jp')->default(0);
            $table->string('note', 255)->nullable();
            $table->timestamps();
            $table->unique(['student_id', 'academic_year', 'semester'], 'opening_absence_student_year_semester_unique');
            $table->index(['academic_year', 'as_of_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opening_absence');
    }
};
