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
            if (!Schema::hasColumn('claims', 'company')) {
                $table->string('company')->nullable()->default('PT MEDIA SELULAR INDONESIA')->after('claim_category');
            }
            if (!Schema::hasColumn('claims', 'badan_usaha')) {
                $table->string('badan_usaha')->nullable()->default('PT MEDIA SELULAR INDONESIA')->after('company');
            }
            if (!Schema::hasColumn('claims', 'actual_fuel_cost')) {
                $table->decimal('actual_fuel_cost', 12, 2)->nullable()->default(0)->after('fuel_cost');
            }
            if (!Schema::hasColumn('claims', 'actual_toll_cost')) {
                $table->decimal('actual_toll_cost', 12, 2)->nullable()->default(0)->after('toll_cost');
            }
            if (!Schema::hasColumn('claims', 'actual_car_rental_cost')) {
                $table->decimal('actual_car_rental_cost', 12, 2)->nullable()->default(0)->after('car_rental_cost');
            }
            if (!Schema::hasColumn('claims', 'actual_service_cost')) {
                $table->decimal('actual_service_cost', 12, 2)->nullable()->default(0)->after('service_cost');
            }
            if (!Schema::hasColumn('claims', 'actual_meal_allowance')) {
                $table->decimal('actual_meal_allowance', 12, 2)->nullable()->default(0)->after('meal_allowance');
            }
            if (!Schema::hasColumn('claims', 'actual_amount_total')) {
                $table->decimal('actual_amount_total', 12, 2)->nullable()->default(0)->after('amount');
            }
            if (!Schema::hasColumn('claims', 'remaining_amount_calculated')) {
                $table->decimal('remaining_amount_calculated', 12, 2)->nullable()->default(0)->after('return_transfer_amount');
            }
            if (!Schema::hasColumn('claims', 'nota_balik_status')) {
                $table->string('nota_balik_status')->nullable()->default('Belum Diserahkan')->after('nota_balik_submitted');
            }
            if (!Schema::hasColumn('claims', 'nota_balik_rejection_reason')) {
                $table->text('nota_balik_rejection_reason')->nullable()->after('nota_balik_status');
            }
            if (!Schema::hasColumn('claims', 'approved_by_yoga_id')) {
                $table->unsignedBigInteger('approved_by_yoga_id')->nullable()->after('approved_by_jejen_at');
            }
            if (!Schema::hasColumn('claims', 'approved_by_yoga_at')) {
                $table->timestamp('approved_by_yoga_at')->nullable()->after('approved_by_yoga_id');
            }
            if (!Schema::hasColumn('claims', 'approved_by_nurbaiti_id')) {
                $table->unsignedBigInteger('approved_by_nurbaiti_id')->nullable()->after('approved_by_yoga_at');
            }
            if (!Schema::hasColumn('claims', 'approved_by_nurbaiti_at')) {
                $table->timestamp('approved_by_nurbaiti_at')->nullable()->after('approved_by_nurbaiti_id');
            }
            if (!Schema::hasColumn('claims', 'approved_by_thia_id')) {
                $table->unsignedBigInteger('approved_by_thia_id')->nullable()->after('approved_by_nurbaiti_at');
            }
            if (!Schema::hasColumn('claims', 'approved_by_thia_at')) {
                $table->timestamp('approved_by_thia_at')->nullable()->after('approved_by_thia_id');
            }
        });

        Schema::table('employees', function (Blueprint $table) {
            if (!Schema::hasColumn('employees', 'managed_branches')) {
                $table->json('managed_branches')->nullable()->after('region');
            }
            if (!Schema::hasColumn('employees', 'company')) {
                $table->string('company')->nullable()->default('PT MEDIA SELULAR INDONESIA')->after('managed_branches');
            }
            if (!Schema::hasColumn('employees', 'badan_usaha')) {
                $table->string('badan_usaha')->nullable()->default('PT MEDIA SELULAR INDONESIA')->after('company');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'managed_branches')) {
                $table->json('managed_branches')->nullable()->after('managed_regions');
            }
            if (!Schema::hasColumn('users', 'company')) {
                $table->string('company')->nullable()->default('PT MEDIA SELULAR INDONESIA')->after('managed_branches');
            }
            if (!Schema::hasColumn('users', 'badan_usaha')) {
                $table->string('badan_usaha')->nullable()->default('PT MEDIA SELULAR INDONESIA')->after('company');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('claims', function (Blueprint $table) {
            $table->dropColumn([
                'company',
                'badan_usaha',
                'actual_fuel_cost',
                'actual_toll_cost',
                'actual_car_rental_cost',
                'actual_service_cost',
                'actual_meal_allowance',
                'actual_amount_total',
                'remaining_amount_calculated',
                'nota_balik_status',
                'nota_balik_rejection_reason',
                'approved_by_yoga_id',
                'approved_by_yoga_at',
                'approved_by_nurbaiti_id',
                'approved_by_nurbaiti_at',
                'approved_by_thia_id',
                'approved_by_thia_at',
            ]);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['managed_branches', 'company', 'badan_usaha']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['managed_branches', 'company', 'badan_usaha']);
        });
    }
};
