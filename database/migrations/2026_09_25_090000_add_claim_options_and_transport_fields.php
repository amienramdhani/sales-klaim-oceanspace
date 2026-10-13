<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('claims', function (Blueprint $table) {
            if (!Schema::hasColumn('claims', 'travel_cost')) {
                $table->decimal('travel_cost', 12, 2)->nullable()->default(0)->after('fuel_cost');
            }
            if (!Schema::hasColumn('claims', 'actual_travel_cost')) {
                $table->decimal('actual_travel_cost', 12, 2)->nullable()->default(0)->after('actual_fuel_cost');
            }
            if (!Schema::hasColumn('claims', 'other_transport_cost')) {
                $table->decimal('other_transport_cost', 12, 2)->nullable()->default(0)->after('car_rental_cost');
            }
            if (!Schema::hasColumn('claims', 'actual_other_transport_cost')) {
                $table->decimal('actual_other_transport_cost', 12, 2)->nullable()->default(0)->after('actual_car_rental_cost');
            }
            if (!Schema::hasColumn('claims', 'custom_bank_name')) {
                $table->string('custom_bank_name', 100)->nullable()->after('amount');
            }
            if (!Schema::hasColumn('claims', 'custom_bank_account_number')) {
                $table->string('custom_bank_account_number', 100)->nullable()->after('custom_bank_name');
            }
            if (!Schema::hasColumn('claims', 'custom_bank_account_name')) {
                $table->string('custom_bank_account_name', 255)->nullable()->after('custom_bank_account_number');
            }
            if (!Schema::hasColumn('claims', 'approval_note')) {
                $table->text('approval_note')->nullable()->after('rejection_reason');
            }
            if (!Schema::hasColumn('claims', 'asm_approval_note')) {
                $table->text('asm_approval_note')->nullable()->after('approval_note');
            }
            if (!Schema::hasColumn('claims', 'rgm_approval_note')) {
                $table->text('rgm_approval_note')->nullable()->after('asm_approval_note');
            }
            if (!Schema::hasColumn('claims', 'admin_approval_note')) {
                $table->text('admin_approval_note')->nullable()->after('rgm_approval_note');
            }
            if (!Schema::hasColumn('claims', 'jejen_approval_note')) {
                $table->text('jejen_approval_note')->nullable()->after('admin_approval_note');
            }
            if (!Schema::hasColumn('claims', 'yoga_approval_note')) {
                $table->text('yoga_approval_note')->nullable()->after('jejen_approval_note');
            }
            if (!Schema::hasColumn('claims', 'nurbaiti_approval_note')) {
                $table->text('nurbaiti_approval_note')->nullable()->after('yoga_approval_note');
            }
            if (!Schema::hasColumn('claims', 'thia_approval_note')) {
                $table->text('thia_approval_note')->nullable()->after('nurbaiti_approval_note');
            }
        });

        // Update CV COMPLETE SELULAR -> CV TOP SELULAR
        try {
            DB::table('claims')->where('badan_usaha', 'like', '%COMPLETE%')->update(['badan_usaha' => 'CV TOP SELULAR']);
            DB::table('claims')->where('company', 'like', '%COMPLETE%')->update(['company' => 'CV TOP SELULAR']);

            DB::table('employees')->where('badan_usaha', 'like', '%COMPLETE%')->update(['badan_usaha' => 'CV TOP SELULAR']);
            DB::table('employees')->where('company', 'like', '%COMPLETE%')->update(['company' => 'CV TOP SELULAR']);

            DB::table('users')->where('badan_usaha', 'like', '%COMPLETE%')->update(['badan_usaha' => 'CV TOP SELULAR']);
            DB::table('users')->where('company', 'like', '%COMPLETE%')->update(['company' => 'CV TOP SELULAR']);
        } catch (\Throwable $e) {
            // Ignore if tables are empty
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('claims', function (Blueprint $table) {
            $table->dropColumn([
                'travel_cost',
                'actual_travel_cost',
                'other_transport_cost',
                'actual_other_transport_cost',
                'custom_bank_name',
                'custom_bank_account_number',
                'custom_bank_account_name',
                'approval_note',
                'asm_approval_note',
                'rgm_approval_note',
                'admin_approval_note',
                'jejen_approval_note',
                'yoga_approval_note',
                'nurbaiti_approval_note',
                'thia_approval_note',
            ]);
        });
    }
};
