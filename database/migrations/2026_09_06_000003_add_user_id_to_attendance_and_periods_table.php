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
        if (Schema::hasTable('attendance_docs') && !Schema::hasColumn('attendance_docs', 'user_id')) {
            Schema::table('attendance_docs', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            });
        }

        if (Schema::hasTable('claim_periods') && !Schema::hasColumn('claim_periods', 'user_id')) {
            Schema::table('claim_periods', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('attendance_docs') && Schema::hasColumn('attendance_docs', 'user_id')) {
            Schema::table('attendance_docs', function (Blueprint $table) {
                $table->dropConstrainedForeignId('user_id');
            });
        }

        if (Schema::hasTable('claim_periods') && Schema::hasColumn('claim_periods', 'user_id')) {
            Schema::table('claim_periods', function (Blueprint $table) {
                $table->dropConstrainedForeignId('user_id');
            });
        }
    }
};
