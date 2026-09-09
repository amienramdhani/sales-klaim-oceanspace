<div class="space-y-4">
    @php
        $m = isset($month) && $month ? (int)$month : null;
        $y = isset($year) && $year ? (int)$year : null;
        $monthNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];
        $periodLabel = ($m ? $monthNames[$m] . ' ' : 'Semua Bulan ') . ($y ?: date('Y'));

        $bbmBudget = (float)$employee->bbm_budget;
        $bbmUsed = (float)$employee->getUsedBbmForPeriod($m, $y);
        $bbmDiff = $bbmBudget - $bbmUsed;
        $bbmIsOver = $bbmBudget > 0 && $bbmDiff < 0;

        $entBudget = (float)$employee->entertain_budget;
        $entUsed = (float)$employee->getUsedEntertainForPeriod($m, $y, true);
        $entDiff = $entBudget - $entUsed;
        $entIsOver = $entBudget > 0 && $entDiff < 0;

        $perdinBudget = (float)$employee->perdin_budget;
        $perdinUsed = (float)$employee->getUsedPerdinForPeriod($m, $y);
        $perdinDiff = $perdinBudget - $perdinUsed;
        $perdinIsOver = $perdinBudget > 0 && $perdinDiff < 0;

        $srvBudget = (float)$employee->service_motor_budget;
        $srvUsed = (float)$employee->getUsedTransportForPeriod($m, $y);
        $srvDiff = $srvBudget - $srvUsed;
        $srvIsOver = $srvBudget > 0 && $srvDiff < 0;

        $totalBudget = (float)$employee->total_budget;
        $totalUsed = (float)$employee->getTotalExpenseUsedForPeriod($m, $y);
        $totalDiff = $totalBudget - $totalUsed;
        $totalIsOver = $totalBudget > 0 && $totalDiff < 0;
    @endphp

    <!-- Header Summary Card -->
    <div class="p-4 bg-slate-50 dark:bg-slate-800/60 rounded-xl border border-slate-200 dark:border-slate-700 flex flex-col md:flex-row justify-between items-start md:items-center gap-3">
        <div>
            <div class="flex items-center gap-2">
                <h4 class="text-base font-bold text-slate-800 dark:text-slate-100">{{ $employee->name }}</h4>
                <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-teal-100 text-teal-800 dark:bg-teal-900/60 dark:text-teal-300">
                    Periode: {{ $periodLabel }}
                </span>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                Jabatan: <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $employee->position_name }}</span> | 
                Homebase: <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $employee->homebase ?: '-' }}</span>
            </p>
        </div>
        <div class="text-right">
            <span class="text-xs text-slate-500 dark:text-slate-400 block">Total Biaya Terpakai (Periode Ini)</span>
            <span class="text-lg font-extrabold text-teal-600 dark:text-teal-400">Rp {{ number_format($totalUsed, 0, ',', '.') }}</span>
        </div>
    </div>

    <!-- Category Balances Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <!-- Card 1: BBM -->
        <div class="p-4 rounded-xl border {{ $bbmIsOver ? 'bg-red-50/50 border-red-200 dark:bg-red-950/20 dark:border-red-800' : 'bg-white border-slate-200 dark:bg-slate-900 dark:border-slate-800' }}">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold text-slate-700 dark:text-slate-200 flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full {{ $bbmIsOver ? 'bg-red-500 animate-pulse' : 'bg-blue-500' }}"></span>
                    Klaim BBM (Bahan Bakar)
                </span>
                @if($bbmIsOver)
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-red-100 text-red-700 dark:bg-red-900/60 dark:text-red-300">
                        ⚠️ LEWAT BATAS (+Rp {{ number_format(abs($bbmDiff), 0, ',', '.') }})
                    </span>
                @elseif($bbmBudget > 0)
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/60 dark:text-emerald-300">
                        ✅ Sesuai Plafon
                    </span>
                @else
                    <span class="text-[10px] font-medium px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                        Tanpa Batas
                    </span>
                @endif
            </div>
            <div class="grid grid-cols-3 gap-2 text-center text-xs mt-3">
                <div class="p-2 bg-slate-50 dark:bg-slate-800/40 rounded-lg">
                    <span class="text-[10px] text-slate-400 block">Plafon</span>
                    <span class="font-bold text-slate-700 dark:text-slate-200">{{ $bbmBudget > 0 ? 'Rp ' . number_format($bbmBudget, 0, ',', '.') : '-' }}</span>
                </div>
                <div class="p-2 bg-slate-50 dark:bg-slate-800/40 rounded-lg">
                    <span class="text-[10px] text-slate-400 block">Terpakai</span>
                    <span class="font-bold text-slate-800 dark:text-slate-100">Rp {{ number_format($bbmUsed, 0, ',', '.') }}</span>
                </div>
                <div class="p-2 rounded-lg {{ $bbmIsOver ? 'bg-red-100/70 text-red-700 dark:bg-red-900/40 dark:text-red-300' : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' }}">
                    <span class="text-[10px] block opacity-80">Sisa Saldo</span>
                    <span class="font-extrabold">{{ $bbmBudget > 0 ? ($bbmDiff < 0 ? '-Rp ' : 'Rp ') . number_format(abs($bbmDiff), 0, ',', '.') : '-' }}</span>
                </div>
            </div>
        </div>

        <!-- Card 2: Entertain -->
        <div class="p-4 rounded-xl border {{ $entIsOver ? 'bg-red-50/50 border-red-200 dark:bg-red-950/20 dark:border-red-800' : 'bg-white border-slate-200 dark:bg-slate-900 dark:border-slate-800' }}">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold text-slate-700 dark:text-slate-200 flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full {{ $entIsOver ? 'bg-red-500 animate-pulse' : 'bg-amber-500' }}"></span>
                    Klaim Entertain (Makan)
                </span>
                @if($entIsOver)
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-red-100 text-red-700 dark:bg-red-900/60 dark:text-red-300">
                        ⚠️ LEWAT BATAS (+Rp {{ number_format(abs($entDiff), 0, ',', '.') }})
                    </span>
                @elseif($entBudget > 0)
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/60 dark:text-emerald-300">
                        ✅ Sesuai Plafon
                    </span>
                @else
                    <span class="text-[10px] font-medium px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                        Tanpa Batas
                    </span>
                @endif
            </div>
            <div class="grid grid-cols-3 gap-2 text-center text-xs mt-3">
                <div class="p-2 bg-slate-50 dark:bg-slate-800/40 rounded-lg">
                    <span class="text-[10px] text-slate-400 block">Plafon</span>
                    <span class="font-bold text-slate-700 dark:text-slate-200">{{ $entBudget > 0 ? 'Rp ' . number_format($entBudget, 0, ',', '.') : '-' }}</span>
                </div>
                <div class="p-2 bg-slate-50 dark:bg-slate-800/40 rounded-lg">
                    <span class="text-[10px] text-slate-400 block">Terpakai</span>
                    <span class="font-bold text-slate-800 dark:text-slate-100">Rp {{ number_format($entUsed, 0, ',', '.') }}</span>
                </div>
                <div class="p-2 rounded-lg {{ $entIsOver ? 'bg-red-100/70 text-red-700 dark:bg-red-900/40 dark:text-red-300' : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' }}">
                    <span class="text-[10px] block opacity-80">Sisa Saldo</span>
                    <span class="font-extrabold">{{ $entBudget > 0 ? ($entDiff < 0 ? '-Rp ' : 'Rp ') . number_format(abs($entDiff), 0, ',', '.') : '-' }}</span>
                </div>
            </div>
        </div>

        <!-- Card 3: Perjalanan Dinas -->
        <div class="p-4 rounded-xl border {{ $perdinIsOver ? 'bg-red-50/50 border-red-200 dark:bg-red-950/20 dark:border-red-800' : 'bg-white border-slate-200 dark:bg-slate-900 dark:border-slate-800' }}">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold text-slate-700 dark:text-slate-200 flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full {{ $perdinIsOver ? 'bg-red-500 animate-pulse' : 'bg-indigo-500' }}"></span>
                    Perjalanan Dinas (Perdin)
                </span>
                @if($perdinIsOver)
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-red-100 text-red-700 dark:bg-red-900/60 dark:text-red-300">
                        ⚠️ LEWAT BATAS (+Rp {{ number_format(abs($perdinDiff), 0, ',', '.') }})
                    </span>
                @elseif($perdinBudget > 0)
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/60 dark:text-emerald-300">
                        ✅ Sesuai Plafon
                    </span>
                @else
                    <span class="text-[10px] font-medium px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                        Sesuai Form
                    </span>
                @endif
            </div>
            <div class="grid grid-cols-3 gap-2 text-center text-xs mt-3">
                <div class="p-2 bg-slate-50 dark:bg-slate-800/40 rounded-lg">
                    <span class="text-[10px] text-slate-400 block">Plafon</span>
                    <span class="font-bold text-slate-700 dark:text-slate-200">{{ $perdinBudget > 0 ? 'Rp ' . number_format($perdinBudget, 0, ',', '.') : '-' }}</span>
                </div>
                <div class="p-2 bg-slate-50 dark:bg-slate-800/40 rounded-lg">
                    <span class="text-[10px] text-slate-400 block">Terpakai</span>
                    <span class="font-bold text-slate-800 dark:text-slate-100">Rp {{ number_format($perdinUsed, 0, ',', '.') }}</span>
                </div>
                <div class="p-2 rounded-lg {{ $perdinIsOver ? 'bg-red-100/70 text-red-700 dark:bg-red-900/40 dark:text-red-300' : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' }}">
                    <span class="text-[10px] block opacity-80">Sisa Saldo</span>
                    <span class="font-extrabold">{{ $perdinBudget > 0 ? ($perdinDiff < 0 ? '-Rp ' : 'Rp ') . number_format(abs($perdinDiff), 0, ',', '.') : '-' }}</span>
                </div>
            </div>
        </div>

        <!-- Card 4: Service Kendaraan / Transport -->
        <div class="p-4 rounded-xl border {{ $srvIsOver ? 'bg-red-50/50 border-red-200 dark:bg-red-950/20 dark:border-red-800' : 'bg-white border-slate-200 dark:bg-slate-900 dark:border-slate-800' }}">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold text-slate-700 dark:text-slate-200 flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full {{ $srvIsOver ? 'bg-red-500 animate-pulse' : 'bg-slate-500' }}"></span>
                    Service Kendaraan / Transport
                </span>
                @if($srvIsOver)
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-red-100 text-red-700 dark:bg-red-900/60 dark:text-red-300">
                        ⚠️ LEWAT BATAS (+Rp {{ number_format(abs($srvDiff), 0, ',', '.') }})
                    </span>
                @elseif($srvBudget > 0)
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/60 dark:text-emerald-300">
                        ✅ Sesuai Plafon
                    </span>
                @else
                    <span class="text-[10px] font-medium px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                        Tanpa Batas
                    </span>
                @endif
            </div>
            <div class="grid grid-cols-3 gap-2 text-center text-xs mt-3">
                <div class="p-2 bg-slate-50 dark:bg-slate-800/40 rounded-lg">
                    <span class="text-[10px] text-slate-400 block">Plafon</span>
                    <span class="font-bold text-slate-700 dark:text-slate-200">{{ $srvBudget > 0 ? 'Rp ' . number_format($srvBudget, 0, ',', '.') : '-' }}</span>
                </div>
                <div class="p-2 bg-slate-50 dark:bg-slate-800/40 rounded-lg">
                    <span class="text-[10px] text-slate-400 block">Terpakai</span>
                    <span class="font-bold text-slate-800 dark:text-slate-100">Rp {{ number_format($srvUsed, 0, ',', '.') }}</span>
                </div>
                <div class="p-2 rounded-lg {{ $srvIsOver ? 'bg-red-100/70 text-red-700 dark:bg-red-900/40 dark:text-red-300' : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' }}">
                    <span class="text-[10px] block opacity-80">Sisa Saldo</span>
                    <span class="font-extrabold">{{ $srvBudget > 0 ? ($srvDiff < 0 ? '-Rp ' : 'Rp ') . number_format(abs($srvDiff), 0, ',', '.') : '-' }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Overall Budget Card -->
    <div class="p-4 bg-slate-100 dark:bg-slate-800 rounded-xl border border-slate-300 dark:border-slate-700">
        <div class="flex items-center justify-between">
            <div>
                <span class="text-xs font-bold text-slate-700 dark:text-slate-200 block">RINGKASAN TOTAL ANGGARAN</span>
                <span class="text-[11px] text-slate-500 dark:text-slate-400">Total akumulasi semua plafon dan seluruh kategori klaim</span>
            </div>
            <div class="text-right">
                <span class="text-xs text-slate-500 dark:text-slate-400 block">Total Sisa Saldo:</span>
                <span class="text-base font-extrabold {{ $totalIsOver ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                    {{ $totalBudget > 0 ? ($totalDiff < 0 ? '-Rp ' : 'Rp ') . number_format(abs($totalDiff), 0, ',', '.') : '-' }}
                </span>
            </div>
        </div>
    </div>
</div>
