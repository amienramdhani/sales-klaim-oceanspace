<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. users table: add region & managed_regions
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'region')) {
                $table->string('region')->nullable()->after('homebase')
                    ->comment('Region operasional user (untuk ASM, RGM, Sales, Finance, dll)');
            }
            if (!Schema::hasColumn('users', 'managed_regions')) {
                $table->json('managed_regions')->nullable()->after('region')
                    ->comment('Daftar region yang dipegang (khusus role Admin)');
            }
        });

        // 2. employees table: add region
        Schema::table('employees', function (Blueprint $table) {
            if (!Schema::hasColumn('employees', 'region')) {
                $table->string('region')->nullable()->after('homebase')
                    ->comment('Region operasional karyawan');
            }
        });

        // 3. claims table: add region
        Schema::table('claims', function (Blueprint $table) {
            if (!Schema::hasColumn('claims', 'region')) {
                $table->string('region')->nullable()->after('homebase')
                    ->comment('Region asal pengajuan klaim');
            }
        });

        // 4. Backfill initial data from homebase
        // Users
        DB::table('users')->whereNull('region')->whereNotNull('homebase')->update([
            'region' => DB::raw('UPPER(homebase)')
        ]);

        // Admins: initialize managed_regions with their homebase
        $admins = DB::table('users')
            ->join('roles', 'users.role_id', '=', 'roles.id')
            ->where(function ($q) {
                $q->whereIn(DB::raw('UPPER(roles.code)'), ['ADMIN'])
                  ->orWhere('users.name', 'like', '%ADMIN%');
            })
            ->select('users.id', 'users.homebase')
            ->get();

        foreach ($admins as $admin) {
            $regionVal = strtoupper(trim($admin->homebase ?: 'CIREBON'));
            DB::table('users')->where('id', $admin->id)->update([
                'region' => $regionVal,
                'managed_regions' => json_encode([$regionVal]),
            ]);
        }

        // Employees
        DB::table('employees')->whereNull('region')->whereNotNull('homebase')->update([
            'region' => DB::raw('UPPER(homebase)')
        ]);

        // Claims: fill region from employee's homebase or user's homebase
        $claims = DB::table('claims')->select('id', 'employee_id', 'user_id', 'homebase')->get();
        foreach ($claims as $c) {
            $reg = null;
            if (!empty($c->homebase)) {
                $reg = strtoupper(trim($c->homebase));
            } elseif ($c->employee_id) {
                $emp = DB::table('employees')->where('id', $c->employee_id)->first();
                if ($emp && !empty($emp->homebase)) {
                    $reg = strtoupper(trim($emp->homebase));
                }
            } elseif ($c->user_id) {
                $u = DB::table('users')->where('id', $c->user_id)->first();
                if ($u && !empty($u->homebase)) {
                    $reg = strtoupper(trim($u->homebase));
                }
            }

            if ($reg) {
                DB::table('claims')->where('id', $c->id)->update(['region' => $reg]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('claims', function (Blueprint $table) {
            if (Schema::hasColumn('claims', 'region')) {
                $table->dropColumn('region');
            }
        });

        Schema::table('employees', function (Blueprint $table) {
            if (Schema::hasColumn('employees', 'region')) {
                $table->dropColumn('region');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'managed_regions')) {
                $table->dropColumn('managed_regions');
            }
            if (Schema::hasColumn('users', 'region')) {
                $table->dropColumn('region');
            }
        });
    }
};
