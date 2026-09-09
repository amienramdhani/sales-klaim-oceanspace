<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekapan Budget & Sisa Saldo Karyawan - PT Media Selular Indonesia</title>
    <style>
        @page {
            margin: 18px 20px 20px 20px;
            size: A4 landscape;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 8.5px;
            color: #1e293b;
            line-height: 1.2;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-bottom: 2px double #1e293b;
            padding-bottom: 6px;
            margin-bottom: 8px;
        }
        .company-title {
            font-size: 13px;
            font-weight: bold;
            color: #0f172a;
            letter-spacing: 0.5px;
        }
        .company-subtitle {
            font-size: 8.5px;
            color: #475569;
        }
        .report-title {
            font-size: 11px;
            font-weight: bold;
            text-align: center;
            color: #0f766e;
            text-transform: uppercase;
            margin: 4px 0 2px 0;
        }
        .report-period {
            font-size: 8.5px;
            text-align: center;
            color: #64748b;
            margin-bottom: 8px;
        }
        .metric-boxes {
            width: 100%;
            margin-bottom: 8px;
        }
        .metric-card {
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 5px 8px;
            text-align: center;
        }
        .metric-label {
            font-size: 7.5px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: bold;
        }
        .metric-val {
            font-size: 10px;
            font-weight: bold;
            color: #0f172a;
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
        table.data-table th.sub-th {
            background-color: #334155;
            color: #f1f5f9;
            font-size: 7px;
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
        .badge-danger { background-color: #fee2e2; color: #991b1b; }
        .badge-gray { background-color: #f1f5f9; color: #475569; }
        .sig-table {
            width: 100%;
            margin-top: 12px;
            font-size: 8px;
        }
        .sig-table td {
            text-align: center;
            vertical-align: top;
            width: 25%;
        }
    </style>
</head>
<body>

    <!-- KOP PERUSAHAAN -->
    <table class="header-table">
        <tr>
            <td style="width: 70%;">
                <div class="company-title">PT. MEDIA SELULAR INDONESIA</div>
                <div class="company-subtitle">PUSAT GROSIR & ECERAN ACCESSORIES HANDPHONE & GADGET</div>
                <div class="company-subtitle">Jl. Tuparev No. 109F Kertawinangun, Kedawung - Cirebon 45153</div>
            </td>
            <td style="width: 30%; text-align: right;">
                <div style="font-size: 9px; font-weight: bold; color: #0f766e;">SISTEM KLAIM OPERASIONAL</div>
                <div style="font-size: 7.5px; color: #64748b;">Dicetak: {{ date('d/m/Y H:i') }} WIB</div>
            </td>
        </tr>
    </table>

    <div class="report-title">REKAPAN BUDGET & SISA SALDO KARYAWAN</div>
    <div class="report-period">
        Periode: {{ $periodLabel ?? 'Semua Periode / Kumulatif' }} | Total Karyawan: {{ count($employees) }} Orang
    </div>

    <!-- METRIK RINGKASAN -->
    <table class="metric-boxes">
        <tr>
            <td style="width: 25%;">
                <div class="metric-card">
                    <div class="metric-label">TOTAL PLAFON DISETUJUI</div>
                    <div class="metric-val">Rp {{ number_format($summary['total_budget'], 0, ',', '.') }}</div>
                </div>
            </td>
            <td style="width: 25%;">
                <div class="metric-card">
                    <div class="metric-label">TOTAL REALISASI PEMAKAIAN</div>
                    <div class="metric-val" style="color: #0f766e;">Rp {{ number_format($summary['total_used'], 0, ',', '.') }}</div>
                </div>
            </td>
            <td style="width: 25%;">
                <div class="metric-card">
                    <div class="metric-label">TOTAL SISA SALDO BERSIH</div>
                    <div class="metric-val" style="color: {{ $summary['total_remaining'] >= 0 ? '#166534' : '#dc2626' }};">
                        Rp {{ number_format($summary['total_remaining'], 0, ',', '.') }}
                    </div>
                </div>
            </td>
            <td style="width: 25%;">
                <div class="metric-card">
                    <div class="metric-label">PERSENTASE SERAPAN DANA</div>
                    <div class="metric-val">
                        {{ $summary['total_budget'] > 0 ? round(($summary['total_used'] / $summary['total_budget']) * 100, 1) : 0 }}%
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <!-- TABEL DATA REKAPAN BUDGET PER USER -->
    <table class="data-table">
        <thead>
            <tr>
                <th rowspan="2" style="width: 20px;">NO</th>
                <th rowspan="2" style="width: 95px;">NAMA KARYAWAN</th>
                <th rowspan="2" style="width: 70px;">JABATAN / AREA</th>
                <th colspan="3">BBM (Rp)</th>
                <th colspan="3">ENTERTAIN (Rp)</th>
                <th colspan="3">PERJALANAN DINAS (Rp)</th>
                <th colspan="3">SERVICE & TRANSPORT (Rp)</th>
                <th colspan="3">TOTAL KESELURUHAN (Rp)</th>
                <th rowspan="2" style="width: 48px;">STATUS</th>
            </tr>
            <tr>
                <th class="sub-th">Plafon</th>
                <th class="sub-th">Pakai</th>
                <th class="sub-th">Sisa</th>
                <th class="sub-th">Plafon</th>
                <th class="sub-th">Pakai</th>
                <th class="sub-th">Sisa</th>
                <th class="sub-th">Plafon</th>
                <th class="sub-th">Pakai</th>
                <th class="sub-th">Sisa</th>
                <th class="sub-th">Plafon</th>
                <th class="sub-th">Pakai</th>
                <th class="sub-th">Sisa</th>
                <th class="sub-th">Plafon</th>
                <th class="sub-th">Pakai</th>
                <th class="sub-th">Sisa</th>
            </tr>
        </thead>
        <tbody>
            @php $no = 1; @endphp
            @foreach($employees as $row)
                <tr class="{{ $no % 2 === 0 ? 'even' : '' }}">
                    <td class="text-center">{{ $no }}</td>
                    <td class="text-left font-bold" style="font-weight: bold;">{{ $row['name'] }}</td>
                    <td class="text-left">{{ $row['position'] }}</td>

                    <!-- BBM -->
                    <td class="text-right">{{ number_format($row['bbm_budget'], 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($row['bbm_used'], 0, ',', '.') }}</td>
                    <td class="text-right" style="color: {{ $row['bbm_remaining'] >= 0 ? '#166534' : '#dc2626' }};">
                        {{ number_format($row['bbm_remaining'], 0, ',', '.') }}
                    </td>

                    <!-- Entertain -->
                    <td class="text-right">{{ number_format($row['ent_budget'], 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($row['ent_used'], 0, ',', '.') }}</td>
                    <td class="text-right" style="color: {{ $row['ent_remaining'] >= 0 ? '#166534' : '#dc2626' }};">
                        {{ number_format($row['ent_remaining'], 0, ',', '.') }}
                    </td>

                    <!-- Perdin -->
                    <td class="text-right">{{ number_format($row['perdin_budget'], 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($row['perdin_used'], 0, ',', '.') }}</td>
                    <td class="text-right" style="color: {{ $row['perdin_remaining'] >= 0 ? '#166534' : '#dc2626' }};">
                        {{ number_format($row['perdin_remaining'], 0, ',', '.') }}
                    </td>

                    <!-- Service / Transport -->
                    <td class="text-right">{{ number_format($row['trans_budget'], 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($row['trans_used'], 0, ',', '.') }}</td>
                    <td class="text-right" style="color: {{ $row['trans_remaining'] >= 0 ? '#166534' : '#dc2626' }};">
                        {{ number_format($row['trans_remaining'], 0, ',', '.') }}
                    </td>

                    <!-- Total -->
                    <td class="text-right" style="font-weight: bold;">{{ number_format($row['total_budget'], 0, ',', '.') }}</td>
                    <td class="text-right" style="font-weight: bold; color: #0f766e;">{{ number_format($row['total_used'], 0, ',', '.') }}</td>
                    <td class="text-right" style="font-weight: bold; color: {{ $row['total_remaining'] >= 0 ? '#166534' : '#dc2626' }};">
                        {{ number_format($row['total_remaining'], 0, ',', '.') }}
                    </td>

                    <!-- Status -->
                    <td class="text-center">
                        @if($row['is_over'])
                            <span class="badge badge-danger">⚠️ Over</span>
                        @else
                            <span class="badge badge-success">✅ Aman</span>
                        @endif
                    </td>
                </tr>
                @php $no++; @endphp
            @endforeach

            <!-- BARIS TOTAL KESELURUHAN -->
            <tr class="total-row">
                <td colspan="3" class="text-right" style="text-align: right; padding-right: 8px;">TOTAL AKUMULASI :</td>
                
                <!-- BBM Total -->
                <td class="text-right">{{ number_format($summary['total_bbm_budget'], 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($summary['total_bbm_used'], 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($summary['total_bbm_remaining'], 0, ',', '.') }}</td>

                <!-- Entertain Total -->
                <td class="text-right">{{ number_format($summary['total_ent_budget'], 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($summary['total_ent_used'], 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($summary['total_ent_remaining'], 0, ',', '.') }}</td>

                <!-- Perdin Total -->
                <td class="text-right">{{ number_format($summary['total_perdin_budget'], 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($summary['total_perdin_used'], 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($summary['total_perdin_remaining'], 0, ',', '.') }}</td>

                <!-- Transport Total -->
                <td class="text-right">{{ number_format($summary['total_trans_budget'], 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($summary['total_trans_used'], 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($summary['total_trans_remaining'], 0, ',', '.') }}</td>

                <!-- Grand Total -->
                <td class="text-right" style="color: #0f172a;">{{ number_format($summary['total_budget'], 0, ',', '.') }}</td>
                <td class="text-right" style="color: #0f766e;">{{ number_format($summary['total_used'], 0, ',', '.') }}</td>
                <td class="text-right" style="color: {{ $summary['total_remaining'] >= 0 ? '#166534' : '#dc2626' }};">
                    {{ number_format($summary['total_remaining'], 0, ',', '.') }}
                </td>

                <td class="text-center">-</td>
            </tr>
        </tbody>
    </table>

    <!-- TANDA TANGAN / PENGESAHAN LAPORAN -->
    <table class="sig-table">
        <tr>
            <td>
                <div>Dibuat Oleh,</div>
                <div style="font-weight: bold; margin-bottom: 30px;">Admin Klaim</div>
                <div>( ..................................... )</div>
            </td>
            <td>
                <div>Diperiksa Oleh,</div>
                <div style="font-weight: bold; margin-bottom: 30px;">Area Sales Manager / ASM</div>
                <div>( ..................................... )</div>
            </td>
            <td>
                <div>Disetujui Oleh,</div>
                <div style="font-weight: bold; margin-bottom: 30px;">RGM / Management</div>
                <div>( ..................................... )</div>
            </td>
            <td>
                <div>Diketahui & Dicairkan,</div>
                <div style="font-weight: bold; margin-bottom: 30px;">Finance & Kasir</div>
                <div>( ..................................... )</div>
            </td>
        </tr>
    </table>

</body>
</html>
