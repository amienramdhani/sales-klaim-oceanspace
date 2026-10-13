<div class="space-y-4">
    <!-- Action Bar (Print button) -->
    <div class="flex justify-between items-center print:hidden">
        <span class="text-xs font-semibold px-2.5 py-1 bg-cyan-100 text-cyan-800 dark:bg-cyan-900/50 dark:text-cyan-200 rounded-full">
            Kwitansi Resmi Pengganti Struk Uang Makan
        </span>
        <button type="button" onclick="window.print()" class="inline-flex items-center px-4 py-2 bg-cyan-700 hover:bg-cyan-800 text-white text-xs sm:text-sm font-semibold rounded-lg shadow-sm transition">
            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
            </svg>
            Cetak Kwitansi / Print Voucher
        </button>
    </div>

    <style>
        .kwitansi-container {
            background-color: #d7f1f5;
            color: #1e293b;
            font-family: 'Times New Roman', Georgia, serif;
            padding: 20px;
            border: 3px double #0e7490;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            position: relative;
            width: 100%;
            box-sizing: border-box;
        }
        .kwitansi-layout {
            display: flex;
            gap: 16px;
            align-items: stretch;
        }
        .kwitansi-side-bar {
            background-color: #0e7490;
            color: #ffffff;
            width: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.2);
            flex-shrink: 0;
        }
        .kwitansi-side-text {
            writing-mode: vertical-rl;
            transform: rotate(180deg);
            font-weight: 900;
            font-size: 20px;
            letter-spacing: 8px;
            text-transform: uppercase;
        }
        .kwitansi-main {
            flex: 1;
            padding: 4px 8px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-width: 0;
        }
        .kwitansi-row {
            display: flex;
            align-items: flex-start;
            margin-bottom: 10px;
            font-size: 14px;
        }
        .kwitansi-label {
            width: 150px;
            font-weight: 600;
            flex-shrink: 0;
        }
        .kwitansi-sep {
            margin-right: 8px;
            flex-shrink: 0;
        }
        .kwitansi-val {
            flex: 1;
            border-bottom: 1px dotted #475569;
            padding-bottom: 2px;
            min-width: 0;
            word-break: break-word;
        }
        .kwitansi-footer {
            margin-top: 20px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 16px;
        }
        @media (max-width: 640px) {
            .kwitansi-container {
                padding: 12px;
            }
            .kwitansi-layout {
                flex-direction: column;
                gap: 10px;
            }
            .kwitansi-side-bar {
                width: 100%;
                height: 36px;
                padding: 4px;
            }
            .kwitansi-side-text {
                writing-mode: horizontal-tb;
                transform: none;
                letter-spacing: 4px;
                font-size: 14px;
            }
            .kwitansi-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 2px;
                font-size: 13px;
                margin-bottom: 12px;
            }
            .kwitansi-label {
                width: 100%;
                font-size: 12px;
                color: #0369a1;
                font-weight: bold;
            }
            .kwitansi-sep {
                display: none;
            }
            .kwitansi-val {
                width: 100%;
                padding-left: 0;
                font-size: 13px;
            }
            .kwitansi-footer {
                flex-direction: column;
                align-items: center;
                gap: 16px;
                margin-top: 16px;
            }
            .kwitansi-amount-box {
                width: 100%;
                text-align: center;
            }
        }
    </style>

    <!-- Official Kwitansi Voucher Card -->
    <div id="kwitansi-voucher" class="kwitansi-container">
        <div class="kwitansi-layout">
            <!-- Vertical / Header Badge: KWITANSI -->
            <div class="kwitansi-side-bar">
                <span class="kwitansi-side-text">
                    KWITANSI
                </span>
            </div>

            <!-- Content -->
            <div class="kwitansi-main">
                <!-- Header No. & Badan Usaha -->
                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center pb-2 mb-2 border-b border-cyan-800/20 gap-1">
                    <div class="text-xs font-bold uppercase tracking-wider text-cyan-900">
                        {{ $record->badan_usaha ?: 'PT MEDIA SELULAR INDONESIA' }}
                    </div>
                    <div class="text-xs sm:text-sm font-bold text-slate-900">
                        No. : <span style="display: inline-block; min-width: 130px; border-bottom: 1px dotted #334155; padding-left: 6px;">{{ $record->kwitansi_number ?: ('KWT-MAKAN-' . date('Ym') . '-' . str_pad($record->id, 4, '0', STR_PAD_LEFT)) }}</span>
                    </div>
                </div>

                <!-- Fields -->
                <div class="flex flex-col gap-1 text-sm sm:text-base">
                    <!-- Telah Terima Dari -->
                    <div class="kwitansi-row">
                        <span class="kwitansi-label">Telah terima dari</span>
                        <span class="kwitansi-sep">:</span>
                        <span class="kwitansi-val font-bold text-slate-900">
                            FINANCE {{ strtoupper($record->badan_usaha ?: 'PT MEDIA SELULAR INDONESIA') }} (KAS OPERASIONAL)
                        </span>
                    </div>

                    <!-- Uang Sejumlah -->
                    <div class="kwitansi-row">
                        <span class="kwitansi-label">Uang sejumlah</span>
                        <span class="kwitansi-sep">:</span>
                        <span class="kwitansi-val italic font-semibold text-blue-900 bg-white/40 px-1.5 py-0.5 rounded">
                            # {{ $record->terbilang_meal_allowance }} Rupiah #
                        </span>
                    </div>

                    <!-- Untuk Pembayaran -->
                    <div class="kwitansi-row">
                        <span class="kwitansi-label">Untuk pembayaran</span>
                        <span class="kwitansi-sep">:</span>
                        <span class="kwitansi-val text-slate-800">
                            Uang Makan Perjalanan Dinas (Perdin) ke <strong>{{ $record->destination_city ?: 'Kota Tujuan' }}</strong> — 
                            <strong>{{ $record->days_count }} Hari</strong> x Rp {{ number_format(((float)$record->meal_allowance) / max(1, (int)$record->days_count), 0, ',', '.') }} / hari.
                            <br class="hidden sm:inline">
                            <span class="text-xs sm:text-sm text-slate-600">
                                Agenda: {{ $record->purpose ?: 'Operasional Lapangan' }} 
                                ({{ $record->claim_date ? $record->claim_date->format('d/m/Y') : '-' }} s/d {{ $record->perdin_return_date ? $record->perdin_return_date->format('d/m/Y') : ($record->claim_date ? $record->claim_date->addDays(max(0, (int)$record->days_count - 1))->format('d/m/Y') : '-') }})
                            </span>
                        </span>
                    </div>
                </div>

                <!-- Footer / Amount & Signature -->
                <div class="kwitansi-footer">
                    <!-- Amount Box -->
                    <div class="kwitansi-amount-box border-2 border-cyan-700 bg-white px-4 py-2 rounded shadow-[2px_2px_0px_#0e7490]">
                        <span class="text-xs block text-cyan-800 font-semibold uppercase tracking-wider">Total Uang Makan:</span>
                        <span class="text-lg sm:text-xl font-black font-mono tracking-wide text-cyan-800">
                            Rp. {{ number_format((float)$record->meal_allowance, 0, ',', '.') }},-
                        </span>
                    </div>

                    <!-- Date and Signatory -->
                    <div class="text-center min-w-[180px]">
                        <div class="text-xs sm:text-sm mb-1 text-slate-700">
                            {{ $record->destination_city ?: 'Purwokerto' }}, {{ $record->claim_date ? $record->claim_date->translatedFormat('d F Y') : date('d F Y') }}
                        </div>
                        <div class="h-12 flex items-center justify-center">
                            @if($record->effective_employee?->signature_image)
                                <img src="{{ asset('storage/' . $record->effective_employee->signature_image) }}" alt="Tanda Tangan" class="max-h-12" />
                            @else
                                <span class="text-[11px] text-slate-500 italic">[ Tanda Tangan ]</span>
                            @endif
                        </div>
                        <div class="border-t border-slate-700 pt-1 font-bold text-xs sm:text-sm uppercase text-slate-900">
                            ( {{ $record->effective_employee?->name ?? 'PEMOHON PERDIN' }} )
                        </div>
                        <div class="text-[11px] sm:text-xs text-slate-600">
                            {{ $record->effective_employee?->position_name ?? 'Karyawan' }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Notes: Kwitansi Pengganti Struk Makan -->
    <div class="rounded-lg bg-emerald-50 dark:bg-emerald-950/30 p-3 text-xs text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 print:hidden">
        <p class="font-semibold mb-0.5">Kwitansi Otomatis Pengganti Struk Makan:</p>
        <p>Kwitansi ini dibuat otomatis oleh sistem saat pengajuan dinas dibuat. Pemohon (Sales / ASM / RGM) <strong>tidak perlu melampirkan struk/bon makan fisik</strong>, cukup menggunakan kwitansi uang makan resmi ini.</p>
    </div>
</div>
