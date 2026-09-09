<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('attendance_docs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claim_id')->nullable()->constrained('claims')->cascadeOnDelete();
            $table->foreignId('claim_period_id')->nullable()->constrained('claim_periods')->cascadeOnDelete();
            $table->string('purpose'); // Perihal / Keperluan acara
            $table->date('date'); // Tanggal kegiatan
            $table->string('place'); // Tempat kegiatan
            $table->string('file_path')->nullable(); // Path generated Word .docx
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_docs');
    }
};
