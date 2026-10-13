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
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->unsignedBigInteger('region_id')->nullable()->after('position_id');
        });

        // Seed initial regions
        $initialRegions = [
            ['name' => 'CIREBON', 'code' => 'CRB', 'description' => 'Region Cirebon & Sekitarnya'],
            ['name' => 'JABO', 'code' => 'JBO', 'description' => 'Region Jawa Barat Operasional'],
            ['name' => 'JABODETABEK', 'code' => 'JBD', 'description' => 'Region Jakarta, Bogor, Depok, Tangerang, Bekasi'],
            ['name' => 'JAKARTA', 'code' => 'JKT', 'description' => 'Region DKI Jakarta'],
            ['name' => 'JATENG', 'code' => 'JTG', 'description' => 'Region Jawa Tengah'],
            ['name' => 'JATIM', 'code' => 'JTM', 'description' => 'Region Jawa Timur'],
            ['name' => 'JOGJA', 'code' => 'JOG', 'description' => 'Region D.I. Yogyakarta'],
            ['name' => 'PURWOKERTO', 'code' => 'PWT', 'description' => 'Region Purwokerto & Banyumas Raya'],
            ['name' => 'SEMARANG', 'code' => 'SMG', 'description' => 'Region Semarang & Sekitarnya'],
            ['name' => 'SULAWESI', 'code' => 'SLW', 'description' => 'Region Sulawesi'],
            ['name' => 'SUMATERA', 'code' => 'SUM', 'description' => 'Region Sumatera'],
            ['name' => 'SUMATERA BARAT', 'code' => 'SMB', 'description' => 'Region Sumatera Barat'],
            ['name' => 'SUMATRA UTARA & ACEH', 'code' => 'SMU', 'description' => 'Region Sumatera Utara & Aceh'],
            ['name' => 'LAMPUNG & BENGKULU', 'code' => 'LPG', 'description' => 'Region Lampung & Bengkulu'],
        ];

        foreach ($initialRegions as $r) {
            \Illuminate\Support\Facades\DB::table('regions')->insertOrIgnore([
                'name' => $r['name'],
                'code' => $r['code'],
                'description' => $r['description'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Link existing employees by region name matching
        $regions = \Illuminate\Support\Facades\DB::table('regions')->get()->keyBy(fn ($item) => strtoupper($item->name));
        $employees = \Illuminate\Support\Facades\DB::table('employees')->get();
        foreach ($employees as $emp) {
            $regName = strtoupper(trim($emp->region ?? ''));
            if ($regName && isset($regions[$regName])) {
                \Illuminate\Support\Facades\DB::table('employees')->where('id', $emp->id)->update([
                    'region_id' => $regions[$regName]->id,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('region_id');
        });

        Schema::dropIfExists('regions');
    }
};
