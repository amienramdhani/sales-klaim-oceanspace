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
        Schema::create('budget_types', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Entertain, BBM, Transportasi, Perjalanan Dinas, Service Motor
            $table->string('code')->unique(); // ENTERTAIN, BBM, TRANSPORTASI, etc.
            $table->text('description')->nullable();
            $table->string('status')->default('Aktif'); // Aktif / Nonaktif
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('budget_types');
    }
};
