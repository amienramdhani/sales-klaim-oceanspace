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
        Schema::table('positions', function (Blueprint $table) {
            if (!Schema::hasColumn('positions', 'bbm_budget')) {
                $table->decimal('bbm_budget', 15, 2)->default(0)->after('entertain_budget');
            }
            if (!Schema::hasColumn('positions', 'perdin_budget')) {
                $table->decimal('perdin_budget', 15, 2)->default(0)->after('bbm_budget');
            }
            if (!Schema::hasColumn('positions', 'service_motor_budget')) {
                $table->decimal('service_motor_budget', 15, 2)->default(0)->after('perdin_budget');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('positions', function (Blueprint $table) {
            $table->dropColumn(['bbm_budget', 'perdin_budget', 'service_motor_budget']);
        });
    }
};
