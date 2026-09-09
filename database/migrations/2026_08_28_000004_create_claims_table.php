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
        Schema::create('claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claim_period_id')->constrained('claim_periods')->cascadeOnDelete();
            $table->date('claim_date'); // Tanggal transaksi
            $table->string('claim_type'); // ENTERTAIN, BBM, TRANSPORTASI
            $table->string('purpose'); // Keperluan
            $table->text('note')->nullable(); // Keterangan / tempat / detail
            $table->decimal('amount', 15, 2); // Nominal
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('brand')->nullable(); // Brand terkait
            $table->string('reffnote')->nullable(); // Referensi / Catatan branch
            $table->string('city')->nullable(); // Kota transaksi
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('claims');
    }
};
