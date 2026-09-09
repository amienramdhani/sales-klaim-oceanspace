<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->decimal('bbm_budget', 15, 2)->nullable()->default(0)->change();
            $table->decimal('entertain_budget', 15, 2)->nullable()->default(null)->change();
            $table->decimal('perdin_budget', 15, 2)->nullable()->default(0)->change();
            $table->decimal('transport_budget', 15, 2)->nullable()->default(0)->change();
            $table->decimal('perdin_meal_allowance', 15, 2)->nullable()->default(100000)->change();
            $table->decimal('perdin_lodging_allowance', 15, 2)->nullable()->default(250000)->change();
            $table->decimal('perdin_transport_budget', 15, 2)->nullable()->default(500000)->change();
        });
    }

    public function down(): void
    {
    }
};
