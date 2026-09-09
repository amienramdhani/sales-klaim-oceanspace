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
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Admin, RGM, ASM, ASC, DSF, CSO, Pak Jejen / Management
            $table->string('code')->unique(); // ADMIN, RGM, ASM, ASC, DSF, CSO, JEJEN
            $table->text('description')->nullable();
            
            // Plafon Budget Standar
            $table->decimal('entertain_budget', 15, 2)->default(0); // e.g. RGM: 2.400.000, ASM: 1.500.000
            $table->decimal('operational_budget', 15, 2)->default(0);
            
            // Ketentuan Biaya Operasional / Perdin per Role
            $table->decimal('meal_allowance_per_day', 15, 2)->default(0); // RGM: 100k, ASM: 75k, ASC: 50k, DSF: 35k
            $table->decimal('lodging_allowance_per_night', 15, 2)->default(0); // RGM: 300k, ASM: 250k, ASC: 150-200k, DSF: 150k
            $table->decimal('toll_allowance', 15, 2)->default(500000); // 500k by klaim
            $table->decimal('fuel_budget_default', 15, 2)->default(0); // Customizable per daerah
            $table->decimal('car_rental_budget_default', 15, 2)->default(0); // Kompensasi sewa mobil
            $table->decimal('service_car_budget_quarterly', 15, 2)->default(2000000); // Rp 2jt / 3 bulan
            $table->decimal('service_motor_budget_quarterly', 15, 2)->default(500000); // Rp 500rb / 3 bulan

            $table->string('status')->default('Aktif');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
