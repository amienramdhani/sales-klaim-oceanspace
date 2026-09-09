<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tambah kolom supervisor_id dan budget_overrides ke employees
        Schema::table('employees', function (Blueprint $table) {
            $table->unsignedBigInteger('supervisor_id')->nullable()->after('role_id')
                ->comment('Atasan langsung: RGM menjadi supervisor ASM, ASM menjadi supervisor Sales');
            $table->foreign('supervisor_id')->references('id')->on('employees')->onDelete('set null');
        });

        // Tambah kolom transport_budget ke positions jika belum ada
        Schema::table('positions', function (Blueprint $table) {
            if (!Schema::hasColumn('positions', 'transport_budget')) {
                $table->decimal('transport_budget', 15, 2)->default(0)->after('entertain_budget')
                    ->comment('Budget transport, tol, dan parkir per bulan (ASM: 1.500.000, RGM: 2.400.000)');
            }
        });

        // Tambah total_budget ke roles jika belum ada
        Schema::table('roles', function (Blueprint $table) {
            if (!Schema::hasColumn('roles', 'total_budget')) {
                $table->decimal('total_budget', 15, 2)->default(0)->after('operational_budget')
                    ->comment('Total plafon bulanan: entertain + transport + bbm + perdin');
            }
            if (!Schema::hasColumn('roles', 'transport_budget')) {
                $table->decimal('transport_budget', 15, 2)->default(0)->after('entertain_budget')
                    ->comment('Budget transport, tol, parkir per bulan');
            }
            if (!Schema::hasColumn('roles', 'bbm_budget')) {
                $table->decimal('bbm_budget', 15, 2)->default(0)->after('transport_budget')
                    ->comment('Budget BBM bulanan (fleksibel, diatur per jabatan)');
            }
            if (!Schema::hasColumn('roles', 'perdin_budget')) {
                $table->decimal('perdin_budget', 15, 2)->default(0)->after('bbm_budget')
                    ->comment('Budget Perjalanan Dinas bulanan');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['supervisor_id']);
            $table->dropColumn('supervisor_id');
        });

        Schema::table('positions', function (Blueprint $table) {
            if (Schema::hasColumn('positions', 'transport_budget')) {
                $table->dropColumn('transport_budget');
            }
        });

        Schema::table('roles', function (Blueprint $table) {
            foreach (['total_budget', 'transport_budget', 'bbm_budget', 'perdin_budget'] as $col) {
                if (Schema::hasColumn('roles', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
