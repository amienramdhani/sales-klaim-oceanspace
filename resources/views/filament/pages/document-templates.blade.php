<x-filament-panels::page>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <!-- Card 1: Excel Rekap Format -->
        <div class="p-6 rounded-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-sm flex flex-col justify-between">
            <div>
                <div class="w-12 h-12 rounded-xl bg-emerald-100 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mb-4">
                    <x-heroicon-o-table-cells class="w-7 h-7" />
                </div>
                <h2 class="text-base font-bold text-gray-900 dark:text-white">Format Rekap Klaim (Excel)</h2>
                <p class="text-xs text-gray-600 dark:text-gray-400 mt-2 leading-relaxed">
                    Template laporan realisasi biaya operasional perusahaan. Memuat data pemohon, ringkasan budget & klaim, rincian pengeluaran, dan kolom approval.
                </p>
                <div class="mt-4 p-3 bg-gray-50 dark:bg-gray-800/50 rounded-lg text-xs text-gray-500 space-y-1">
                    <div>• Format: <strong>Microsoft Excel (.xlsx)</strong></div>
                    <div>• Layout: Metadata di bawah tanggal</div>
                </div>
            </div>
            <div class="mt-6 pt-4 border-t border-gray-100 dark:border-gray-800">
                <x-filament::button wire:click="downloadSampleExcel" color="success" icon="heroicon-o-arrow-down-tray" class="w-full">
                    Unduh Format Excel Rekap
                </x-filament::button>
            </div>
        </div>

        <!-- Card 2: Form Perdin Excel -->
        <div class="p-6 rounded-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-sm flex flex-col justify-between">
            <div>
                <div class="w-12 h-12 rounded-xl bg-amber-100 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center mb-4">
                    <x-heroicon-o-briefcase class="w-7 h-7" />
                </div>
                <h2 class="text-base font-bold text-gray-900 dark:text-white">Form Pengajuan Perdin (Excel)</h2>
                <p class="text-xs text-gray-600 dark:text-gray-400 mt-2 leading-relaxed">
                    Formulir resmi Perjalanan Dinas (PT. MEDIA SELULAR INDONESIA). Memuat kop logo resmi, data pemohon, rincian biaya, dan tanda tangan persetujuan.
                </p>
                <div class="mt-4 p-3 bg-gray-50 dark:bg-gray-800/50 rounded-lg text-xs text-gray-500 space-y-1">
                    <div>• Format: <strong>Microsoft Excel (.xlsx)</strong></div>
                    <div>• Standar: <strong>PT. Media Selular Indonesia</strong></div>
                </div>
            </div>
            <div class="mt-6 pt-4 border-t border-gray-100 dark:border-gray-800">
                <x-filament::button wire:click="downloadSamplePerdin" color="warning" icon="heroicon-o-arrow-down-tray" class="w-full">
                    Unduh Form Perdin Excel
                </x-filament::button>
            </div>
        </div>

        <!-- Card 3: Berita Acara BBM Word -->
        <div class="p-6 rounded-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-sm flex flex-col justify-between">
            <div>
                <div class="w-12 h-12 rounded-xl bg-red-100 dark:bg-red-950/60 text-red-600 dark:text-red-400 flex items-center justify-center mb-4">
                    <x-heroicon-o-document-chart-bar class="w-7 h-7" />
                </div>
                <h2 class="text-base font-bold text-gray-900 dark:text-white">Berita Acara BBM (Word)</h2>
                <p class="text-xs text-gray-600 dark:text-gray-400 mt-2 leading-relaxed">
                    Dokumen resmi Berita Acara Ketidaksesuaian Pembelian BBM (PT. MSI) untuk transaksi nominal bulat tanpa lebihan Rp 5.000 atau struk tidak tercetak.
                </p>
                <div class="mt-4 p-3 bg-gray-50 dark:bg-gray-800/50 rounded-lg text-xs text-gray-500 space-y-1">
                    <div>• Format: <strong>Microsoft Word (.docx)</strong></div>
                    <div>• Lengkap: Kop Logo, Approver, Lampiran</div>
                </div>
            </div>
            <div class="mt-6 pt-4 border-t border-gray-100 dark:border-gray-800">
                <x-filament::button wire:click="downloadBlankoBeritaAcaraBbm" color="danger" icon="heroicon-o-arrow-down-tray" class="w-full">
                    Unduh Blanko Berita Acara BBM
                </x-filament::button>
            </div>
        </div>

        <!-- Card 4: Word Daftar Hadir Format -->
        <div class="p-6 rounded-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-sm flex flex-col justify-between">
            <div>
                <div class="w-12 h-12 rounded-xl bg-blue-100 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center mb-4">
                    <x-heroicon-o-document-text class="w-7 h-7" />
                </div>
                <h2 class="text-base font-bold text-gray-900 dark:text-white">Form Daftar Hadir (Word)</h2>
                <p class="text-xs text-gray-600 dark:text-gray-400 mt-2 leading-relaxed">
                    Dokumen resmi pendukung untuk klaim Entertain. Memuat keterangan pertemuan, tabel daftar peserta & tanda tangan, serta lampiran foto proporsional.
                </p>
                <div class="mt-4 p-3 bg-gray-50 dark:bg-gray-800/50 rounded-lg text-xs text-gray-500 space-y-1">
                    <div>• Format: <strong>Microsoft Word (.docx)</strong></div>
                    <div>• Font: <strong>Aptos (12pt)</strong></div>
                </div>
            </div>
            <div class="mt-6 pt-4 border-t border-gray-100 dark:border-gray-800">
                <x-filament::button wire:click="downloadBlankWordAttendance" color="primary" icon="heroicon-o-arrow-down-tray" class="w-full">
                    Unduh Blanko Daftar Hadir
                </x-filament::button>
            </div>
        </div>

        <!-- Card 5: Form Pengajuan Klaim BBM Word -->
        <div class="p-6 rounded-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-sm flex flex-col justify-between">
            <div>
                <div class="w-12 h-12 rounded-xl bg-teal-100 dark:bg-teal-950/60 text-teal-600 dark:text-teal-400 flex items-center justify-center mb-4">
                    <x-heroicon-o-document-check class="w-7 h-7" />
                </div>
                <h2 class="text-base font-bold text-gray-900 dark:text-white">Form Klaim BBM (Word)</h2>
                <p class="text-xs text-gray-600 dark:text-gray-400 mt-2 leading-relaxed">
                    Dokumen cetak pengajuan klaim pengisian bahan bakar minyak operasional sales. Memuat kop resmi MSI, rincian SPBU, KM awal, volume liter, matrix tanda tangan approval, dan lampiran foto odometer.
                </p>
                <div class="mt-4 p-3 bg-gray-50 dark:bg-gray-800/50 rounded-lg text-xs text-gray-500 space-y-1">
                    <div>• Format: <strong>Microsoft Word (.docx)</strong></div>
                    <div>• Lengkap: Kop Logo, Tabel SPBU, KM & Foto</div>
                </div>
            </div>
            <div class="mt-6 pt-4 border-t border-gray-100 dark:border-gray-800">
                <x-filament::button wire:click="downloadBlankoFormKlaimBbmWord" color="info" icon="heroicon-o-arrow-down-tray" class="w-full">
                    Unduh Form Klaim BBM Word
                </x-filament::button>
            </div>
        </div>
    </div>

    <!-- Panduan Operasional Section -->
    <div class="mt-4 p-6 rounded-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-sm">
        <h3 class="text-base font-bold text-gray-900 dark:text-white mb-3 flex items-center gap-2">
            <x-heroicon-o-information-circle class="w-5 h-5 text-indigo-500" />
            Ketentuan & Alur Pengisian Pengajuan Klaim Operasional
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-xs text-gray-600 dark:text-gray-400">
            <div class="p-3 bg-gray-50 dark:bg-gray-800/40 rounded-xl">
                <span class="font-bold text-gray-800 dark:text-gray-200 block mb-1">1. Klaim Transport & Entertain</span>
                Dapat memilih lebih dari satu item pengeluaran. Kategori Entertain: Makan (memotong plafon) vs Lainnya (non plafon).
            </div>
            <div class="p-3 bg-gray-50 dark:bg-gray-800/40 rounded-xl">
                <span class="font-bold text-gray-800 dark:text-gray-200 block mb-1">2. Klaim BBM & Pertamax</span>
                Pembelian Pertamax dihitung konversi liter x harga Pertalite (bisa diedit). Wajib upload odometer Before & After. Jika nominal bulat, wajib lampirkan Berita Acara.
            </div>
            <div class="p-3 bg-gray-50 dark:bg-gray-800/40 rounded-xl">
                <span class="font-bold text-gray-800 dark:text-gray-200 block mb-1">3. Perjalanan Dinas & Service</span>
                Perdin minimal 80 km dari homebase. Service kendaraan pribadi: Mobil Rp 2jt / Motor Rp 500rb per 3 bulan.
            </div>
            <div class="p-3 bg-gray-50 dark:bg-gray-800/40 rounded-xl">
                <span class="font-bold text-gray-800 dark:text-gray-200 block mb-1">4. Alur _UID & Finance</span>
                Admin input ke form external ➔ dapat _UID ➔ edit klaim masukkan _UID ➔ status masuk ke Finance ➔ Finance cairkan dengan upload bukti transfer bank.
            </div>
        </div>
    </div>
</x-filament-panels::page>
