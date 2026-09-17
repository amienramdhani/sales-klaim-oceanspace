<div class="flex flex-col items-center justify-center p-2 text-center">
    <div class="w-full mb-4 p-3 bg-gray-50 dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 text-left text-sm space-y-1.5">
        <div class="flex justify-between items-center">
            <span class="text-gray-500 dark:text-gray-400">Nomor _UID:</span>
            <span class="font-bold font-mono text-emerald-600 dark:text-emerald-400">{{ $record->_uid ?: '-' }}</span>
        </div>
        <div class="flex justify-between items-center">
            <span class="text-gray-500 dark:text-gray-400">Kategori Klaim:</span>
            <span class="px-2 py-0.5 text-xs font-semibold rounded-md bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300">
                @if($record->claim_category === 'bbm')
                    Klaim BBM
                @elseif($record->claim_category === 'perdin' || $record->is_perdin)
                    Perjalanan Dinas (Perdin)
                @else
                    {{ $record->claim_type_string ?: 'Transport & Entertain' }}
                @endif
            </span>
        </div>
        <div class="flex justify-between items-center">
            <span class="text-gray-500 dark:text-gray-400">Nama Pemohon:</span>
            <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $record->effective_employee?->name ?? $record->employee?->name ?? '-' }} ({{ $record->effective_employee?->position_name ?? '-' }})</span>
        </div>
        <div class="flex justify-between items-center">
            <span class="text-gray-500 dark:text-gray-400">Total Nominal Dicairkan:</span>
            <span class="font-bold text-base text-emerald-600 dark:text-emerald-400">
                @php
                    $disbursedAmount = (float)($record->claim_category === 'bbm' && (float)($record->effective_employee?->bbm_budget ?? 0) > 0 
                        ? $record->effective_employee?->bbm_budget 
                        : $record->amount);
                @endphp
                Rp {{ number_format($disbursedAmount, 0, ',', '.') }}
            </span>
        </div>
        <div class="flex justify-between items-center">
            <span class="text-gray-500 dark:text-gray-400">Waktu Pencairan:</span>
            <span class="text-gray-800 dark:text-gray-200">{{ $record->disbursed_at ? $record->disbursed_at->translatedFormat('d F Y H:i') : '-' }}</span>
        </div>
        <div class="flex justify-between items-center">
            <span class="text-gray-500 dark:text-gray-400">Diproses Oleh Finance:</span>
            <span class="font-medium text-gray-800 dark:text-gray-200">{{ $record->financeApprovedBy?->name ?? 'Tim Finance' }}</span>
        </div>
        <div class="flex justify-between items-center pt-1 border-t border-gray-200 dark:border-gray-700">
            <span class="text-gray-500 dark:text-gray-400">Status Pencairan:</span>
            <span class="px-2 py-0.5 text-xs rounded-md font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300">{{ $record->disbursement_status ?? 'Sudah Dicairkan' }}</span>
        </div>
    </div>

    @if($photo && \Illuminate\Support\Facades\Storage::disk('public')->exists($photo))
        <div class="relative w-full max-h-[70vh] overflow-auto rounded-xl border border-gray-300 dark:border-gray-700 shadow-sm bg-black/5 dark:bg-black/20 flex items-center justify-center p-2">
            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($photo) }}" alt="Bukti Transfer Finance" class="max-h-[60vh] max-w-full object-contain rounded-lg shadow-md" />
        </div>
    @else
        <div class="p-8 text-gray-400 dark:text-gray-500">
            <svg class="w-12 h-12 mx-auto mb-2 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            <p>File foto bukti transfer tidak ditemukan pada penyimpanan server.</p>
        </div>
    @endif
</div>
