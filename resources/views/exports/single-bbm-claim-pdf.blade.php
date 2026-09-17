<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Formulir Klaim BBM - {{ $claim->_uid ?: ($employee->name ?? 'MSI') }}</title>
    <style>
        @page {
            margin: 20px 25px 25px 25px;
            size: A4 portrait;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 8.5px;
            color: #1e293b;
            line-height: 1.3;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-bottom: 2px double #0f172a;
            padding-bottom: 6px;
            margin-bottom: 10px;
        }
        .company-title {
            font-size: 13px;
            font-weight: bold;
            color: #0f172a;
            letter-spacing: 0.5px;
        }
        .company-subtitle {
            font-size: 8px;
            color: #475569;
        }
        .voucher-title {
            font-size: 11.5px;
            font-weight: bold;
            text-align: center;
            color: #0f766e;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 4px 0 2px 0;
        }
        .voucher-subtitle {
            text-align: center;
            font-size: 8.5px;
            color: #64748b;
            margin-bottom: 10px;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        .info-table td {
            padding: 4px 8px;
            vertical-align: top;
            font-size: 8.5px;
        }

        table.data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8px;
            margin-bottom: 10px;
        }
        table.data-table th, table.data-table td {
            border: 0.5px solid #94a3b8;
            padding: 4.5px 6px;
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
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 7.5px;
            font-weight: bold;
        }
        .badge-success { background-color: #dcfce7; color: #166534; }
        .badge-warning { background-color: #fef3c7; color: #92400e; }
        .badge-danger { background-color: #fee2e2; color: #991b1b; }
        .badge-info { background-color: #e0f2fe; color: #075985; }
        .badge-gray { background-color: #f1f5f9; color: #475569; }


        .photo-card {
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 6px;
            text-align: center;
            background: #ffffff;
            margin-bottom: 12px;
            page-break-inside: avoid;
        }
        .photo-img {
            max-width: 100%;
            max-height: 300px;
            object-fit: contain;
            border: 0.5px solid #e2e8f0;
            border-radius: 2px;
        }
    </style>
</head>
<body>

    <!-- KOP PERUSAHAAN -->
    <table class="header-table">
        <tr>
            <td style="width: 15%; vertical-align: middle;">
                @if(!empty($logoBase64))
                    <img src="{{ $logoBase64 }}" style="max-height: 42px; max-width: 90px;" alt="Logo MSI">
                @else
                    <div style="font-weight: bold; font-size: 15px; color: #0f766e;">MSI</div>
                @endif
            </td>
            <td style="width: 55%; vertical-align: middle;">
                <div class="company-title">PT. MEDIA SELULAR INDONESIA</div>
                <div class="company-subtitle">PUSAT GROSIR & DISTRIBUSI ACCESSORIES HANDPHONE & GADGET</div>
                <div class="company-subtitle">Jl. Tuparev No. 109F Kertawinangun, Kedawung - Cirebon 45153</div>
            </td>
            <td style="width: 30%; text-align: right; vertical-align: middle;">
                <div style="font-size: 9.5px; font-weight: bold; color: #0f766e;">FORMULIR KLAIM BBM</div>
                <div style="font-size: 8px; color: #475569;">No. UID: <strong style="font-family: monospace; font-size: 9px; color: #0f172a;">{{ $claim->_uid ?: 'DRAFT' }}</strong></div>
                <div style="font-size: 7.5px; color: #64748b;">Dicetak: {{ date('d/m/Y H:i') }} WIB</div>
            </td>
        </tr>
    </table>

    <div class="voucher-title">FORMULIR PENGAJUAN & REALISASI KLAIM BAHAN BAKAR MINYAK (BBM)</div>
    <div class="voucher-subtitle">
        Tanggal Pengajuan: {{ $claim->claim_date ? $claim->claim_date->format('d F Y') : '-' }} | 
        Wilayah / Homebase: {{ $claim->region ?: ($employee->region ?? ($employee->homebase ?? '-')) }}
    </div>

    <!-- INFORMASI PEMOHON & KENDARAAN -->
    <table class="info-table">
        <tr>
            <td style="width: 50%;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="width: 120px; font-weight: bold; color: #475569; padding: 2px 0;">Nama Pemohon</td>
                        <td style="padding: 2px 0;">: <strong>{{ $employee->name ?? '-' }}</strong></td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; color: #475569; padding: 2px 0;">Jabatan / Role</td>
                        <td style="padding: 2px 0;">: {{ $employee->position_name ?? ($employee->role->name ?? 'Sales / Operasional') }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; color: #475569; padding: 2px 0;">Region / Cabang</td>
                        <td style="padding: 2px 0;">: {{ $claim->region ?: ($employee->region ?? '-') }} / {{ $claim->branch?->name ?: ($claim->city ?: '-') }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; color: #475569; padding: 2px 0;">Jenis Kendaraan</td>
                        <td style="padding: 2px 0;">: <strong>{{ $claim->vehicle_type ?: 'Mobil' }}</strong> @if($claim->vehicle_plate) ({{ $claim->vehicle_plate }}) @endif</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; color: #475569; padding: 2px 0;">Jenis Bahan Bakar</td>
                        <td style="padding: 2px 0;">: 
                            <strong>{{ $claim->fuel_type ?: 'Pertalite' }}</strong>
                            @php $comp = $claim->fuel_compliance; @endphp
                            <span class="badge {{ $comp['color'] === 'success' ? 'badge-success' : ($comp['color'] === 'danger' ? 'badge-danger' : 'badge-warning') }}">
                                {{ $comp['label'] }}
                            </span>
                        </td>
                    </tr>
                </table>
            </td>
            <td style="width: 50%;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="width: 120px; font-weight: bold; color: #475569; padding: 2px 0;">KM Odometer</td>
                        <td style="padding: 2px 0;">: {{ $claim->fuel_start_km ? number_format($claim->fuel_start_km, 0, ',', '.') . ' KM' : '-' }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; color: #475569; padding: 2px 0;">Volume Pengisian</td>
                        <td style="padding: 2px 0;">: {{ $claim->fuel_liters ? number_format($claim->fuel_liters, 2, ',', '.') . ' Liter' : '-' }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; color: #475569; padding: 2px 0;">Biaya Dasar BBM</td>
                        <td style="padding: 2px 0;">: Rp {{ number_format((float)($claim->fuel_base_amount ?: $claim->amount), 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; color: #475569; padding: 2px 0;">Nominal Lebih Mobil</td>
                        <td style="padding: 2px 0;">: Rp {{ number_format((float)($claim->fuel_extra_amount ?? 0), 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; color: #475569; padding: 2px 0;">Status Approval</td>
                        <td style="padding: 2px 0;">: 
                            <span class="badge {{ in_array($claim->approval_status, ['DISETUJUI', 'ACC_PAK_JEJEN']) ? 'badge-success' : ($claim->approval_status === 'DITOLAK' ? 'badge-danger' : 'badge-warning') }}">
                                {{ $claim->approval_status ?? 'DRAFT' }}
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; color: #475569; padding: 2px 0;">Status Pencairan</td>
                        <td style="padding: 2px 0;">: 
                            <span class="badge {{ $claim->disbursement_status === 'Sudah Dicairkan' ? 'badge-success' : 'badge-danger' }}">
                                {{ $claim->disbursement_status ?? 'Belum Dicairkan' }}
                            </span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>



    <!-- TABEL RINCIAN TRANSAKSI BBM -->
    <div style="font-weight: bold; font-size: 9px; margin-bottom: 4px; color: #1e293b;">
        RINCIAN TRANSAKSI PENGISIAN BBM :
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 25px;">NO</th>
                <th style="width: 70px;">TANGGAL</th>
                <th style="text-align: left;">LOKASI SPBU / KETERANGAN</th>
                <th style="width: 70px;">KM ODO</th>
                <th style="width: 65px;">LITER</th>
                <th style="width: 85px;">BIAYA DASAR (Rp)</th>
                <th style="width: 75px;">LEBIH (Rp)</th>
                <th style="width: 95px;">TOTAL (Rp)</th>
            </tr>
        </thead>
        <tbody>
            @if(is_array($claim->items) && count($claim->items) > 0)
                @php $totalBase = 0; $totalExtra = 0; $grandTotal = 0; @endphp
                @foreach($claim->items as $idx => $it)
                    @php 
                        $amt = (float)($it['amount'] ?? 0);
                        $base = (float)($it['fuel_base_amount'] ?? $amt);
                        $extra = (float)($it['fuel_extra_amount'] ?? 0);
                        $totalBase += $base;
                        $totalExtra += $extra;
                        $grandTotal += $amt;
                    @endphp
                    <tr class="{{ $idx % 2 === 1 ? 'even' : '' }}">
                        <td class="text-center">{{ $idx + 1 }}</td>
                        <td class="text-center">{{ !empty($it['receipt_date']) ? date('d/m/Y', strtotime($it['receipt_date'])) : ($claim->claim_date ? $claim->claim_date->format('d/m/Y') : '-') }}</td>
                        <td class="text-left">
                            <strong>{{ $it['note'] ?? ($claim->note ?: 'Pengisian SPBU') }}</strong>
                            @if(!empty($it['city'])) <span style="color: #64748b;">({{ $it['city'] }})</span> @endif
                        </td>
                        <td class="text-center">{{ !empty($it['fuel_start_km']) ? number_format($it['fuel_start_km'], 0, ',', '.') : '-' }}</td>
                        <td class="text-center">{{ !empty($it['fuel_liters']) ? number_format($it['fuel_liters'], 2, ',', '.') : '-' }}</td>
                        <td class="text-right">{{ number_format($base, 0, ',', '.') }}</td>
                        <td class="text-right">{{ number_format($extra, 0, ',', '.') }}</td>
                        <td class="text-right" style="font-weight: bold;">{{ number_format($amt, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="5" class="text-right" style="padding-right: 8px;">TOTAL KLAIM BBM :</td>
                    <td class="text-right">{{ number_format($totalBase, 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($totalExtra, 0, ',', '.') }}</td>
                    <td class="text-right" style="color: #0f766e; font-size: 9px;">
                        Rp {{ number_format($grandTotal, 0, ',', '.') }}
                    </td>
                </tr>
            @else
                <tr>
                    <td class="text-center">1</td>
                    <td class="text-center">{{ $claim->claim_date ? $claim->claim_date->format('d/m/Y') : '-' }}</td>
                    <td class="text-left">
                        <strong>{{ $claim->note ?: 'Pengisian SPBU' }}</strong>
                        @if($claim->city) <span style="color: #64748b;">({{ $claim->city }})</span> @endif
                    </td>
                    <td class="text-center">{{ $claim->fuel_start_km ? number_format($claim->fuel_start_km, 0, ',', '.') : '-' }}</td>
                    <td class="text-center">{{ $claim->fuel_liters ? number_format($claim->fuel_liters, 2, ',', '.') : '-' }}</td>
                    <td class="text-right">{{ number_format((float)($claim->fuel_base_amount ?: $claim->amount), 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format((float)($claim->fuel_extra_amount ?? 0), 0, ',', '.') }}</td>
                    <td class="text-right" style="font-weight: bold;">{{ number_format((float)$claim->amount, 0, ',', '.') }}</td>
                </tr>
                <tr class="total-row">
                    <td colspan="5" class="text-right" style="padding-right: 8px;">TOTAL KLAIM BBM :</td>
                    <td class="text-right">{{ number_format((float)($claim->fuel_base_amount ?: $claim->amount), 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format((float)($claim->fuel_extra_amount ?? 0), 0, ',', '.') }}</td>
                    <td class="text-right" style="color: #0f766e; font-size: 9px;">
                        Rp {{ number_format((float)$claim->amount, 0, ',', '.') }}
                    </td>
                </tr>
            @endif
        </tbody>
    </table>



    <!-- LAMPIRAN FOTO BBM (GABUNGAN BEFORE ODOMETER & AFTER NOTA SPBU) -->
    @if(!empty($pairs) && count($pairs) > 0)
        <div style="page-break-before: always;"></div>
        <table class="header-table">
            <tr>
                <td style="width: 70%;">
                    <div class="company-title">PT. MEDIA SELULAR INDONESIA</div>
                    <div style="font-size: 8px; color: #475569;">Lampiran Bukti Foto BBM - No. UID: {{ $claim->_uid ?: 'DRAFT' }}</div>
                </td>
                <td style="width: 30%; text-align: right;">
                    <div style="font-size: 8.5px; font-weight: bold; color: #0f766e;">BUKTI FOTO BBM</div>
                    <div style="font-size: 7.5px; color: #64748b;">Pemohon: {{ $employee->name ?? '-' }}</div>
                </td>
            </tr>
        </table>

        <div style="font-weight: bold; font-size: 10px; color: #1e293b; margin-bottom: 8px;">
            DOKUMENTASI FOTO GABUNGAN BBM (ODOMETER & STRUK SPBU) :
        </div>

        @foreach($pairs as $p)
            <div class="photo-card">
                <div style="font-size: 8.5px; font-weight: bold; color: #0f172a; margin-bottom: 4px;">
                    {{ $p['label'] }} | SPBU: {{ $p['spbu'] }} | Tanggal: {{ $p['date'] }} | Nominal: Rp {{ number_format($p['amount'], 0, ',', '.') }}
                </div>

                @if(!empty($p['combined_b64']))
                    <img src="{{ $p['combined_b64'] }}" class="photo-img" style="max-height: 380px;" alt="Foto Gabungan BBM">
                @else
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <td style="width: 50%; padding: 4px; text-align: center;">
                                <div style="font-size: 7.5px; font-weight: bold; color: #475569; margin-bottom: 2px;">BEFORE: FOTO KM ODOMETER</div>
                                @if(!empty($p['before_b64']))
                                    <img src="{{ $p['before_b64'] }}" class="photo-img" style="max-height: 250px;" alt="Foto Odometer">
                                @else
                                    <div style="padding: 20px; color: #94a3b8; font-style: italic;">(Tidak ada foto Odometer)</div>
                                @endif
                            </td>
                            <td style="width: 50%; padding: 4px; text-align: center;">
                                <div style="font-size: 7.5px; font-weight: bold; color: #475569; margin-bottom: 2px;">AFTER: FOTO STRUK / NOTA SPBU</div>
                                @if(!empty($p['after_b64']))
                                    <img src="{{ $p['after_b64'] }}" class="photo-img" style="max-height: 250px;" alt="Foto Struk SPBU">
                                @else
                                    <div style="padding: 20px; color: #94a3b8; font-style: italic;">(Tidak ada foto Struk SPBU)</div>
                                @endif
                            </td>
                        </tr>
                    </table>
                @endif
            </div>
        @endforeach
    @endif

    <!-- FOTO LAINNYA JIKA ADA -->
    @if(!empty($generalPhotosBase64) && count($generalPhotosBase64) > 0)
        <div style="font-weight: bold; font-size: 9.5px; color: #1e293b; margin-top: 15px; margin-bottom: 8px;">
            FOTO BUKTI PENDUKUNG LAINNYA ({{ count($generalPhotosBase64) }} Foto) :
        </div>
        <table style="width: 100%; border-collapse: collapse;">
            @foreach(array_chunk($generalPhotosBase64, 2) as $chunk)
                <tr>
                    @foreach($chunk as $idx => $photo)
                        <td style="width: 50%; padding: 6px; vertical-align: top;">
                            <div class="photo-card">
                                <img src="{{ $photo }}" class="photo-img" style="max-height: 250px;" alt="Foto Pendukung">
                            </div>
                        </td>
                    @endforeach
                    @if(count($chunk) === 1)
                        <td style="width: 50%; padding: 6px;"></td>
                    @endif
                </tr>
            @endforeach
        </table>
    @endif

</body>
</html>
