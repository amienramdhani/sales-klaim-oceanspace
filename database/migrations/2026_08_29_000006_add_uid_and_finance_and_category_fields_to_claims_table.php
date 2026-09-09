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
            if (!Schema::hasColumn('claims', '_uid')) {
                $table->string('_uid')->nullable()->index()->after('id');
            }
            if (!Schema::hasColumn('claims', 'claim_category')) {
                $table->string('claim_category')->nullable()->default('transport_entertain')->after('_uid');
            }
            if (!Schema::hasColumn('claims', 'fuel_pertalite_price')) {
                $table->decimal('fuel_pertalite_price', 10, 2)->nullable()->default(10000)->after('fuel_type');
            }
            if (!Schema::hasColumn('claims', 'fuel_pertamax_price')) {
                $table->decimal('fuel_pertamax_price', 10, 2)->nullable()->default(12300)->after('fuel_pertalite_price');
            }
            if (!Schema::hasColumn('claims', 'fuel_liters')) {
                $table->decimal('fuel_liters', 10, 3)->nullable()->default(0)->after('fuel_pertamax_price');
            }
            if (!Schema::hasColumn('claims', 'transfer_proof_photo')) {
                $table->string('transfer_proof_photo')->nullable()->after('disbursed_at');
            }
            if (!Schema::hasColumn('claims', 'finance_approved_by_id')) {
                $table->foreignId('finance_approved_by_id')->nullable()->constrained('users')->nullOnDelete()->after('transfer_proof_photo');
            }
            if (!Schema::hasColumn('claims', 'finance_approved_at')) {
                $table->timestamp('finance_approved_at')->nullable()->after('finance_approved_by_id');
            }
            if (!Schema::hasColumn('claims', 'ba_phone')) {
                $table->string('ba_phone')->nullable()->after('rejection_reason');
            }
            if (!Schema::hasColumn('claims', 'ba_division')) {
                $table->string('ba_division')->nullable()->after('ba_phone');
            }
            if (!Schema::hasColumn('claims', 'ba_approver_1')) {
                $table->string('ba_approver_1')->nullable()->default('Agus Supangat')->after('ba_division');
            }
            if (!Schema::hasColumn('claims', 'ba_approver_2')) {
                $table->string('ba_approver_2')->nullable()->default('Alb. Maria Adi Nugroho')->after('ba_approver_1');
            }
            if (!Schema::hasColumn('claims', 'ba_approver_3')) {
                $table->string('ba_approver_3')->nullable()->default('Yoga Prima Hadi')->after('ba_approver_2');
            }
            if (!Schema::hasColumn('claims', 'ba_wa_proof_photo')) {
                $table->string('ba_wa_proof_photo')->nullable()->after('ba_approver_3');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('claims', function (Blueprint $table) {
            $table->dropForeign(['finance_approved_by_id']);
            $table->dropColumn([
                '_uid',
                'claim_category',
                'fuel_pertalite_price',
                'fuel_pertamax_price',
                'fuel_liters',
                'transfer_proof_photo',
                'finance_approved_by_id',
                'finance_approved_at',
                'ba_phone',
                'ba_division',
                'ba_approver_1',
                'ba_approver_2',
                'ba_approver_3',
                'ba_wa_proof_photo',
            ]);
        });
    }
};
