<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (!Schema::hasColumn('employees', 'bbm_budget')) {
                $table->decimal('bbm_budget', 15, 2)->default(0)->after('homebase')
                    ->comment('Budget BBM per bulan untuk ASM/RGM ini (diatur fleksibel per user oleh Super Admin)');
            }
            if (!Schema::hasColumn('employees', 'entertain_budget')) {
                $table->decimal('entertain_budget', 15, 2)->nullable()->default(null)->after('bbm_budget')
                    ->comment('Custom budget entertain (jika null/0, gunakan standar jabatan ASM 1.5jt, RGM 2.4jt)');
            }
            if (!Schema::hasColumn('employees', 'perdin_budget')) {
                $table->decimal('perdin_budget', 15, 2)->default(0)->after('entertain_budget')
                    ->comment('Budget perjalanan dinas per bulan untuk karyawan ini');
            }
            if (!Schema::hasColumn('employees', 'transport_budget')) {
                $table->decimal('transport_budget', 15, 2)->default(0)->after('perdin_budget')
                    ->comment('Budget transport, tol, dan parkir per bulan untuk karyawan ini');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $cols = ['bbm_budget', 'entertain_budget', 'perdin_budget', 'transport_budget'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('employees', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
