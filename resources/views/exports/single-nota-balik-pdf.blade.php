<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Nota Balik Perdin - {{ $claim->_uid ?: ($employee->name ?? 'Pemohon') }}</title>
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
            font-size: 12px;
            font-weight: bold;
            text-align: center;
            color: #059669;
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
            background-color: #065f46;
            color: #ffffff;
            font-weight: bold;
            text-align: center;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }

        .recap-box {
            background-color: #f0fdf4;
            border: 1.5px solid #86efac;
            border-radius: 4px;
            padding: 10px 14px;
            margin-bottom: 14px;
        }
        .recap-grid {
            width: 100%;
            border-collapse: collapse;
        }
        .recap-grid td {
            padding: 3px 0;
            font-size: 8.5px;
        }

        .photo-section-title {
            font-size: 9.5px;
            font-weight: bold;
            color: #0f172a;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 3px;
            margin: 12px 0 8px 0;
            text-transform: uppercase;
        }
        .photo-grid-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        .photo-grid-table td {
            width: 50%;
            padding: 5px;
            vertical-align: top;
            text-align: center;
        }
        .photo-card {
            border: 1px solid #cbd5e1;
            background-color: #ffffff;
            padding: 6px;
            border-radius: 4px;
        }
        .photo-img {
            max-width: 100%;
            max-height: 180px;
            object-fit: contain;
            display: block;
            margin: 0 auto;
        }
        .photo-label {
            font-weight: bold;
            font-size: 8px;
            margin-top: 4px;
            color: #334155;
            background-color: #f1f5f9;
            padding: 2px;
            border-radius: 2px;
        }

        .signatures-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
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
            height: 48px;
            display: block;
            margin: 0 auto;
        }
        .sig-placeholder {
            height: 48px;
            line-height: 48px;
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
                <div class="company-subtitle">Laporan Pertanggungjawaban Realisasi Biaya & Nota Balik Perjalanan Dinas</div>
            </td>
            <td style="text-align: right; vertical-align: middle;">
                <div style="font-size: 10.5px; font-weight: bold; color: #059669;">_UID: {{ $claim->_uid ?: 'DRAFT' }}</div>
                <div style="font-size: 8px; color: #64748b;">Tanggal Nota Balik: {{ $claim->nota_balik_submitted_at ? $claim->nota_balik_submitted_at->format('d/m/Y H:i') : date('d/m/Y') }}</div>
            </td>
        </tr>
    </table>

    <div class="voucher-title">PERTANGGUNGJAWABAN REALISASI BIAYA (NOTA BALIK PERDIN)</div>
    <div class="voucher-subtitle">Status Nota Balik: {{ $claim->nota_balik_status ?: 'Diserahkan' }} | Badan Usaha: {{ $claim->badan_usaha ?: 'PT Media Selular Indonesia' }}</div>

    <!-- Informasi Pemohon & Jadwal -->
    <table class="info-table">
        <tr>
            <td style="width: 18%;"><strong>Nama Pemohon</strong></td>
            <td style="width: 32%;">: {{ $employee->name ?? ($claim->user->name ?? '-') }}</td>
            <td style="width: 18%;"><strong>Kota Tujuan</strong></td>
            <td style="width: 32%;">: <strong>{{ strtoupper($claim->destination_city ?: 'KOTA TUJUAN') }}</strong></td>
        </tr>
        <tr>
            <td><strong>Jabatan</strong></td>
            <td>: {{ $employee->position_name ?? ($employee->role->name ?? 'Sales') }}</td>
            <td><strong>Tanggal Dinas</strong></td>
            <td>: {{ $claim->claim_date ? $claim->claim_date->format('d/m/Y') : '-' }} s/d {{ $claim->perdin_return_date ? $claim->perdin_return_date->format('d/m/Y') : '-' }} ({{ $claim->days_count }} Hari)</td>
        </tr>
        <tr>
            <td><strong>Agenda Dinas</strong></td>
            <td colspan="3">: {{ $claim->purpose ?: 'TUGAS OPERASIONAL LAPANGAN' }}</td>
        </tr>
    </table>

    @php
        $estMeal = (float)$claim->meal_allowance;
        $actMeal = (float)($claim->actual_meal_allowance > 0 ? $claim->actual_meal_allowance : $claim->meal_allowance);
        $remMeal = max(0, $estMeal - $actMeal);

        $estFuel = (float)$claim->fuel_cost;
        $actFuel = (float)($claim->actual_fuel_cost > 0 ? $claim->actual_fuel_cost : 0);
        $remFuel = max(0, $estFuel - $actFuel);

        $estTravel = (float)$claim->travel_cost;
        $actTravel = (float)($claim->actual_travel_cost > 0 ? $claim->actual_travel_cost : 0);
        $remTravel = max(0, $estTravel - $actTravel);

        $estRental = (float)$claim->car_rental_cost;
        $actRental = (float)($claim->actual_car_rental_cost > 0 ? $claim->actual_car_rental_cost : 0);
        $remRental = max(0, $estRental - $actRental);

        $estOther = (float)($claim->other_transport_cost ?: $claim->toll_cost);
        $actOther = (float)($claim->actual_other_transport_cost > 0 ? $claim->actual_other_transport_cost : ($claim->actual_toll_cost > 0 ? $claim->actual_toll_cost : 0));
        $remOther = max(0, $estOther - $actOther);

        $totalEst = (float)$claim->amount;
        $totalAct = (float)($claim->actual_amount_total > 0 ? $claim->actual_amount_total : ($actMeal + $actFuel + $actTravel + $actRental + $actOther));
        $totalRem = (float)($claim->remaining_amount_calculated > 0 ? $claim->remaining_amount_calculated : max(0, $totalEst - $totalAct));
    @endphp

    <!-- Tabel Rincian: Diajukan vs Realisasi vs Sisa Dana (Item 15) -->
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th style="width: 25%;">Komponen Biaya</th>
                <th style="width: 20%;">Estimasi Awal (Rp)</th>
                <th style="width: 20%;">Realisasi Terpakai (Rp)</th>
                <th style="width: 20%;">Sisa Pengembalian (Rp)</th>
                <th style="width: 10%;">Bukti Fisik</th>
            </tr>
        </thead>
        <tbody>
            @php $no = 1; @endphp
            <tr>
                <td class="text-center">{{ $no++ }}</td>
                <td><strong>Tunjangan Uang Makan</strong></td>
                <td class="text-right">Rp {{ number_format($estMeal, 0, ',', '.') }}</td>
                <td class="text-right">Rp {{ number_format($actMeal, 0, ',', '.') }}</td>
                <td class="text-right font-bold" style="color: {{ $remMeal > 0 ? '#059669' : '#475569' }};">
                    Rp {{ number_format($remMeal, 0, ',', '.') }}
                </td>
                <td class="text-center" style="font-size: 7.5px;">Kwitansi Sistem</td>
            </tr>
            @if($estFuel > 0 || $actFuel > 0)
            <tr>
                <td class="text-center">{{ $no++ }}</td>
                <td><strong>Transportasi (BBM)</strong></td>
                <td class="text-right">Rp {{ number_format($estFuel, 0, ',', '.') }}</td>
                <td class="text-right">Rp {{ number_format($actFuel, 0, ',', '.') }}</td>
                <td class="text-right font-bold" style="color: {{ $remFuel > 0 ? '#059669' : '#475569' }};">
                    Rp {{ number_format($remFuel, 0, ',', '.') }}
                </td>
                <td class="text-center" style="font-size: 7.5px;">Struk SPBU</td>
            </tr>
            @endif
            @if($estTravel > 0 || $actTravel > 0)
            <tr>
                <td class="text-center">{{ $no++ }}</td>
                <td><strong>Transportasi (Travel)</strong></td>
                <td class="text-right">Rp {{ number_format($estTravel, 0, ',', '.') }}</td>
                <td class="text-right">Rp {{ number_format($actTravel, 0, ',', '.') }}</td>
                <td class="text-right font-bold" style="color: {{ $remTravel > 0 ? '#059669' : '#475569' }};">
                    Rp {{ number_format($remTravel, 0, ',', '.') }}
                </td>
                <td class="text-center" style="font-size: 7.5px;">Tiket Travel</td>
            </tr>
            @endif
            @if($estRental > 0 || $actRental > 0)
            <tr>
                <td class="text-center">{{ $no++ }}</td>
                <td><strong>Biaya Sewa Motor</strong></td>
                <td class="text-right">Rp {{ number_format($estRental, 0, ',', '.') }}</td>
                <td class="text-right">Rp {{ number_format($actRental, 0, ',', '.') }}</td>
                <td class="text-right font-bold" style="color: {{ $remRental > 0 ? '#059669' : '#475569' }};">
                    Rp {{ number_format($remRental, 0, ',', '.') }}
                </td>
                <td class="text-center" style="font-size: 7.5px;">Nota Sewa</td>
            </tr>
            @endif
            @if($estOther > 0 || $actOther > 0)
            <tr>
                <td class="text-center">{{ $no++ }}</td>
                <td><strong>Transportasi (Lainnya / Tol)</strong></td>
                <td class="text-right">Rp {{ number_format($estOther, 0, ',', '.') }}</td>
                <td class="text-right">Rp {{ number_format($actOther, 0, ',', '.') }}</td>
                <td class="text-right font-bold" style="color: {{ $remOther > 0 ? '#059669' : '#475569' }};">
                    Rp {{ number_format($remOther, 0, ',', '.') }}
                </td>
                <td class="text-center" style="font-size: 7.5px;">Karcis / Nota</td>
            </tr>
            @endif
            <tr style="background-color: #ecfdf5; font-weight: bold;">
                <td colspan="2" class="text-right">TOTAL KESELURUHAN (RP):</td>
                <td class="text-right" style="color: #0369a1;">Rp {{ number_format($totalEst, 0, ',', '.') }}</td>
                <td class="text-right" style="color: #0f172a;">Rp {{ number_format($totalAct, 0, ',', '.') }}</td>
                <td class="text-right" style="color: #059669; font-size: 9.5px;">Rp {{ number_format($totalRem, 0, ',', '.') }}</td>
                <td class="text-center">-</td>
            </tr>
        </tbody>
    </table>

    <!-- Kotak Rekapitulasi Kas & Bukti Pengembalian -->
    <div class="recap-box">
        <table class="recap-grid">
            <tr>
                <td style="width: 40%;"><strong>Total Uang Muka Dicairkan Finance</strong></td>
                <td style="width: 60%;">: <strong>Rp {{ number_format($totalEst, 0, ',', '.') }}</strong></td>
            </tr>
            <tr>
                <td><strong>Total Realisasi Biaya Terpakai</strong></td>
                <td>: <strong>Rp {{ number_format($totalAct, 0, ',', '.') }}</strong></td>
            </tr>
            <tr>
                <td><strong style="color: #047857; font-size: 9px;">TOTAL SISA DANA WAJIB DIKEMBALIKAN</strong></td>
                <td>: <strong style="color: #047857; font-size: 10px;">Rp {{ number_format($totalRem, 0, ',', '.') }}</strong></td>
            </tr>
            <tr>
                <td><strong>Status Transfer Sisa ke Rekening Kas Finance</strong></td>
                <td>: 
                    @if(!empty($claim->return_transfer_proof))
                        <span style="color: #166534; font-weight: bold;">✓ SUDAH DITRANSFER (Rp {{ number_format((float)$claim->return_transfer_amount, 0, ',', '.') }})</span>
                    @elseif($totalRem <= 0)
                        <span style="color: #475569;">(Nihil / Seluruh dana terpakai)</span>
                    @else
                        <span style="color: #b91c1c; font-weight: bold;">BELUM DITRANSFER</span>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <!-- Lampiran Bukti Transfer Sisa / Struk SPBU -->
    @if(!empty($returnProofBase64) || (!empty($receiptPhotosBase64) && count($receiptPhotosBase64) > 0))
    <div class="photo-section-title">LAMPIRAN BUKTI FISIK & BUKTI TRANSFER PENGEMBALIAN SISA DANA</div>
    <table class="photo-grid-table">
        <tr>
            @if(!empty($returnProofBase64))
            <td>
                <div class="photo-card">
                    <img src="{{ $returnProofBase64 }}" alt="Bukti Transfer Sisa" class="photo-img"/>
                    <div class="photo-label">BUKTI TRANSFER PENGEMBALIAN SISA DANA KE FINANCE</div>
                </div>
            </td>
            @endif

            @if(!empty($receiptPhotosBase64) && isset($receiptPhotosBase64[0]))
            <td>
                <div class="photo-card">
                    <img src="{{ $receiptPhotosBase64[0] }}" alt="Struk SPBU / Tol" class="photo-img"/>
                    <div class="photo-label">STRUK SPBU / TIKET / KARCIS RIIL OPERASIONAL</div>
                </div>
            </td>
            @endif
        </tr>
    </table>
    @endif

    <!-- Tanda Tangan 3 Pihak: Pemohon, Admin, Finance -->
    <table class="signatures-table">
        <tr>
            <!-- 1. Yang Menyerahkan (Pemohon) -->
            <td>
                <div class="sig-box">
                    <div class="sig-title">Yang Menyerahkan (Pemohon)</div>
                    @if(!empty($signatureApplicantBase64))
                        <img src="{{ $signatureApplicantBase64 }}" class="sig-img" alt="TTD Pemohon"/>
                    @else
                        <div class="sig-stamp">
                            <div style="font-size: 7.5px; font-weight: bold; color: #0284c7;">✓ TTD DIGITAL SISTEM</div>
                            <div style="font-size: 6.5px; color: #64748b;">Diserahkan: {{ $claim->nota_balik_submitted_at ? $claim->nota_balik_submitted_at->format('d/m/Y') : date('d/m/Y') }}</div>
                            <div style="font-size: 7px; color: #0f172a; font-weight: bold;">{{ $employee->name ?? ($claim->user->name ?? 'Pemohon') }}</div>
                        </div>
                    @endif
                    <div class="sig-name">{{ $employee->name ?? ($claim->user->name ?? 'Pemohon') }}</div>
                    <div class="sig-role">{{ $employee->position_name ?? 'Pemohon Perdin' }}</div>
                </div>
            </td>

            <!-- 2. Diverifikasi Admin -->
            <td>
                <div class="sig-box">
                    <div class="sig-title">Diverifikasi Admin Region</div>
                    @if($claim->nota_balik_submitted)
                        <div class="sig-stamp-approved">
                            <div style="font-size: 7.5px; font-weight: bold; color: #16a34a;">✓ TERVERIFIKASI ADMIN</div>
                            <div style="font-size: 6.5px; color: #64748b;">Nota Balik Terinput</div>
                            <div style="font-size: 7px; color: #0f172a; font-weight: bold;">{{ $claim->adminApprovedBy?->name ?? 'Admin Region' }}</div>
                        </div>
                    @else
                        <div class="sig-placeholder">[ Menunggu Verifikasi ]</div>
                    @endif
                    <div class="sig-name">{{ $claim->adminApprovedBy?->name ?? 'Admin Region' }}</div>
                    <div class="sig-role">Verifikator Nota Balik</div>
                </div>
            </td>

            <!-- 3. Disetujui Tim Finance -->
            <td>
                <div class="sig-box">
                    <div class="sig-title">Disetujui Tim Finance</div>
                    @if($claim->nota_balik_status === 'Disetujui Finance')
                        <div class="sig-stamp-approved">
                            <div style="font-size: 7.5px; font-weight: bold; color: #16a34a;">✓ ACC FINANCE</div>
                            <div style="font-size: 6.5px; color: #64748b;">Validasi Selesai</div>
                            <div style="font-size: 7px; color: #0f172a; font-weight: bold;">{{ $claim->financeApprovedBy?->name ?? 'Tim Finance' }}</div>
                        </div>
                    @elseif($claim->nota_balik_status === 'Ditolak Finance')
                        <div class="sig-placeholder" style="color: #dc2626; font-weight: bold;">[ DITOLAK FINANCE ]</div>
                    @else
                        <div class="sig-placeholder">[ Antrean Validasi ]</div>
                    @endif
                    <div class="sig-name">{{ $claim->financeApprovedBy?->name ?? 'Tim Finance' }}</div>
                    <div class="sig-role">{{ $claim->badan_usaha ?: 'PT Media Selular Indonesia' }}</div>
                </div>
            </td>
        </tr>
    </table>

</body>
</html>
