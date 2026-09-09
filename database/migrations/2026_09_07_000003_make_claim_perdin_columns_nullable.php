<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('claims', function (Blueprint $table) {
            $table->decimal('meal_allowance', 15, 2)->nullable()->default(0)->change();
            $table->decimal('lodging_allowance', 15, 2)->nullable()->default(0)->change();
            $table->decimal('toll_cost', 15, 2)->nullable()->default(0)->change();
            $table->decimal('fuel_cost', 15, 2)->nullable()->default(0)->change();
            $table->decimal('car_rental_cost', 15, 2)->nullable()->default(0)->change();
            $table->decimal('service_cost', 15, 2)->nullable()->default(0)->change();
            $table->integer('days_count')->nullable()->default(1)->change();
            $table->integer('nights_count')->nullable()->default(0)->change();
            $table->decimal('amount', 15, 2)->nullable()->default(0)->change();
        });
    }

    public function down(): void
    {
    }
};
