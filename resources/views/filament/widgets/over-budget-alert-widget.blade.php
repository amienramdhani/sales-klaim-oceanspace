<x-filament-widgets::widget>
    @php
        $overEmployees = $this->overBudgetEmployees;
        $overPeriods = $this->overBudgetPeriods;
    @endphp

    @if ($overEmployees->count() > 0 || $overPeriods->count() > 0)
        <div class="p-4 rounded-xl bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-800/60 shadow-sm">
            <div class="flex items-start gap-3">
                <div class="p-2 rounded-lg bg-red-100 dark:bg-red-900/60 text-red-600 dark:text-red-400">
                    <x-heroicon-o-exclamation-triangle class="w-6 h-6" />
                </div>
                <div class="flex-1">
                    <h3 class="text-sm font-semibold text-red-800 dark:text-red-200">
                        Perhatian: Terdapat Pengeluaran Klaim yang Melebihi Plafon Anggaran!
                    </h3>
                    <p class="text-xs text-red-600 dark:text-red-300 mt-1">
                        Berikut adalah daftar karyawan / pengajuan yang melebihi batas budget:
                    </p>
                    <div class="mt-3 divide-y divide-red-200/60 dark:divide-red-800/40">
                        @foreach ($overEmployees as $emp)
                            @php
                                $budget = (float)$emp->entertain_budget > 0 ? (float)$emp->entertain_budget : (float)$emp->total_budget;
                                $used = (float)$emp->total_expense_used;
                                $over = $used - $budget;
                            @endphp
                            <div class="py-2 flex flex-wrap items-center justify-between gap-2 text-xs">
                                <div>
                                    <span class="font-bold text-red-900 dark:text-red-100">{{ $emp->name }}</span>
                                    <span class="text-red-700 dark:text-red-300"> — {{ $emp->position_name }}</span>
                                </div>
                                <div class="flex items-center gap-4">
                                    <span class="text-gray-600 dark:text-gray-400">Plafon: <strong>Rp {{ number_format($budget, 0, ',', '.') }}</strong></span>
                                    <span class="text-gray-600 dark:text-gray-400">Terpakai: <strong>Rp {{ number_format($used, 0, ',', '.') }}</strong></span>
                                    <span class="font-bold text-red-600 dark:text-red-400">Over Budget: Rp {{ number_format($over, 0, ',', '.') }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endif
</x-filament-widgets::widget>
