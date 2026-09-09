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
        Schema::create('claim_periods', function (Blueprint $table) {
            $table->id();
            $table->string('period_number')->unique(); // e.g. CLM/2026/08/001
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('month'); // e.g. "Agustus" or "08"
            $table->integer('year'); // e.g. 2026
            $table->date('submission_date'); // Tanggal Pengajuan
            $table->decimal('budget_claim', 15, 2)->default(0); // Budget Claim
            $table->decimal('already_claimed', 15, 2)->default(0); // Sudah Claim
            $table->decimal('total_claim', 15, 2)->default(0); // Biaya Claim (Auto Sum)
            $table->decimal('over_budget', 15, 2)->default(0); // Over Budget
            $table->string('status')->default('DRAFT'); // DRAFT, SELESAI, DIAJUKAN
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('claim_periods');
    }
};
