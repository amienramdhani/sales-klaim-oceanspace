<div class="space-y-4">
    <!-- Action Bar (Print button) -->
    <div class="flex justify-end gap-2 print:hidden">
        <button type="button" onclick="window.print()" class="inline-flex items-center px-4 py-2 bg-cyan-700 hover:bg-cyan-800 text-white text-sm font-semibold rounded-lg shadow-sm transition">
            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
            </svg>
            Cetak Kwitansi / Print Voucher
        </button>
    </div>

    <!-- Official Kwitansi Voucher Card -->
    <div id="kwitansi-voucher" style="background-color: #d7f1f5; color: #1e293b; font-family: 'Times New Roman', Georgia, serif; padding: 24px; border: 3px double #0e7490; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); position: relative;">
        <!-- Watermark / Background Texture -->
        <div style="display: flex; gap: 16px; align-items: stretch;">
            <!-- Left Vertical Column: KWITANSI -->
            <div style="background-color: #0e7490; color: #ffffff; width: 50px; display: flex; align-items: center; justify-content: center; border-radius: 4px; box-shadow: inset 0 2px 4px rgba(0,0,0,0.2);">
                <span style="writing-mode: vertical-rl; transform: rotate(180deg); font-weight: 900; font-size: 24px; letter-spacing: 10px; text-transform: uppercase;">
                    KWITANSI
                </span>
            </div>

            <!-- Right Content -->
            <div style="flex: 1; padding: 4px 12px; display: flex; flex-direction: column; justify-content: space-between;">
                <!-- Header No. -->
                <div style="display: flex; justify-content: flex-end; margin-bottom: 12px;">
                    <div style="font-size: 15px; font-weight: bold; color: #0f172a;">
                        No. : <span style="display: inline-block; min-width: 140px; border-bottom: 1px dotted #334155; padding-left: 6px;">{{ $record->kwitansi_number ?: ('KWT-PERDIN-' . date('Ym') . '-' . str_pad($record->id, 4, '0', STR_PAD_LEFT)) }}</span>
                    </div>
                </div>

                <!-- Fields -->
                <div style="display: flex; flex-direction: column; gap: 14px; font-size: 15px;">
                    <!-- Telah Terima Dari -->
                    <div style="display: flex; align-items: flex-start;">
                        <span style="width: 160px; font-weight: 600; flex-shrink: 0;">Telah terima dari</span>
                        <span style="margin-right: 8px;">:</span>
                        <span style="flex: 1; border-bottom: 1px dotted #475569; padding-bottom: 2px; font-weight: bold; color: #0f172a;">
                            FINANCE PERUSAHAAN (KAS OPERASIONAL)
                        </span>
                    </div>

                    <!-- Uang Sejumlah -->
                    <div style="display: flex; align-items: flex-start;">
                        <span style="width: 160px; font-weight: 600; flex-shrink: 0;">Uang sejumlah</span>
                        <span style="margin-right: 8px;">:</span>
                        <span style="flex: 1; border-bottom: 1px dotted #475569; padding-bottom: 2px; font-style: italic; font-weight: 600; color: #1e3a8a; background-color: rgba(255,255,255,0.4); padding-left: 6px;">
                            # {{ $record->terbilang_amount }} Rupiah #
                        </span>
                    </div>

                    <!-- Untuk Pembayaran -->
                    <div style="display: flex; align-items: flex-start;">
                        <span style="width: 160px; font-weight: 600; flex-shrink: 0;">Untuk pembayaran</span>
                        <span style="margin-right: 8px;">:</span>
                        <span style="flex: 1; border-bottom: 1px dotted #475569; padding-bottom: 2px;">
                            Biaya Uang Muka Perjalanan Dinas (Perdin) ke <strong>{{ $record->destination_city ?: ($record->branch?->name ?? 'Cabang Terkait') }}</strong> — 
                            Agenda: {{ $record->purpose ?: 'Tugas & Operasional Lapangan' }} 
                            ({{ $record->claim_date ? $record->claim_date->format('d/m/Y') : '-' }} s/d {{ $record->perdin_return_date ? $record->perdin_return_date->format('d/m/Y') : ($record->claim_date ? $record->claim_date->addDays((int)$record->days_count)->format('d/m/Y') : '-') }})
                        </span>
                    </div>
                </div>

                <!-- Footer / Amount & Signature -->
                <div style="margin-top: 26px; display: flex; justify-content: space-between; align-items: flex-end;">
                    <!-- Amount Box -->
                    <div style="border: 2px solid #0e7490; background-color: #ffffff; padding: 8px 18px; border-radius: 4px; box-shadow: 2px 2px 0px #0e7490;">
                        <span style="font-size: 20px; font-weight: 900; font-family: 'Courier New', Courier, monospace; letter-spacing: 1px; color: #0e7490;">
                            Rp. {{ number_format((float)$record->amount, 0, ',', '.') }},-
                        </span>
                    </div>

                    <!-- Date and Signatory -->
                    <div style="text-align: center; min-width: 200px;">
                        <div style="font-size: 14px; margin-bottom: 4px;">
                            {{ $record->homebase ?: ($record->branch?->name ?? 'Purwokerto') }}, {{ $record->disbursed_at ? $record->disbursed_at->translatedFormat('d F Y') : ($record->claim_date ? $record->claim_date->translatedFormat('d F Y') : date('d F Y')) }}
                        </div>
                        <div style="height: 55px; display: flex; align-items: center; justify-content: center;">
                            @if($record->effective_employee?->signature_image)
                                <img src="{{ asset('storage/' . $record->effective_employee->signature_image) }}" alt="Tanda Tangan" style="max-height: 50px;" />
                            @else
                                <span style="font-size: 11px; color: #64748b; font-style: italic;">[ Tanda Tangan ]</span>
                            @endif
                        </div>
                        <div style="border-top: 1px solid #334155; padding-top: 4px; font-weight: bold; font-size: 14px; text-transform: uppercase;">
                            ( {{ $record->effective_employee?->name ?? 'USER YANG MEMOHON' }} )
                        </div>
                        <div style="font-size: 12px; color: #475569;">
                            {{ $record->effective_employee?->position_name ?? 'Pemohon Perdin' }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Notes on Post-Trip Nota Balik -->
    <div class="rounded-lg bg-amber-50 dark:bg-amber-950/30 p-3 text-xs text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-800 print:hidden">
        <p class="font-semibold mb-0.5">Ketentuan Pertanggungjawaban Nota Balik & Sisa Dana Perdin:</p>
        <p>Maksimal H+1 setelah kepulangan ({{ $record->perdin_return_deadline ? $record->perdin_return_deadline->translatedFormat('d F Y') : '-' }}), penerima dana wajib menyerahkan seluruh bukti nota riil pengeluaran ke Admin. Jika ada sisa dana, sisa wajib ditransfer kembali ke Finance.</p>
    </div>
</div>
