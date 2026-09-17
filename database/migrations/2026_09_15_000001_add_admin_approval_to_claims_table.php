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
            if (!Schema::hasColumn('claims', 'admin_approved_by_id')) {
                $table->foreignId('admin_approved_by_id')->nullable()->constrained('users')->nullOnDelete()->after('approved_by_jejen_at');
            }
            if (!Schema::hasColumn('claims', 'admin_approved_at')) {
                $table->timestamp('admin_approved_at')->nullable()->after('admin_approved_by_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('claims', function (Blueprint $table) {
            if (Schema::hasColumn('claims', 'admin_approved_by_id')) {
                $table->dropForeign(['admin_approved_by_id']);
                $table->dropColumn('admin_approved_by_id');
            }
            if (Schema::hasColumn('claims', 'admin_approved_at')) {
                $table->dropColumn('admin_approved_at');
            }
        });
    }
};
