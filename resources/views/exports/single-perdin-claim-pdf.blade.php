<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Formulir Perjalanan Dinas - {{ $claim->_uid ?: ($employee->name ?? 'Pemohon') }}</title>
    <style>
        @page {
            margin: 20px 25px 25px 25px;
            size: A4 portrait;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 8.5px;
            color: #1e293b;
            line-height: 1.35;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-bottom: 2px double #0f172a;
            padding-bottom: 6px;
            margin-bottom: 12px;
        }
        .company-title {
            font-size: 13.5px;
            font-weight: bold;
            color: #0f172a;
            letter-spacing: 0.5px;
        }
        .company-subtitle {
            font-size: 8px;
            color: #475569;
        }
        .voucher-title {
            font-size: 12.5px;
            font-weight: bold;
            text-align: center;
            color: #0369a1;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 4px 0 2px 0;
        }
        .voucher-subtitle {
            text-align: center;
            font-size: 8.5px;
            color: #64748b;
            margin-bottom: 12px;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            margin-bottom: 12px;
        }
        .info-table td {
            padding: 5px 9px;
            vertical-align: top;
            font-size: 8.5px;
        }

        table.data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5px;
            margin-bottom: 14px;
        }
        table.data-table th, table.data-table td {
            border: 0.5px solid #94a3b8;
            padding: 5.5px 8px;
        }
        table.data-table th {
            background-color: #0369a1;
            color: #ffffff;
            font-weight: bold;
            text-align: center;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 7.5px;
            font-weight: bold;
        }
        .badge-success { background-color: #dcfce7; color: #166534; }
        .badge-info { background-color: #e0f2fe; color: #0369a1; }

        .signatures-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            page-break-inside: avoid;
        }
        .signatures-table td {
            width: 33.33%;
            text-align: center;
            vertical-align: top;
            padding: 4px 8px;
        }
        .sig-box {
            border: 1px solid #cbd5e1;
            background-color: #ffffff;
            border-radius: 4px;
            padding: 8px 6px;
        }
        .sig-title {
            font-weight: bold;
            font-size: 8.5px;
            color: #1e293b;
            border-bottom: 0.5px solid #e2e8f0;
            padding-bottom: 4px;
            margin-bottom: 6px;
        }
        .sig-img {
            height: 50px;
            display: block;
            margin: 0 auto;
        }
        .sig-placeholder {
            height: 50px;
            line-height: 50px;
            font-size: 8px;
            color: #94a3b8;
            font-style: italic;
        }
        .sig-stamp {
            border: 1px dashed #0284c7;
            background-color: #f0f9ff;
            color: #0369a1;
            border-radius: 4px;
            padding: 4px;
            margin: 4px auto;
            font-size: 7px;
            font-weight: bold;
            line-height: 1.2;
            width: 85%;
        }
        .sig-stamp-approved {
            border: 1px dashed #16a34a;
            background-color: #f0fdf4;
            color: #15803d;
            border-radius: 4px;
            padding: 4px;
            margin: 4px auto;
            font-size: 7px;
            font-weight: bold;
            line-height: 1.2;
            width: 85%;
        }
        .sig-name {
            font-weight: bold;
            font-size: 8.5px;
            color: #0f172a;
            border-top: 0.5px dotted #64748b;
            padding-top: 4px;
            text-transform: uppercase;
        }
        .sig-role {
            font-size: 7.5px;
            color: #64748b;
        }

        .notice-box {
            background-color: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 4px;
            padding: 8px 12px;
            font-size: 8px;
            color: #1e40af;
            margin-top: 15px;
        }
    </style>
</head>
<body>

    <!-- Header Section -->
    <table class="header-table">
        <tr>
            <td style="width: 70px; vertical-align: middle;">
                @if(!empty($logoBase64))
                    <img src="{{ $logoBase64 }}" alt="Logo" style="max-width: 60px; max-height: 40px;"/>
                @endif
            </td>
            <td style="vertical-align: middle;">
                <div class="company-title">{{ strtoupper($claim->badan_usaha ?: 'PT MEDIA SELULAR INDONESIA') }}</div>
                <div class="company-subtitle">Surat Perintah & Formulir Pengajuan Perjalanan Dinas (SPT / Perdin)</div>
            </td>
            <td style="text-align: right; vertical-align: middle;">
                <div style="font-size: 10.5px; font-weight: bold; color: #0369a1;">_UID: {{ $claim->_uid ?: 'DRAFT' }}</div>
                <div style="font-size: 8px; color: #64748b;">Tanggal Cetak: {{ date('d/m/Y H:i') }}</div>
            </td>
        </tr>
    </table>

    <div class="voucher-title">FORM PENGAJUAN PERJALANAN DINAS</div>
    <div class="voucher-subtitle">Nomor: {{ $claim->_uid ?: ('PERDIN-' . date('Ymd')) }} | Badan Usaha: {{ $claim->badan_usaha ?: 'PT MEDIA SELULAR INDONESIA' }}</div>

    <!-- Informasi Pemohon & Jadwal Dinas -->
    <table class="info-table">
        <tr>
            <td style="width: 18%;"><strong>Nama Pemohon</strong></td>
            <td style="width: 32%;">: {{ $employee->name ?? ($claim->user->name ?? '-') }}</td>
            <td style="width: 18%;"><strong>Kota Tujuan</strong></td>
            <td style="width: 32%;">: <strong>{{ strtoupper($claim->destination_city ?: 'KOTA TUJUAN') }}</strong></td>
        </tr>
        <tr>
            <td><strong>Jabatan / Role</strong></td>
            <td>: {{ $employee->position_name ?? ($employee->role->name ?? 'Sales') }}</td>
            <td><strong>Jarak Tempuh</strong></td>
            <td>: {{ $claim->distance_km ?: 80 }} KM</td>
        </tr>
        <tr>
            <td><strong>Tanggal Berangkat</strong></td>
            <td>: {{ $claim->claim_date ? $claim->claim_date->format('d/m/Y') : '-' }}</td>
            <td><strong>Tanggal Selesai</strong></td>
            <td>: {{ $claim->perdin_return_date ? $claim->perdin_return_date->format('d/m/Y') : '-' }} ({{ $claim->days_count }} Hari Dinas)</td>
        </tr>
        <tr>
            <td><strong>Rekening Pencairan</strong></td>
            <td>: <strong>{{ $claim->getEffectiveBankName() }} - {{ $claim->getEffectiveBankAccountNumber() }}</strong> a/n {{ $claim->getEffectiveBankAccountName() }}</td>
            <td><strong>Keperluan / Agenda</strong></td>
            <td>: {{ $claim->purpose ?: 'TUGAS OPERASIONAL LAPANGAN / DEALER VISIT' }}</td>
        </tr>
    </table>

    <!-- Rincian Estimasi Biaya Perdin -->
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th style="width: 35%;">Komponen Biaya Perjalanan Dinas</th>
                <th style="width: 35%;">Ketentuan / Rincian Perhitungan</th>
                <th style="width: 25%;">Nominal Estimasi (Rp)</th>
            </tr>
        </thead>
        <tbody>
            @php $no = 1; @endphp
            <tr>
                <td class="text-center">{{ $no++ }}</td>
                <td><strong>Tunjangan Uang Makan</strong></td>
                <td>{{ $claim->days_count }} Hari x Rp {{ number_format(((float)$claim->meal_allowance) / max(1, (int)$claim->days_count), 0, ',', '.') }} / hari (Terkunci Sesuai Role)</td>
                <td class="text-right">Rp {{ number_format((float)$claim->meal_allowance, 0, ',', '.') }}</td>
            </tr>
            @if((float)$claim->fuel_cost > 0)
            <tr>
                <td class="text-center">{{ $no++ }}</td>
                <td><strong>Transportasi (BBM)</strong></td>
                <td>Jatah transportasi bensin / BBM operasional dinas</td>
                <td class="text-right">Rp {{ number_format((float)$claim->fuel_cost, 0, ',', '.') }}</td>
            </tr>
            @endif
            @if((float)$claim->travel_cost > 0)
            <tr>
                <td class="text-center">{{ $no++ }}</td>
                <td><strong>Transportasi (Travel)</strong></td>
                <td>Biaya armada travel / antar kota</td>
                <td class="text-right">Rp {{ number_format((float)$claim->travel_cost, 0, ',', '.') }}</td>
            </tr>
            @endif
            @if((float)$claim->car_rental_cost > 0)
            <tr>
                <td class="text-center">{{ $no++ }}</td>
                <td><strong>Biaya Sewa Motor</strong></td>
                <td>Biaya sewa sepeda motor operasional selama dinas</td>
                <td class="text-right">Rp {{ number_format((float)$claim->car_rental_cost, 0, ',', '.') }}</td>
            </tr>
            @endif
            @if((float)($claim->other_transport_cost ?: $claim->toll_cost) > 0)
            <tr>
                <td class="text-center">{{ $no++ }}</td>
                <td><strong>Transportasi (Lainnya / Tol)</strong></td>
                <td>Biaya tol / karcis parkir / penyeberangan / lainnya</td>
                <td class="text-right">Rp {{ number_format((float)($claim->other_transport_cost ?: $claim->toll_cost), 0, ',', '.') }}</td>
            </tr>
            @endif
            <tr style="background-color: #f0fdf4; font-weight: bold;">
                <td colspan="3" class="text-right" style="font-size: 9.5px;">TOTAL PERMINTAAN DANA PERJALANAN DINAS:</td>
                <td class="text-right" style="color: #0369a1; font-size: 11px;">
                    Rp {{ number_format((float)$claim->amount, 0, ',', '.') }}
                </td>
            </tr>
        </tbody>
    </table>

    <!-- Ketentuan Pertanggungjawaban -->
    <div class="notice-box">
        <strong>Ketentuan Pertanggungjawaban Nota Balik:</strong>
        Maksimal H+1 setelah kepulangan, penerima dana wajib menyerahkan seluruh bukti struk riil pengeluaran fisik (BBM SPBU, Tiket/Tol) melalui sistem Nota Balik. Kwitansi uang makan dibuat otomatis oleh sistem. Apabila terdapat sisa dana uang muka yang tidak terpakai, sisa wajib ditransfer kembali ke kas Finance.
    </div>

    <!-- TANDA TANGAN (Item 6): Pemohon, Atasan Langsung (RGM / Pak Jejen), dan Finance -->
    <table class="signatures-table">
        <tr>
            <!-- 1. Yang Mengajukan (Pemohon: Sales / ASM / RGM) -->
            <td>
                <div class="sig-box">
                    <div class="sig-title">Yang Mengajukan (Pemohon)</div>
                    @if(!empty($signatureApplicantBase64))
                        <img src="{{ $signatureApplicantBase64 }}" class="sig-img" alt="TTD Pemohon"/>
                    @else
                        <div class="sig-stamp">
                            <div style="font-size: 7.5px; font-weight: bold; color: #0284c7;">✓ TTD DIGITAL SISTEM</div>
                            <div style="font-size: 6.5px; color: #64748b;">Diajukan: {{ $claim->claim_date ? $claim->claim_date->format('d/m/Y') : date('d/m/Y') }}</div>
                            <div style="font-size: 7px; color: #0f172a; font-weight: bold;">{{ $employee->name ?? ($claim->user->name ?? 'Pemohon') }}</div>
                        </div>
                    @endif
                    <div class="sig-name">{{ $employee->name ?? ($claim->user->name ?? 'Pemohon') }}</div>
                    <div class="sig-role">{{ $employee->position_name ?? ($employee->role->name ?? 'Pemohon Dinas') }}</div>
                </div>
            </td>

            <!-- 2. Disetujui Atasan: Sales/ASM -> RGM; RGM -> Head of Sales (Pak Jejen) -->
            <td>
                <div class="sig-box">
                    <div class="sig-title">{{ $atasanTitle ?? 'Disetujui Oleh (Atasan Langsung)' }}</div>
                    @if(!empty($signatureSupervisorBase64))
                        <img src="{{ $signatureSupervisorBase64 }}" class="sig-img" alt="TTD Atasan"/>
                    @elseif($isApprovedByAtasan || in_array($claim->approval_status, ['ACC_ASM', 'ACC_RGM', 'ACC_ADMIN', 'ACC_PAK_JEJEN', 'DISETUJUI']))
                        <div class="sig-stamp-approved">
                            <div style="font-size: 7.5px; font-weight: bold; color: #16a34a;">✓ TERVERIFIKASI & DISETUJUI</div>
                            <div style="font-size: 6.5px; color: #64748b;">Status: {{ $claim->approval_status }}</div>
                            <div style="font-size: 7px; color: #0f172a; font-weight: bold;">{{ $atasanName ?? 'Atasan Langsung' }}</div>
                        </div>
                    @else
                        <div class="sig-placeholder">[ Menunggu Persetujuan ]</div>
                    @endif
                    <div class="sig-name">{{ $atasanName ?? 'Atasan Langsung' }}</div>
                    <div class="sig-role">{{ $atasanPosition ?? 'RGM / Head of Sales' }}</div>
                </div>
            </td>

            <!-- 3. Mengetahui: Finance & Accounting -->
            <td>
                <div class="sig-box">
                    <div class="sig-title">Mengetahui / Pencairan</div>
                    @if($claim->disbursement_status === 'Sudah Dicairkan')
                        <div class="sig-stamp-approved">
                            <div style="font-size: 7.5px; font-weight: bold; color: #16a34a;">✓ DANA DICAIRKAN</div>
                            <div style="font-size: 6.5px; color: #64748b;">Tgl: {{ $claim->disbursed_at ? $claim->disbursed_at->format('d/m/Y H:i') : date('d/m/Y') }}</div>
                            <div style="font-size: 7px; color: #0f172a; font-weight: bold;">Finance & Accounting</div>
                        </div>
                    @elseif($claim->approval_status === 'DISETUJUI')
                        <div class="sig-stamp">
                            <div style="font-size: 7.5px; font-weight: bold; color: #0284c7;">SIAP DICAIRKAN</div>
                            <div style="font-size: 6.5px; color: #64748b;">Verifikasi Lengkap</div>
                            <div style="font-size: 7px; color: #0f172a; font-weight: bold;">Finance & Accounting</div>
                        </div>
                    @else
                        <div class="sig-placeholder">[ Antrean Approval ]</div>
                    @endif
                    <div class="sig-name">{{ $financeName ?? 'Tim Finance & Accounting' }}</div>
                    <div class="sig-role">{{ $claim->badan_usaha ?: 'PT Media Selular Indonesia' }}</div>
                </div>
            </td>
        </tr>
    </table>

</body>
</html>
