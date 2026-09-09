<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tambah rincian budget Perjalanan Dinas pada tabel employees
        Schema::table('employees', function (Blueprint $table) {
            if (!Schema::hasColumn('employees', 'perdin_meal_allowance')) {
                $table->decimal('perdin_meal_allowance', 15, 2)->default(100000)->after('perdin_budget')
                    ->comment('Tarif uang makan harian perdin per karyawan / region');
            }
            if (!Schema::hasColumn('employees', 'perdin_lodging_allowance')) {
                $table->decimal('perdin_lodging_allowance', 15, 2)->default(250000)->after('perdin_meal_allowance')
                    ->comment('Tarif penginapan per malam perdin per karyawan / region');
            }
            if (!Schema::hasColumn('employees', 'perdin_transport_budget')) {
                $table->decimal('perdin_transport_budget', 15, 2)->default(500000)->after('perdin_lodging_allowance')
                    ->comment('Plafon biaya transportasi / bensin / tol perdin');
            }
        });

        // 2. Tambah field alur perjalanan dinas, kwitansi, dan foto service motor pada tabel claims
        Schema::table('claims', function (Blueprint $table) {
            if (!Schema::hasColumn('claims', 'perdin_return_date')) {
                $table->date('perdin_return_date')->nullable()->after('claim_date')
                    ->comment('Tanggal kepulangan perjalanan dinas');
            }
            if (!Schema::hasColumn('claims', 'admin_gform_submitted')) {
                $table->boolean('admin_gform_submitted')->default(false)->after('approval_status')
                    ->comment('Status pengajuan via Google Form oleh admin');
            }
            if (!Schema::hasColumn('claims', 'admin_gform_url')) {
                $table->string('admin_gform_url', 500)->nullable()->after('admin_gform_submitted')
                    ->comment('URL Google Form approval atasan');
            }
            if (!Schema::hasColumn('claims', 'admin_gform_submitted_at')) {
                $table->datetime('admin_gform_submitted_at')->nullable()->after('admin_gform_url');
            }
            if (!Schema::hasColumn('claims', 'nota_balik_submitted')) {
                $table->boolean('nota_balik_submitted')->default(false)->after('admin_gform_submitted_at')
                    ->comment('Apakah sales/asm/rgm sudah balik nota setelah perjalanan dinas');
            }
            if (!Schema::hasColumn('claims', 'nota_balik_submitted_at')) {
                $table->datetime('nota_balik_submitted_at')->nullable()->after('nota_balik_submitted');
            }
            if (!Schema::hasColumn('claims', 'nota_balik_recap_notes')) {
                $table->text('nota_balik_recap_notes')->nullable()->after('nota_balik_submitted_at')
                    ->comment('Catatan rekapitulasi nota balik dari admin');
            }
            if (!Schema::hasColumn('claims', 'nota_balik_finance_sent')) {
                $table->boolean('nota_balik_finance_sent')->default(false)->after('nota_balik_recap_notes')
                    ->comment('Apakah admin sudah mengirim rekap nota balik ke finance');
            }
            if (!Schema::hasColumn('claims', 'nota_balik_finance_sent_at')) {
                $table->datetime('nota_balik_finance_sent_at')->nullable()->after('nota_balik_finance_sent');
            }
            if (!Schema::hasColumn('claims', 'kwitansi_number')) {
                $table->string('kwitansi_number', 50)->nullable()->after('nota_balik_finance_sent_at')
                    ->comment('Nomor kwitansi resmi pencairan');
            }
            // Foto service motor
            if (!Schema::hasColumn('claims', 'service_photo_before')) {
                $table->string('service_photo_before')->nullable()->after('bbm_photo_combined')
                    ->comment('Foto sebelum service motor');
            }
            if (!Schema::hasColumn('claims', 'service_photo_after')) {
                $table->string('service_photo_after')->nullable()->after('service_photo_before')
                    ->comment('Foto sesudah service motor');
            }
            if (!Schema::hasColumn('claims', 'service_photo_combined')) {
                $table->string('service_photo_combined')->nullable()->after('service_photo_after')
                    ->comment('Foto gabungan before dan after service motor');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $cols = ['perdin_meal_allowance', 'perdin_lodging_allowance', 'perdin_transport_budget'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('employees', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('claims', function (Blueprint $table) {
            $cols = [
                'perdin_return_date',
                'admin_gform_submitted',
                'admin_gform_url',
                'admin_gform_submitted_at',
                'nota_balik_submitted',
                'nota_balik_submitted_at',
                'nota_balik_recap_notes',
                'nota_balik_finance_sent',
                'nota_balik_finance_sent_at',
                'kwitansi_number',
                'service_photo_before',
                'service_photo_after',
                'service_photo_combined',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('claims', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
