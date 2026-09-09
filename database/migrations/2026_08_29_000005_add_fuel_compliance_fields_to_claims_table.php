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
        Schema::table('claims', function (Blueprint $table) {
            $table->string('vehicle_type')->nullable()->default('Mobil')->after('entertain_subtype'); // Mobil / Motor
            $table->string('fuel_type')->nullable()->default('Pertalite')->after('vehicle_type'); // Pertalite / Pertamax / Solar / Lainnya
            $table->decimal('fuel_base_amount', 15, 2)->nullable()->default(0)->after('fuel_type');
            $table->decimal('fuel_extra_amount', 15, 2)->nullable()->default(5000)->after('fuel_base_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('claims', function (Blueprint $table) {
            $table->dropColumn(['vehicle_type', 'fuel_type', 'fuel_base_amount', 'fuel_extra_amount']);
        });
    }
};
