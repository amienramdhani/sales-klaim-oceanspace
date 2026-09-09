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
        Schema::table('users', function (Blueprint $table) {
            $table->string('custom_id')->nullable()->unique()->after('id'); // User ID kombinasi angka & huruf
            $table->foreignId('role_id')->nullable()->after('custom_id')->constrained('roles')->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->after('role_id')->constrained('employees')->nullOnDelete();
            $table->string('homebase')->nullable()->default('Purwokerto')->after('employee_id');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->string('custom_id')->nullable()->after('id');
            $table->foreignId('role_id')->nullable()->after('position_id')->constrained('roles')->nullOnDelete();
            $table->string('homebase')->nullable()->default('Purwokerto')->after('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropForeign(['employee_id']);
            $table->dropColumn(['custom_id', 'role_id', 'employee_id', 'homebase']);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropColumn(['custom_id', 'role_id', 'homebase']);
        });
    }
};
