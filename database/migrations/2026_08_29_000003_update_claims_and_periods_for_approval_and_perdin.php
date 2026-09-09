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
            // Entertain sub-type
            $table->string('entertain_subtype')->nullable()->after('purpose'); // 'Makan', 'Lainnya'

            // Perjalanan Dinas (Perdin) detail fields
            $table->boolean('is_perdin')->default(false)->after('entertain_subtype');
            $table->string('homebase')->nullable()->after('is_perdin');
            $table->string('destination_city')->nullable()->after('homebase');
            $table->decimal('distance_km', 10, 2)->nullable()->after('destination_city');
            $table->integer('days_count')->default(1)->after('distance_km');
            $table->integer('nights_count')->default(0)->after('days_count');
            $table->decimal('meal_allowance', 15, 2)->default(0)->after('nights_count');
            $table->decimal('lodging_allowance', 15, 2)->default(0)->after('meal_allowance');
            $table->decimal('toll_cost', 15, 2)->default(0)->after('lodging_allowance');
            $table->decimal('fuel_cost', 15, 2)->default(0)->after('toll_cost');
            $table->decimal('car_rental_cost', 15, 2)->default(0)->after('fuel_cost');
            $table->decimal('service_cost', 15, 2)->default(0)->after('car_rental_cost');

            // BBM Before & After photos
            $table->string('bbm_photo_before')->nullable()->after('photos');
            $table->string('bbm_photo_after')->nullable()->after('bbm_photo_before');
            $table->string('bbm_photo_combined')->nullable()->after('bbm_photo_after');

            // Workflow & Approval fields
            $table->string('approval_status')->default('DRAFT')->after('bbm_photo_combined');
            $table->foreignId('approved_by_asm_id')->nullable()->after('approval_status')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_by_asm_at')->nullable()->after('approved_by_asm_id');
            $table->foreignId('approved_by_rgm_id')->nullable()->after('approved_by_asm_at')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_by_rgm_at')->nullable()->after('approved_by_rgm_id');
            $table->foreignId('approved_by_jejen_id')->nullable()->after('approved_by_rgm_at')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_by_jejen_at')->nullable()->after('approved_by_jejen_id');
            $table->text('rejection_reason')->nullable()->after('approved_by_jejen_at');

            // Disbursement status (Sudah/Belum Dicairkan)
            $table->string('disbursement_status')->default('Belum Dicairkan')->after('rejection_reason');
            $table->timestamp('disbursed_at')->nullable()->after('disbursement_status');
        });

        Schema::table('claim_periods', function (Blueprint $table) {
            $table->string('approval_status')->default('DRAFT')->after('status');
            $table->string('disbursement_status')->default('Belum Dicairkan')->after('approval_status');
            $table->timestamp('disbursed_at')->nullable()->after('disbursement_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('claims', function (Blueprint $table) {
            $table->dropForeign(['approved_by_asm_id']);
            $table->dropForeign(['approved_by_rgm_id']);
            $table->dropForeign(['approved_by_jejen_id']);
            $table->dropColumn([
                'entertain_subtype',
                'is_perdin',
                'homebase',
                'destination_city',
                'distance_km',
                'days_count',
                'nights_count',
                'meal_allowance',
                'lodging_allowance',
                'toll_cost',
                'fuel_cost',
                'car_rental_cost',
                'service_cost',
                'bbm_photo_before',
                'bbm_photo_after',
                'bbm_photo_combined',
                'approval_status',
                'approved_by_asm_id',
                'approved_by_asm_at',
                'approved_by_rgm_id',
                'approved_by_rgm_at',
                'approved_by_jejen_id',
                'approved_by_jejen_at',
                'rejection_reason',
                'disbursement_status',
                'disbursed_at',
            ]);
        });

        Schema::table('claim_periods', function (Blueprint $table) {
            $table->dropColumn(['approval_status', 'disbursement_status', 'disbursed_at']);
        });
    }
};
