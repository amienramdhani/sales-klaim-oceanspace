<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekapan Budget Karyawan - {{ $employee->name }}</title>
    <style>
        @page {
            margin: 25px 25px 25px 25px;
            size: A4 portrait;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 9px;
            color: #1e293b;
            line-height: 1.3;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-bottom: 2px double #1e293b;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }
        .company-title {
            font-size: 13px;
            font-weight: bold;
            color: #0f172a;
        }
        .report-title {
            font-size: 12px;
            font-weight: bold;
            text-align: center;
            color: #0f766e;
            text-transform: uppercase;
            margin: 6px 0 2px 0;
        }
        .info-box {
            width: 100%;
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 8px 12px;
            margin-bottom: 12px;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5px;
            margin-bottom: 12px;
        }
        table.data-table th, table.data-table td {
            border: 0.5px solid #94a3b8;
            padding: 4px 6px;
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
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .badge {
            display: inline-block;
            padding: 2px 5px;
            border-radius: 3px;
            font-size: 7.5px;
            font-weight: bold;
        }
        .badge-success { background-color: #dcfce7; color: #166534; }
        .badge-danger { background-color: #fee2e2; color: #991b1b; }
        .sig-table {
            width: 100%;
            margin-top: 20px;
            font-size: 8.5px;
        }
        .sig-table td {
            text-align: center;
            vertical-align: top;
            width: 33.3%;
        }
    </style>
</head>
<body>

    <!-- KOP PERUSAHAAN -->
    <table class="header-table">
        <tr>
            <td style="width: 70%;">
                <div class="company-title">PT. MEDIA SELULAR INDONESIA</div>
                <div style="font-size: 8.5px; color: #475569;">Jl. Tuparev No. 109F Kertawinangun, Kedawung - Cirebon 45153</div>
            </td>
            <td style="width: 30%; text-align: right;">
                <div style="font-size: 9px; font-weight: bold; color: #0f766e;">KARTU KONTROL BUDGET</div>
                <div style="font-size: 8px; color: #64748b;">Dicetak: {{ date('d/m/Y H:i') }} WIB</div>
            </td>
        </tr>
    </table>

    <div class="report-title">REKAPAN BUDGET & REALISASI KLAIM PER USER</div>
    <div style="text-align: center; font-size: 9px; color: #64748b; margin-bottom: 10px;">
        Periode: {{ $periodLabel ?? 'Semua Periode' }}
    </div>

    <!-- PROFIL KARYAWAN -->
    <table class="info-box">
        <tr>
            <td style="width: 50%;">
                <div><strong>Nama Karyawan :</strong> {{ $employee->name }}</div>
                <div><strong>Jabatan / Role :</strong> {{ $employee->position_name ?? 'ASM' }}</div>
                <div><strong>Homebase / Area :</strong> {{ $employee->homebase ?? 'Purwokerto' }}</div>
            </td>
            <td style="width: 50%;">
                <div><strong>Total Plafon Budget :</strong> Rp {{ number_format($employee->total_budget, 0, ',', '.') }}</div>
                <div><strong>Total Terpakai :</strong> Rp {{ number_format($totalUsed, 0, ',', '.') }}</div>
                <div><strong>Sisa Saldo Bersih :</strong> 
                    <span style="font-weight: bold; color: {{ ($employee->total_budget - $totalUsed) >= 0 ? '#166534' : '#dc2626' }};">
                        Rp {{ number_format($employee->total_budget - $totalUsed, 0, ',', '.') }}
                    </span>
                </div>
            </td>
        </tr>
    </table>

    <!-- TABEL REKAPAN PER KATEGORI BUDGET -->
    <div style="font-weight: bold; font-size: 9.5px; margin-bottom: 4px; color: #1e293b;">1. RINGKASAN PEMAKAIAN PER KATEGORI BUDGET :</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 30px;">NO</th>
                <th style="text-align: left;">KATEGORI BIAYA / BUDGET</th>
                <th style="width: 90px;">PLAFON (Rp)</th>
                <th style="width: 90px;">REALISASI (Rp)</th>
                <th style="width: 90px;">SISA SALDO (Rp)</th>
                <th style="width: 80px;">STATUS</th>
            </tr>
        </thead>
        <tbody>
            @foreach($categoryBreakdown as $idx => $cat)
                <tr class="{{ $idx % 2 === 1 ? 'even' : '' }}">
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td class="text-left" style="font-weight: bold;">{{ $cat['name'] }}</td>
                    <td class="text-right">{{ number_format($cat['budget'], 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($cat['used'], 0, ',', '.') }}</td>
                    <td class="text-right" style="font-weight: bold; color: {{ $cat['remaining'] >= 0 ? '#166534' : '#dc2626' }};">
                        {{ number_format($cat['remaining'], 0, ',', '.') }}
                    </td>
                    <td class="text-center">
                        @if($cat['budget'] > 0 && $cat['used'] > $cat['budget'])
                            <span class="badge badge-danger">⚠️ Over Budget</span>
                        @elseif($cat['budget'] > 0)
                            <span class="badge badge-success">✅ Sesuai Plafon</span>
                        @else
                            <span class="badge badge-gray">-</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="2" class="text-right">TOTAL KESELURUHAN :</td>
                <td class="text-right">{{ number_format($employee->total_budget, 0, ',', '.') }}</td>
                <td class="text-right" style="color: #0f766e;">{{ number_format($totalUsed, 0, ',', '.') }}</td>
                <td class="text-right" style="color: {{ ($employee->total_budget - $totalUsed) >= 0 ? '#166534' : '#dc2626' }};">
                    {{ number_format($employee->total_budget - $totalUsed, 0, ',', '.') }}
                </td>
                <td class="text-center">
                    @if($totalUsed > $employee->total_budget && $employee->total_budget > 0)
                        <span class="badge badge-danger">OVER</span>
                    @else
                        <span class="badge badge-success">AMAN</span>
                    @endif
                </td>
            </tr>
        </tbody>
    </table>

    <!-- TABEL DAFTAR TRANSAKSI KLAIM -->
    <div style="font-weight: bold; font-size: 9.5px; margin-bottom: 4px; color: #1e293b; margin-top: 10px;">2. RINCIAN TRANSAKSI PENGAJUAN KLAIM :</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 25px;">NO</th>
                <th style="width: 65px;">TANGGAL</th>
                <th style="width: 75px;">_UID</th>
                <th style="text-align: left;">KATEGORI / JENIS KLAIM</th>
                <th style="width: 80px;">NOMINAL (Rp)</th>
                <th style="width: 70px;">APPROVAL</th>
                <th style="width: 75px;">PENCAIRAN</th>
            </tr>
        </thead>
        <tbody>
            @forelse($claims as $idx => $c)
                <tr class="{{ $idx % 2 === 1 ? 'even' : '' }}">
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td class="text-center">{{ $c->claim_date ? $c->claim_date->format('d/m/Y') : '-' }}</td>
                    <td class="text-center" style="font-family: monospace; font-weight: bold;">{{ $c->_uid ?: '-' }}</td>
                    <td class="text-left">
                        {{ strtoupper($c->claim_category ?: ($c->claim_type ?: 'Operasional')) }}
                        @if($c->city) - {{ $c->city }} @endif
                    </td>
                    <td class="text-right" style="font-weight: bold;">{{ number_format((float)$c->amount, 0, ',', '.') }}</td>
                    <td class="text-center">{{ $c->approval_status ?? 'DRAFT' }}</td>
                    <td class="text-center">{{ $c->disbursement_status ?? 'Belum Dicairkan' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center" style="color: #94a3b8; padding: 12px;">Belum ada riwayat transaksi pengajuan klaim untuk periode ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- TANDA TANGAN -->
    <table class="sig-table">
        <tr>
            <td>
                <div>Pemohon / Karyawan,</div>
                <div style="height: 35px;"></div>
                <div style="font-weight: bold;">({{ $employee->name }})</div>
            </td>
            <td>
                <div>Diperiksa & Disetujui,</div>
                <div style="height: 35px;"></div>
                <div>( ..................................... )</div>
            </td>
            <td>
                <div>Finance & Accounting,</div>
                <div style="height: 35px;"></div>
                <div>( ..................................... )</div>
            </td>
        </tr>
    </table>

</body>
</html>
