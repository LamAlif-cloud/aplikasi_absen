<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('import_issues', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->string('academic_year', 9);
            $table->string('input_unit', 10)->default('jp');
            $table->string('filename');
            $table->unsignedInteger('row_number');
            $table->json('row_data');
            $table->text('error');
            $table->timestamp('resolved_at')->nullable()->index();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('import_issues'); }
};
