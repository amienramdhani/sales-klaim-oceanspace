<div class="flex flex-col items-center justify-center p-2 text-center">
    {{-- Quick Mobile Close Bar --}}
    <div class="sm:hidden w-full flex items-center justify-between p-2.5 rounded-xl bg-gradient-to-r from-red-50 to-orange-50 dark:from-red-950/40 dark:to-orange-950/40 border border-red-200 dark:border-red-900/60 shadow-sm mb-3">
        <span class="text-xs font-bold text-gray-900 dark:text-gray-100">Bukti Transfer Balik</span>
        <button type="button" 
                x-on:click="const btn = $el.closest('.fi-modal-window')?.querySelector('.fi-modal-close-btn'); if (btn) { btn.click(); } else { $dispatch('close-modal'); if (typeof close === 'function') close(); }"
                class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-red-600 hover:bg-red-700 active:bg-red-800 text-white text-xs font-bold shadow transition transform active:scale-95 touch-manipulation">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
            <span>Tutup</span>
        </button>
    </div>

    <div class="w-full mb-4 p-3 bg-gray-50 dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 text-left text-sm space-y-1">
        <div class="flex justify-between">
            <span class="text-gray-500 dark:text-gray-400">Nomor _UID:</span>
            <span class="font-bold font-mono text-emerald-600 dark:text-emerald-400">{{ $record->_uid ?: '-' }}</span>
        </div>
        <div class="flex justify-between">
            <span class="text-gray-500 dark:text-gray-400">Nama Pemohon:</span>
            <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $record->effective_employee?->name ?? $record->employee?->name ?? '-' }}</span>
        </div>
        <div class="flex justify-between">
            <span class="text-gray-500 dark:text-gray-400">Plafon Budget Awal:</span>
            <span class="font-bold text-gray-900 dark:text-gray-100">Rp {{ number_format((float)($record->effective_employee?->bbm_budget ?? 0), 0, ',', '.') }}</span>
        </div>
        <div class="flex justify-between">
            <span class="text-gray-500 dark:text-gray-400">Total Nota Balik BBM:</span>
            <span class="font-semibold text-teal-600 dark:text-teal-400">Rp {{ number_format((float)$record->amount, 0, ',', '.') }}</span>
        </div>
        <div class="flex justify-between">
            <span class="text-gray-500 dark:text-gray-400">Nominal Ditransfer Balik:</span>
            <span class="font-bold text-emerald-600 dark:text-emerald-400">Rp {{ number_format((float)($record->return_transfer_amount ?: $record->remaining_budget_amount), 0, ',', '.') }}</span>
        </div>
        <div class="flex justify-between">
            <span class="text-gray-500 dark:text-gray-400">Waktu Pengiriman:</span>
            <span class="text-gray-700 dark:text-gray-300">{{ $record->return_transferred_at ? $record->return_transferred_at->format('d/m/Y H:i') : '-' }}</span>
        </div>
        @if(!empty($record->return_transfer_notes))
            <div class="pt-2 border-t border-gray-200 dark:border-gray-700 text-xs text-gray-500 dark:text-gray-400">
                <span class="font-semibold">Catatan:</span> {{ $record->return_transfer_notes }}
            </div>
        @endif
    </div>

    @if($photo && \Illuminate\Support\Facades\Storage::disk('public')->exists($photo))
        @php
            $isPdf = str_ends_with(strtolower($photo), '.pdf');
        @endphp
        @if($isPdf)
            <div class="p-6 text-center bg-gray-100 dark:bg-gray-800 rounded-xl w-full">
                <svg class="w-16 h-16 mx-auto text-red-500 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                </svg>
                <p class="font-medium text-gray-900 dark:text-gray-100">Dokumen PDF Bukti Transfer Balik</p>
                <p class="text-xs text-gray-500 mb-4">Silakan klik tombol download di bawah untuk melihat file PDF</p>
            </div>
        @else
            <div class="relative w-full max-h-[70vh] overflow-auto rounded-xl border border-gray-300 dark:border-gray-700 shadow-sm bg-black/5 dark:bg-black/20 flex items-center justify-center p-2">
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($photo) }}" alt="Bukti Transfer Balik ke Finance" class="max-h-[60vh] max-w-full object-contain rounded-lg shadow-md" />
            </div>
        @endif
    @else
        <div class="p-8 text-gray-400 dark:text-gray-500">
            <svg class="w-12 h-12 mx-auto mb-2 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            <p>File bukti transfer balik belum diunggah atau tidak ditemukan.</p>
        </div>
    @endif

    {{-- Mobile Bottom Quick Close Button --}}
    <div class="sm:hidden w-full mt-4 pt-3 border-t border-gray-200 dark:border-gray-700">
        <button type="button" 
                x-on:click="const btn = $el.closest('.fi-modal-window')?.querySelector('.fi-modal-close-btn'); if (btn) { btn.click(); } else { $dispatch('close-modal'); if (typeof close === 'function') close(); }"
                class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl bg-gray-100 hover:bg-gray-200 active:bg-gray-300 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-800 dark:text-gray-100 text-xs font-bold border border-gray-300 dark:border-gray-600 shadow-sm transition touch-manipulation">
            <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
            <span>Tutup Jendela Bukti Transfer</span>
        </button>
    </div>
</div>
