<div class="space-y-4">
    {{-- Header Info Klaim --}}
    <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-800/60 border border-gray-200 dark:border-gray-700">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
            <div>
                <span class="text-xs text-gray-500 dark:text-gray-400 block font-medium">Pemohon</span>
                <span class="font-bold text-gray-900 dark:text-gray-100">{{ $record->effective_employee?->name ?? ($record->user?->name ?? '-') }}</span>
                <span class="text-xs text-gray-500 dark:text-gray-400 block">{{ $record->effective_employee?->position_name ?? '-' }}</span>
            </div>
            <div>
                <span class="text-xs text-gray-500 dark:text-gray-400 block font-medium">Kategori / Jenis Klaim</span>
                <span class="font-semibold text-primary-600 dark:text-primary-400">{{ $record->claim_type_string ?? 'Klaim' }}</span>
                <span class="text-xs text-gray-500 dark:text-gray-400 block">{{ $record->claim_date ? $record->claim_date->format('d/m/Y') : '-' }}</span>
            </div>
            <div>
                <span class="text-xs text-gray-500 dark:text-gray-400 block font-medium">Total Nominal Klaim</span>
                <span class="text-base font-extrabold text-emerald-600 dark:text-emerald-400">Rp {{ number_format((float)$record->amount, 0, ',', '.') }}</span>
            </div>
            <div>
                <span class="text-xs text-gray-500 dark:text-gray-400 block font-medium">Status & Pencairan</span>
                <div class="flex flex-wrap gap-1 mt-0.5">
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold {{ $record->approval_status === 'DISETUJUI' ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300' : ($record->approval_status === 'DITOLAK' ? 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300') }}">
                        {{ $record->approval_status }}
                    </span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold {{ $record->disbursement_status === 'Sudah Dicairkan' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' }}">
                        {{ $record->disbursement_status }}
                    </span>
                </div>
            </div>
        </div>
        @if(!empty($record->rejection_reason))
            <div class="mt-3 p-2.5 rounded-lg bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-900/50 text-xs text-red-700 dark:text-red-300">
                <span class="font-bold">⚠️ Catatan Penolakan:</span> {{ $record->rejection_reason }}
            </div>
        @endif
    </div>

    {{-- Daftar File Lampiran --}}
    @if(empty($files) || count($files) === 0)
        <div class="py-12 text-center rounded-xl border-2 border-dashed border-gray-200 dark:border-gray-700">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
            </svg>
            <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-gray-100">Belum Ada Berkas Terlampir</h3>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Pengajuan klaim ini belum mengunggah foto struk, kwitansi, atau dokumen pendukung.</p>
        </div>
    @else
        <div class="flex items-center justify-between pb-1 border-b border-gray-100 dark:border-gray-800">
            <h4 class="text-sm font-bold text-gray-800 dark:text-gray-200">
                Total {{ count($files) }} Berkas Terlampir
            </h4>
            <span class="text-xs text-gray-500">Klik gambar untuk membuka ukuran penuh di tab baru</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 max-h-[65vh] overflow-y-auto pr-1">
            @foreach($files as $index => $file)
                <div class="group relative flex flex-col rounded-xl overflow-hidden border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm hover:shadow-md transition duration-200">
                    {{-- File Preview Area --}}
                    @if($file['is_image'])
                        <div class="relative w-full h-48 bg-gray-100 dark:bg-gray-900 overflow-hidden flex items-center justify-center">
                            @if($file['exists'])
                                <img src="{{ $file['url'] }}" 
                                     alt="{{ $file['title'] }}" 
                                     class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                                     loading="lazy" />
                            @else
                                <div class="text-center p-4 text-xs text-amber-600 dark:text-amber-400">
                                    <span>⚠️ File fisik di server tidak ditemukan</span>
                                </div>
                            @endif

                            {{-- Overlay Action Bar on Hover --}}
                            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2 p-2">
                                <a href="{{ $file['url'] }}" target="_blank" rel="noopener noreferrer"
                                   class="inline-flex items-center px-2.5 py-1.5 rounded-lg bg-white/90 text-gray-800 text-xs font-semibold hover:bg-white shadow">
                                    🔍 Buka Layar Penuh
                                </a>
                                <a href="{{ $file['url'] }}" download="{{ $file['filename'] }}"
                                   class="inline-flex items-center px-2.5 py-1.5 rounded-lg bg-emerald-600 text-white text-xs font-semibold hover:bg-emerald-700 shadow">
                                    ⬇️ Unduh
                                </a>
                            </div>
                        </div>
                    @elseif($file['is_pdf'])
                        <div class="relative w-full h-48 bg-red-50 dark:bg-red-950/20 flex flex-col items-center justify-center p-4 border-b border-red-100 dark:border-red-900/30">
                            <svg class="w-16 h-16 text-red-500" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2C6.49 2 2 6.49 2 12s4.49 10 10 10 10-4.49 10-10S17.51 2 12 2zm-1 8.5c0 .28-.22.5-.5.5h-1c-.28 0-.5-.22-.5-.5v-3c0-.28.22-.5.5-.5h1c.28 0 .5.22.5.5v3zm3 4c0 .28-.22.5-.5.5h-2.5c-.28 0-.5-.22-.5-.5v-4c0-.28.22-.5.5-.5H13.5c.28 0 .5.22.5.5v4zm3-2c0 .28-.22.5-.5.5H16v1.5c0 .28-.22.5-.5.5s-.5-.22-.5-.5v-4c0-.28.22-.5.5-.5h1.5c.28 0 .5.22.5.5v2z" />
                            </svg>
                            <span class="mt-2 text-xs font-bold text-red-700 dark:text-red-400">Dokumen PDF</span>
                            <div class="flex gap-2 mt-3">
                                <a href="{{ $file['url'] }}" target="_blank" rel="noopener noreferrer"
                                   class="inline-flex items-center px-2.5 py-1 rounded bg-red-600 text-white text-xs font-semibold hover:bg-red-700 shadow">
                                    Buka PDF
                                </a>
                                <a href="{{ $file['url'] }}" download="{{ $file['filename'] }}"
                                   class="inline-flex items-center px-2.5 py-1 rounded bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 text-xs font-semibold hover:bg-gray-300 shadow">
                                    Unduh
                                </a>
                            </div>
                        </div>
                    @else
                        <div class="relative w-full h-48 bg-gray-100 dark:bg-gray-900 flex flex-col items-center justify-center p-4">
                            <svg class="w-14 h-14 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                            </svg>
                            <span class="mt-2 text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">{{ $file['ext'] ?: 'Berkas' }}</span>
                            <a href="{{ $file['url'] }}" download="{{ $file['filename'] }}"
                               class="mt-2 inline-flex items-center px-3 py-1 rounded bg-primary-600 text-white text-xs font-semibold hover:bg-primary-700 shadow">
                                Unduh File
                            </a>
                        </div>
                    @endif

                    {{-- File Information Footer --}}
                    <div class="p-3 flex-1 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between gap-1 mb-1">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider
                                    {{ str_contains($file['category'], 'BBM') ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300' : 
                                       (str_contains($file['category'], 'Service') ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300' : 
                                       (str_contains($file['category'], 'Pencairan') ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' : 
                                       (str_contains($file['category'], 'Pengembalian') ? 'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'))) }}">
                                    {{ $file['category'] }}
                                </span>
                                @if($file['size'])
                                    <span class="text-[11px] text-gray-400 dark:text-gray-500 font-mono">{{ $file['size'] }}</span>
                                @endif
                            </div>
                            <h5 class="text-xs font-semibold text-gray-900 dark:text-gray-100 line-clamp-2" title="{{ $file['title'] }}">
                                {{ $file['title'] }}
                            </h5>
                        </div>

                        <div class="mt-2 pt-2 border-t border-gray-100 dark:border-gray-750 flex items-center justify-between text-[11px]">
                            <a href="{{ $file['url'] }}" target="_blank" rel="noopener noreferrer" 
                               class="text-primary-600 dark:text-primary-400 hover:underline flex items-center gap-1">
                                <span>Buka</span>
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                            </a>
                            <a href="{{ $file['url'] }}" download="{{ $file['filename'] }}" 
                               class="text-gray-500 dark:text-gray-400 hover:text-emerald-600 dark:hover:text-emerald-400 flex items-center gap-1">
                                <span>Unduh</span>
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
