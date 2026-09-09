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
        Schema::create('attendance_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_doc_id')->constrained('attendance_docs')->cascadeOnDelete();
            $table->string('name'); // Nama Peserta
            $table->string('position')->nullable(); // Jabatan / Instansi
            $table->string('signature_info')->nullable(); // Keterangan tanda tangan
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_parts');
    }
};
