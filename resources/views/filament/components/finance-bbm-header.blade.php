<div class="mb-4">
    <div class="p-4 rounded-xl border {{ now()->day > 10 ? 'bg-amber-50 dark:bg-amber-950/40 border-amber-300 dark:border-amber-800' : 'bg-blue-50 dark:bg-blue-950/40 border-blue-200 dark:border-blue-800' }} shadow-sm">
        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-3">
            <div class="flex items-start gap-3">
                <div class="p-2 rounded-lg {{ now()->day > 10 ? 'bg-amber-100 text-amber-700 dark:bg-amber-900 dark:text-amber-300' : 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300' }}">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <span>Alur Nota Balik Klaim BBM</span>
                        @if(now()->day <= 10)
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200">
                                Periode Aktif s/d Tanggal 10
                            </span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200">
                                Lewat Tanggal 10 Bulan Berjalan
                            </span>
                        @endif
                    </h3>
                    <p class="text-xs text-gray-600 dark:text-gray-300 mt-1">
                        Pencairan BBM bulanan menggunakan sistem <strong>Nota Balik</strong> (pemeriksaan bukti nota & struk SPBU bulan sebelumnya). Batas waktu pengajuan dan verifikasi pencairan BBM adalah <strong>maksimal tanggal 10 di awal bulan</strong>.
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2 text-xs font-medium text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 self-stretch md:self-auto justify-center">
                <span>Hari ini: {{ now()->translatedFormat('d F Y') }}</span>
            </div>
        </div>
    </div>
</div>
