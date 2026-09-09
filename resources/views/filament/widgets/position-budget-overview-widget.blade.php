<x-filament-widgets::widget>
    <div class="p-6 rounded-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-sm">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    <x-heroicon-o-scale class="w-5 h-5 text-emerald-500" />
                    Monitoring Sisa Anggaran & Budget Per Jabatan
                </h2>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                    Ringkasan plafon budget, realisasi klaim terpakai, dan sisa saldo anggaran per jabatan.
                </p>
            </div>
            <a href="{{ url('/admin/positions') }}" class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:underline">
                Kelola Plafon Jabatan →
            </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @forelse ($this->positionsBudgetData as $pos)
                @php
                    $isOver = $pos['remaining_total'] < 0 || $pos['remaining_entertain'] < 0;
                    $statusColor = $isOver ? 'red' : ($pos['percentage_used'] > 80 ? 'amber' : 'emerald');
                @endphp
                <div class="p-4 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/30 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-bold text-sm text-gray-900 dark:text-white flex items-center gap-1.5">
                                <x-heroicon-o-briefcase class="w-4 h-4 text-gray-500" />
                                {{ $pos['name'] }}
                            </span>
                            <span class="text-[11px] px-2 py-0.5 rounded-full font-medium bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                                {{ $pos['employees_count'] }} Karyawan
                            </span>
                        </div>

                        <!-- Progress Bar -->
                        <div class="mt-2 mb-3">
                            <div class="flex justify-between text-[11px] mb-1">
                                <span class="text-gray-500">Terpakai ({{ $pos['percentage_used'] }}%)</span>
                                <span class="font-semibold text-gray-800 dark:text-gray-200">
                                    Rp {{ number_format($pos['used_total'], 0, ',', '.') }} / Rp {{ number_format($pos['total_budget'], 0, ',', '.') }}
                                </span>
                            </div>
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 overflow-hidden">
                                <div class="h-2 rounded-full transition-all duration-500 {{ $isOver ? 'bg-red-500' : ($pos['percentage_used'] > 80 ? 'bg-amber-500' : 'bg-emerald-500') }}"
                                     style="width: {{ min(100, $pos['percentage_used']) }}%">
                                </div>
                            </div>
                        </div>

                        <!-- Detail Budget Entertain & Operasional -->
                        <div class="space-y-2 text-xs border-t border-gray-200 dark:border-gray-700/60 pt-2.5">
                            <!-- Entertain -->
                            <div class="flex justify-between items-center">
                                <span class="text-gray-500">Budget Entertain:</span>
                                <div class="text-right">
                                    <span class="font-medium text-gray-800 dark:text-gray-200">Rp {{ number_format($pos['entertain_budget'], 0, ',', '.') }}</span>
                                    <span class="block text-[10px] {{ $pos['remaining_entertain'] < 0 ? 'text-red-500 font-bold' : 'text-emerald-600 dark:text-emerald-400' }}">
                                        Sisa: Rp {{ number_format($pos['remaining_entertain'], 0, ',', '.') }}
                                    </span>
                                </div>
                            </div>

                            <!-- Operasional -->
                            <div class="flex justify-between items-center">
                                <span class="text-gray-500">Budget Ops (BBM/Trans):</span>
                                <div class="text-right">
                                    <span class="font-medium text-gray-800 dark:text-gray-200">Rp {{ number_format($pos['operational_budget'], 0, ',', '.') }}</span>
                                    <span class="block text-[10px] {{ $pos['remaining_operational'] < 0 ? 'text-red-500 font-bold' : 'text-emerald-600 dark:text-emerald-400' }}">
                                        Sisa: Rp {{ number_format($pos['remaining_operational'], 0, ',', '.') }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Total Sisa Saldo Footer -->
                    <div class="mt-3 pt-2.5 border-t border-gray-200 dark:border-gray-700/60 flex items-center justify-between">
                        <span class="text-xs font-semibold text-gray-700 dark:text-gray-300">Total Sisa Budget:</span>
                        <span class="text-xs font-bold {{ $pos['remaining_total'] < 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                            Rp {{ number_format($pos['remaining_total'], 0, ',', '.') }}
                        </span>
                    </div>
                </div>
            @empty
                <div class="col-span-full text-center py-6 text-xs text-gray-500">
                    Belum ada data jabatan. Silakan tambahkan jabatan di Master Data.
                </div>
            @endforelse
        </div>
    </div>
</x-filament-widgets::widget>
