<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekapitulasi Klaim BBM - PT Media Selular Indonesia</title>
    <style>
        @page {
            margin: 18px 20px 20px 20px;
            size: A4 landscape;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 8px;
            color: #1e293b;
            line-height: 1.25;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-bottom: 2px double #0f172a;
            padding-bottom: 6px;
            margin-bottom: 8px;
        }
        .company-title {
            font-size: 12px;
            font-weight: bold;
            color: #0f172a;
            letter-spacing: 0.5px;
        }
        .company-subtitle {
            font-size: 7.5px;
            color: #475569;
        }
        .report-title {
            font-size: 11px;
            font-weight: bold;
            text-align: center;
            color: #0f766e;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 4px 0 2px 0;
        }
        .report-period {
            font-size: 8px;
            text-align: center;
            color: #64748b;
            margin-bottom: 8px;
        }
        .metric-boxes {
            width: 100%;
            margin-bottom: 8px;
            border-collapse: collapse;
        }
        .metric-card {
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 5px 8px;
            text-align: center;
        }
        .metric-label {
            font-size: 7px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: bold;
        }
        .metric-val {
            font-size: 10px;
            font-weight: bold;
            color: #0f172a;
            margin-top: 1px;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7.5px;
        }
        table.data-table th, table.data-table td {
            border: 0.5px solid #94a3b8;
            padding: 3.5px 4px;
        }
        table.data-table th {
            background-color: #1e293b;
            color: #ffffff;
            font-weight: bold;
            text-align: center;
        }
        table.data-table tr.even {
            background-color: #f8fafc;
        }
        table.data-table tr.total-row {
            background-color: #e2e8f0;
            font-weight: bold;
            font-size: 8px;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .badge {
            display: inline-block;
            padding: 1.5px 4px;
            border-radius: 3px;
            font-size: 7px;
            font-weight: bold;
        }
        .badge-success { background-color: #dcfce7; color: #166534; }
        .badge-warning { background-color: #fef3c7; color: #92400e; }
        .badge-danger { background-color: #fee2e2; color: #991b1b; }
        .badge-info { background-color: #e0f2fe; color: #075985; }
        .badge-gray { background-color: #f1f5f9; color: #475569; }

        .sig-table {
            width: 100%;
            margin-top: 15px;
            font-size: 8px;
            border-collapse: collapse;
            page-break-inside: avoid;
        }
        .sig-table td {
            text-align: center;
            vertical-align: top;
            width: 25%;
            padding: 0 4px;
        }
    </style>
</head>
<body>

    <!-- KOP PERUSAHAAN -->
    <table class="header-table">
        <tr>
            <td style="width: 12%; vertical-align: middle;">
                @if(!empty($logoBase64))
                    <img src="{{ $logoBase64 }}" style="max-height: 38px; max-width: 80px;" alt="Logo MSI">
                @else
                    <div style="font-weight: bold; font-size: 14px; color: #0f766e;">MSI</div>
                @endif
            </td>
            <td style="width: 58%; vertical-align: middle;">
                <div class="company-title">PT. MEDIA SELULAR INDONESIA</div>
                <div class="company-subtitle">PUSAT GROSIR & DISTRIBUSI ACCESSORIES HANDPHONE & GADGET</div>
                <div class="company-subtitle">Jl. Tuparev No. 109F Kertawinangun, Kedawung - Cirebon 45153</div>
            </td>
            <td style="width: 30%; text-align: right; vertical-align: middle;">
                <div style="font-size: 9px; font-weight: bold; color: #0f766e;">REKAP KLAIM OPERASIONAL</div>
                <div style="font-size: 7.5px; color: #64748b;">Dicetak: {{ date('d/m/Y H:i') }} WIB</div>
            </td>
        </tr>
    </table>

    <div class="report-title">REKAPITULASI PENGAJUAN KLAIM BAHAN BAKAR MINYAK (BBM)</div>
    <div class="report-period">
        {{ $periodLabel }} | Total: {{ count($claims) }} Pengajuan Klaim
    </div>

    <!-- METRIK RINGKASAN -->
    @php
        $disbursedCount = 0;
        $disbursedAmount = 0;
        foreach($claims as $c) {
            if ($c->disbursement_status === 'Sudah Dicairkan') {
                $disbursedCount++;
                $disbursedAmount += (float)$c->amount;
            }
        }
    @endphp
    <table class="metric-boxes">
        <tr>
            <td style="width: 25%; padding: 0 3px;">
                <div class="metric-card">
                    <div class="metric-label">TOTAL PENGAJUAN BBM</div>
                    <div class="metric-val">{{ count($claims) }} Transaksi</div>
                </div>
            </td>
            <td style="width: 25%; padding: 0 3px;">
                <div class="metric-card">
                    <div class="metric-label">TOTAL VOLUME BBM</div>
                    <div class="metric-val" style="color: #0f766e;">{{ number_format($totalLiters, 2, ',', '.') }} Liter</div>
                </div>
            </td>
            <td style="width: 25%; padding: 0 3px;">
                <div class="metric-card">
                    <div class="metric-label">TOTAL BIAYA BBM (Rp)</div>
                    <div class="metric-val" style="color: #0f172a;">Rp {{ number_format($totalAmount, 0, ',', '.') }}</div>
                </div>
            </td>
            <td style="width: 25%; padding: 0 3px;">
                <div class="metric-card">
                    <div class="metric-label">SUDAH DICAIRKAN</div>
                    <div class="metric-val" style="color: #166534;">
                        Rp {{ number_format($disbursedAmount, 0, ',', '.') }} ({{ $disbursedCount }})
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <!-- TABEL DATA REKAPAN BBM -->
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 20px;">NO</th>
                <th style="width: 55px;">TANGGAL</th>
                <th style="width: 70px;">_UID</th>
                <th style="width: 100px; text-align: left;">NAMA PEMOHON</th>
                <th style="width: 75px; text-align: left;">JABATAN</th>
                <th style="width: 75px; text-align: left;">REGION</th>
                <th style="width: 75px;">KENDARAAN</th>
                <th style="width: 75px;">JENIS BBM</th>
                <th style="width: 50px;">KM ODO</th>
                <th style="width: 50px;">LITER</th>
                <th style="width: 75px;">NOMINAL (Rp)</th>
                <th style="width: 60px;">APPROVAL</th>
                <th style="width: 65px;">PENCAIRAN</th>
            </tr>
        </thead>
        <tbody>
            @forelse($claims as $idx => $claim)
                @php 
                    $emp = $claim->effective_employee;
                    $comp = $claim->fuel_compliance;
                @endphp
                <tr class="{{ $idx % 2 === 1 ? 'even' : '' }}">
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td class="text-center">{{ $claim->claim_date ? $claim->claim_date->format('d/m/Y') : '-' }}</td>
                    <td class="text-center" style="font-family: monospace; font-weight: bold;">{{ $claim->_uid ?: '-' }}</td>
                    <td class="text-left font-bold" style="font-weight: bold;">{{ $emp?->name ?? '-' }}</td>
                    <td class="text-left">{{ $emp?->position_name ?? ($emp?->role?->name ?? 'Sales') }}</td>
                    <td class="text-left">{{ $claim->region ?: ($emp?->region ?? '-') }}</td>
                    <td class="text-center">
                        {{ $claim->vehicle_type ?: 'Mobil' }}
                        @if($claim->vehicle_plate)
                            <div style="font-size: 6.5px; color: #64748b;">{{ $claim->vehicle_plate }}</div>
                        @endif
                    </td>
                    <td class="text-center">
                        <div><strong>{{ $claim->fuel_type ?: 'Pertalite' }}</strong></div>
                        <span class="badge {{ $comp['color'] === 'success' ? 'badge-success' : ($comp['color'] === 'danger' ? 'badge-danger' : 'badge-warning') }}">
                            {{ $comp['label'] }}
                        </span>
                    </td>
                    <td class="text-center">{{ $claim->fuel_start_km ? number_format($claim->fuel_start_km, 0, ',', '.') : '-' }}</td>
                    <td class="text-center">{{ $claim->fuel_liters ? number_format($claim->fuel_liters, 2, ',', '.') : '-' }}</td>
                    <td class="text-right font-bold" style="font-weight: bold;">
                        {{ number_format((float)$claim->amount, 0, ',', '.') }}
                    </td>
                    <td class="text-center">
                        <span class="badge {{ in_array($claim->approval_status, ['DISETUJUI', 'ACC_PAK_JEJEN']) ? 'badge-success' : ($claim->approval_status === 'DITOLAK' ? 'badge-danger' : 'badge-warning') }}">
                            {{ $claim->approval_status ?? 'DRAFT' }}
                        </span>
                    </td>
                    <td class="text-center">
                        <span class="badge {{ $claim->disbursement_status === 'Sudah Dicairkan' ? 'badge-success' : 'badge-danger' }}">
                            {{ $claim->disbursement_status === 'Sudah Dicairkan' ? 'Dicairkan' : 'Belum' }}
                        </span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="13" class="text-center" style="color: #94a3b8; padding: 15px;">
                        Tidak ada data klaim BBM untuk filter ini.
                    </td>
                </tr>
            @endforelse

            <tr class="total-row">
                <td colspan="9" class="text-right" style="padding-right: 8px;">TOTAL KESELURUHAN :</td>
                <td class="text-center" style="color: #0f766e;">{{ number_format($totalLiters, 2, ',', '.') }} L</td>
                <td class="text-right" style="color: #0f766e;">
                    Rp {{ number_format($totalAmount, 0, ',', '.') }}
                </td>
                <td colspan="2" class="text-center">
                    {{ count($claims) }} Transaksi
                </td>
            </tr>
        </tbody>
    </table>

    <!-- TANDA TANGAN / PENGESAHAN LAPORAN -->
    <table class="sig-table">
        <tr>
            <td>
                <div>Dibuat Oleh,</div>
                <div style="font-weight: bold; margin-top: 2px;">Admin Klaim</div>
                <div style="height: 35px;"></div>
                <div>( ..................................... )</div>
            </td>
            <td>
                <div>Diperiksa Oleh,</div>
                <div style="font-weight: bold; margin-top: 2px;">Area Sales Manager (ASM)</div>
                <div style="height: 35px;"></div>
                <div>( ..................................... )</div>
            </td>
            <td>
                <div>Disetujui Oleh,</div>
                <div style="font-weight: bold; margin-top: 2px;">RGM / Management</div>
                <div style="height: 35px;"></div>
                <div>( ..................................... )</div>
            </td>
            <td>
                <div>Diketahui & Dicairkan,</div>
                <div style="font-weight: bold; margin-top: 2px;">Finance & Kasir</div>
                <div style="height: 35px;"></div>
                <div>( ..................................... )</div>
            </td>
        </tr>
    </table>

</body>
</html>
