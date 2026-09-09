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
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // RGM, ASM, Staff, etc.
            $table->decimal('entertain_budget', 15, 2)->default(0); // 2.400.000, 1.500.000
            $table->decimal('operational_budget', 15, 2)->default(0); // Operasional / BBM / Transport
            $table->string('signature_image')->nullable(); // Uploaded digital signature image
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('positions');
    }
};
