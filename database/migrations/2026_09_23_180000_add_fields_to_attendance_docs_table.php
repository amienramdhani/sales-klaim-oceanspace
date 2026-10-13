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
        Schema::table('attendance_docs', function (Blueprint $table) {
            if (!Schema::hasColumn('attendance_docs', 'category')) {
                $table->string('category')->nullable()->default('Makan')->after('place');
            }
            if (!Schema::hasColumn('attendance_docs', 'receipt_photos')) {
                $table->json('receipt_photos')->nullable()->after('photos');
            }
            if (!Schema::hasColumn('attendance_docs', 'estimated_amount')) {
                $table->decimal('estimated_amount', 15, 2)->nullable()->default(0)->after('receipt_photos');
            }
            if (!Schema::hasColumn('attendance_docs', 'admin_status')) {
                $table->string('admin_status')->default('MENUNGGU_PENYESUAIAN')->after('estimated_amount');
            }
            if (!Schema::hasColumn('attendance_docs', 'admin_notes')) {
                $table->text('admin_notes')->nullable()->after('admin_status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_docs', function (Blueprint $table) {
            $columns = ['category', 'receipt_photos', 'estimated_amount', 'admin_status', 'admin_notes'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('attendance_docs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
