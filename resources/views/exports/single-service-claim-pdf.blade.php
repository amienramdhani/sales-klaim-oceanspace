<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Klaim Service Kendaraan - {{ $claim->_uid ?: ($employee->name ?? 'Sales') }}</title>
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
            font-size: 12px;
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
            font-size: 8.5px;
            margin-bottom: 12px;
        }
        table.data-table th, table.data-table td {
            border: 0.5px solid #94a3b8;
            padding: 5px 8px;
        }
        table.data-table th {
            background-color: #1e293b;
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
            margin-bottom: 10px;
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
            max-height: 190px;
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
            width: 25%;
            text-align: center;
            vertical-align: top;
            padding: 4px 6px;
        }
        .sig-box {
            border: 1px solid #cbd5e1;
            background-color: #ffffff;
            border-radius: 4px;
            padding: 6px 4px;
        }
        .sig-title {
            font-weight: bold;
            font-size: 8px;
            color: #1e293b;
            border-bottom: 0.5px solid #e2e8f0;
            padding-bottom: 3px;
            margin-bottom: 4px;
        }
        .sig-img {
            height: 45px;
            display: block;
            margin: 0 auto;
        }
        .sig-placeholder {
            height: 45px;
            line-height: 45px;
            font-size: 7.5px;
            color: #94a3b8;
            font-style: italic;
        }
        .sig-stamp {
            border: 1px dashed #0284c7;
            background-color: #f0f9ff;
            color: #0369a1;
            border-radius: 4px;
            padding: 3px;
            margin: 2px auto;
            font-size: 6.5px;
            font-weight: bold;
            line-height: 1.2;
            width: 90%;
        }
        .sig-stamp-approved {
            border: 1px dashed #16a34a;
            background-color: #f0fdf4;
            color: #15803d;
            border-radius: 4px;
            padding: 3px;
            margin: 2px auto;
            font-size: 6.5px;
            font-weight: bold;
            line-height: 1.2;
            width: 90%;
        }
        .sig-name {
            font-weight: bold;
            font-size: 8px;
            color: #0f172a;
            border-top: 0.5px dotted #64748b;
            padding-top: 3px;
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
                <div class="company-subtitle">Sistem Manajemen Operasional & Klaim Pemeliharaan Kendaraan Sales</div>
            </td>
            <td style="text-align: right; vertical-align: middle;">
                <div style="font-size: 10px; font-weight: bold; color: #0369a1;">_UID: {{ $claim->_uid ?: 'DRAFT' }}</div>
                <div style="font-size: 8px; color: #64748b;">Tanggal Cetak: {{ date('d/m/Y H:i') }}</div>
            </td>
        </tr>
    </table>

    <div class="voucher-title">FORMULIR PENGAJUAN KLAIM SERVICE MOTOR / KENDARAAN</div>
    <div class="voucher-subtitle">Nomor Form: {{ $claim->_uid ?: 'SERVICE-' . date('Ymd') }} | Status: {{ $claim->approval_status }}</div>

    @php
        $emp = $employee ?? $claim->effective_employee;
        $employee = $emp;

        // 1. Resolve Region: Prioritaskan region employee, atau region klaim (jika bukan multi-region admin), atau homebase, atau kota branch
        $rawClaimRegion = $claim->region;
        $isMulti = $rawClaimRegion && str_contains($rawClaimRegion, ',');
        $resolvedRegion = (!empty($emp?->region) && $emp->region !== '-') 
            ? $emp->region 
            : ((!empty($rawClaimRegion) && !$isMulti) 
                ? $rawClaimRegion 
                : ($emp?->homebase ?: ($claim->branch?->city ?: ($rawClaimRegion ?: '-'))));

        // 2. Resolve Cabang / Branch:
        $resolvedBranch = $claim->branch?->name 
            ?: ($claim->city ?: ($claim->branch_name ?? null));
        if (empty($resolvedBranch) && $emp) {
            $branches = $emp->getManagedBranchesList();
            if (!empty($branches)) {
                $mbId = $branches[0];
                $mb = \App\Models\Branch::find($mbId);
                if ($mb) $resolvedBranch = $mb->name;
            }
        }
        $resolvedBranch = $resolvedBranch ?: '-';

        // 3. Resolve Reffnote:
        $resolvedReffnote = $claim->reffnote 
            ?: ($claim->branch?->reffnote 
                ?: ($claim->branch?->name ?: ($resolvedBranch !== '-' ? $resolvedBranch : '-')));

        // 4. Format Region / Cabang display:
        if ($resolvedBranch !== '-' && strtoupper($resolvedBranch) !== strtoupper($resolvedRegion)) {
            $regionCabangText = "{$resolvedRegion} / {$resolvedBranch}";
        } else {
            $regionCabangText = $resolvedRegion;
        }
    @endphp

    <!-- Informasi Pemohon -->
    <table class="info-table">
        <tr>
            <td style="width: 18%;"><strong>Nama Pemohon</strong></td>
            <td style="width: 32%;">: {{ $emp->name ?? ($claim->user->name ?? '-') }}</td>
            <td style="width: 18%;"><strong>Badan Usaha</strong></td>
            <td style="width: 32%;">: {{ $claim->badan_usaha ?: 'PT MEDIA SELULAR INDONESIA' }}</td>
        </tr>
        <tr>
            <td><strong>Jabatan / Posisi</strong></td>
            <td>: {{ $emp->position_name ?? ($emp->role->name ?? 'Sales') }}</td>
            <td><strong>Region / Cabang</strong></td>
            <td>: {{ $regionCabangText }}</td>
        </tr>
        <tr>
            <td><strong>Jenis Kendaraan</strong></td>
            <td>: {{ $claim->vehicle_type ?: 'Motor' }} (Plat: {{ $claim->vehicle_plate_number ?: ($emp->vehicle_plate_number ?? '-') }})</td>
            <td><strong>Reffnote</strong></td>
            <td>: <strong>{{ $resolvedReffnote }}</strong></td>
        </tr>
        <tr>
            <td><strong>Nama Bengkel</strong></td>
            <td>: {{ $claim->workshop_name ?: ($claim->note ?: 'Bengkel Resmi / Rekanan') }}</td>
            <td><strong>Tanggal Servis</strong></td>
            <td>: {{ $claim->claim_date ? $claim->claim_date->format('d/m/Y') : date('d/m/Y') }}</td>
        </tr>
        <tr>
            <td><strong>Rekening Tujuan</strong></td>
            <td>: {{ $claim->getEffectiveBankName() }} - {{ $claim->getEffectiveBankAccountNumber() }} (a/n {{ $claim->getEffectiveBankAccountName() }})</td>
            <td><strong>Status Pencairan</strong></td>
            <td>: <span class="badge {{ $claim->disbursement_status === 'Sudah Dicairkan' ? 'badge-success' : 'badge-info' }}">{{ $claim->disbursement_status }}</span></td>
        </tr>
    </table>

    <!-- Rincian Biaya Servis -->
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th style="width: 35%;">Uraian Pekerjaan / Komponen Servis</th>
                <th style="width: 35%;">Keterangan / Keluhan Kendaraan</th>
                <th style="width: 25%;">Nominal Biaya (Rp)</th>
            </tr>
        </thead>
        <tbody>
            @if(is_array($claim->items) && count($claim->items) > 0)
                @foreach($claim->items as $idx => $item)
                    <tr>
                        <td class="text-center">{{ $idx + 1 }}</td>
                        <td><strong>{{ $item['item_name'] ?? ($item['name'] ?? 'Service Komponen') }}</strong></td>
                        <td>{{ $item['purpose'] ?? ($item['description'] ?? ($claim->note ?? '-')) }}</td>
                        <td class="text-right font-bold" style="font-size: 10px;">
                            Rp {{ number_format((float)($item['amount'] ?? 0), 0, ',', '.') }}
                        </td>
                    </tr>
                @endforeach
            @else
                <tr>
                    <td class="text-center">1</td>
                    <td><strong>Service Rutin & Ganti Oli / Sparepart</strong></td>
                    <td>{{ is_array($claim->purpose) ? implode(', ', $claim->purpose) : ($claim->purpose ?: ($claim->description ?: 'Perawatan berkala sepeda motor operasional sales')) }}</td>
                    <td class="text-right font-bold" style="font-weight: bold; font-size: 10px;">
                        Rp {{ number_format((float)$claim->amount, 0, ',', '.') }}
                    </td>
                </tr>
            @endif
            <tr style="background-color: #f1f5f9; font-weight: bold;">
                <td colspan="3" class="text-right">TOTAL BIAYA SERVICE KENDARAAN:</td>
                <td class="text-right" style="color: #0369a1; font-size: 10.5px;">
                    Rp {{ number_format((float)$claim->amount, 0, ',', '.') }}
                </td>
            </tr>
        </tbody>
    </table>

    <!-- DOKUMENTASI FOTO SEBELUM & SESUDAH SERVIS (BEFORE & AFTER) -->
    <div class="photo-section-title">DOKUMENTASI FOTO BEFORE & AFTER SERVIS KENDARAAN</div>
    @if(!empty($photoBeforeBase64) || !empty($photoAfterBase64))
        <table class="photo-grid-table">
            <tr>
                <td style="width: 50%; padding: 4px; vertical-align: top; text-align: center;">
                    <div class="photo-card">
                        <div class="photo-label" style="background-color: #f0fdfa; color: #0f766e; border: 1px solid #ccfbf1; margin-bottom: 4px; font-weight: bold;">
                            FOTO SEBELUM SERVIS (BEFORE)
                        </div>
                        @if(!empty($photoBeforeBase64))
                            <img src="{{ $photoBeforeBase64 }}" alt="Foto Before Servis" class="photo-img"/>
                        @else
                            <div class="sig-placeholder" style="height: 140px; line-height: 140px;">[ Foto Sebelum Servis Belum Diunggah ]</div>
                        @endif
                        <div style="font-size: 7px; color: #64748b; margin-top: 3px;">Kondisi Kendaraan / Sparepart Sebelum Pengerjaan</div>
                    </div>
                </td>
                <td style="width: 50%; padding: 4px; vertical-align: top; text-align: center;">
                    <div class="photo-card">
                        <div class="photo-label" style="background-color: #f0f9ff; color: #0284c7; border: 1px solid #e0f2fe; margin-bottom: 4px; font-weight: bold;">
                            FOTO SESUDAH SERVIS (AFTER)
                        </div>
                        @if(!empty($photoAfterBase64))
                            <img src="{{ $photoAfterBase64 }}" alt="Foto After Servis" class="photo-img"/>
                        @else
                            <div class="sig-placeholder" style="height: 140px; line-height: 140px;">[ Foto Sesudah Servis Belum Diunggah ]</div>
                        @endif
                        <div style="font-size: 7px; color: #64748b; margin-top: 3px;">Kondisi Kendaraan / Sparepart Sesudah Selesai Servis</div>
                    </div>
                </td>
            </tr>
        </table>
    @elseif(!empty($photoCombinedBase64))
        <table class="photo-grid-table">
            <tr>
                <td style="width: 100%; padding: 4px; vertical-align: top; text-align: center;">
                    <div class="photo-card">
                        <div class="photo-label" style="background-color: #f0fdfa; color: #0f766e; border: 1px solid #ccfbf1; margin-bottom: 4px; font-weight: bold;">
                            FOTO GABUNGAN BEFORE & AFTER (HASIL SISTEM)
                        </div>
                        <img src="{{ $photoCombinedBase64 }}" alt="Foto Gabungan Before & After" class="photo-img" style="max-height: 240px;"/>
                        <div style="font-size: 7px; color: #64748b; margin-top: 3px;">Foto Komposit Sebelum dan Sesudah Servis Kendaraan</div>
                    </div>
                </td>
            </tr>
        </table>
    @else
        <table class="photo-grid-table">
            <tr>
                <td style="width: 50%; padding: 4px; vertical-align: top; text-align: center;">
                    <div class="photo-card">
                        <div class="photo-label">FOTO SEBELUM SERVIS (BEFORE)</div>
                        <div class="sig-placeholder" style="height: 120px; line-height: 120px;">[ Foto Sebelum Servis Belum Diunggah ]</div>
                    </div>
                </td>
                <td style="width: 50%; padding: 4px; vertical-align: top; text-align: center;">
                    <div class="photo-card">
                        <div class="photo-label">FOTO SESUDAH SERVIS (AFTER)</div>
                        <div class="sig-placeholder" style="height: 120px; line-height: 120px;">[ Foto Sesudah Servis Belum Diunggah ]</div>
                    </div>
                </td>
            </tr>
        </table>
    @endif

    @if(!empty($receiptPhotosBase64) && count($receiptPhotosBase64) > 0)
        <div class="photo-section-title">LAMPIRAN FOTO KWITANSI / NOTA BENGKEL</div>
        <table class="photo-grid-table">
            <tr>
                @foreach($receiptPhotosBase64 as $idx => $rB64)
                    @if($idx < 2)
                        <td>
                            <div class="photo-card">
                                <img src="{{ $rB64 }}" alt="Nota Bengkel" class="photo-img" style="max-height: 160px;"/>
                                <div class="photo-label">NOTA / STRUK FISIK BENGKEL (Lampiran {{ $idx + 1 }})</div>
                            </div>
                        </td>
                    @endif
                @endforeach
            </tr>
        </table>
    @endif

    <!-- Kolom Tanda Tangan (Item 10) -->
    <table class="signatures-table">
        <tr>
            <!-- 1. Diajukan Oleh -->
            <td>
                <div class="sig-box">
                    <div class="sig-title">Diajukan Oleh</div>
                    @if(!empty($signatureApplicantBase64))
                        <img src="{{ $signatureApplicantBase64 }}" class="sig-img" alt="TTD Pemohon"/>
                    @else
                        <div class="sig-stamp">
                            <div style="font-size: 7px; font-weight: bold; color: #0284c7;">✓ TTD DIGITAL</div>
                            <div style="font-size: 6px; color: #64748b;">Diajukan: {{ $claim->claim_date ? $claim->claim_date->format('d/m/Y') : date('d/m/Y') }}</div>
                            <div style="font-size: 6.5px; color: #0f172a; font-weight: bold;">{{ $employee->name ?? ($claim->user->name ?? 'Pemohon') }}</div>
                        </div>
                    @endif
                    <div class="sig-name">{{ $employee->name ?? ($claim->user->name ?? 'Pemohon') }}</div>
                    <div class="sig-role">{{ $employee->position_name ?? 'Sales / Submitter' }}</div>
                </div>
            </td>

            <!-- 2. Disetujui Atasan -->
            <td>
                <div class="sig-box">
                    <div class="sig-title">Disetujui Atasan (ASM/RGM)</div>
                    @if(!empty($signatureSupervisorBase64))
                        <img src="{{ $signatureSupervisorBase64 }}" class="sig-img" alt="TTD Atasan"/>
                    @elseif($claim->approvedByRgm || $claim->approvedByAsm || in_array($claim->approval_status, ['ACC_ASM', 'ACC_RGM', 'ACC_ADMIN', 'ACC_PAK_JEJEN', 'DISETUJUI']))
                        <div class="sig-stamp-approved">
                            <div style="font-size: 7px; font-weight: bold; color: #16a34a;">✓ TERVERIFIKASI</div>
                            <div style="font-size: 6px; color: #64748b;">ACC: {{ $claim->approved_by_rgm_at ? $claim->approved_by_rgm_at->format('d/m/Y') : ($claim->approved_by_asm_at ? $claim->approved_by_asm_at->format('d/m/Y') : date('d/m/Y')) }}</div>
                            <div style="font-size: 6.5px; color: #0f172a; font-weight: bold;">{{ $claim->approvedByRgm?->name ?? ($claim->approvedByAsm?->name ?? 'ASM / RGM Terkait') }}</div>
                        </div>
                    @else
                        <div class="sig-placeholder">[ Menunggu Persetujuan ]</div>
                    @endif
                    <div class="sig-name">{{ $claim->approvedByRgm?->name ?? ($claim->approvedByAsm?->name ?? 'ASM / RGM Terkait') }}</div>
                    <div class="sig-role">Atasan Langsung</div>
                </div>
            </td>

            <!-- 3. Head of Sales -->
            <td>
                <div class="sig-box">
                    <div class="sig-title">Head of Sales</div>
                    @if(!empty($signatureJejenBase64))
                        <img src="{{ $signatureJejenBase64 }}" class="sig-img" alt="TTD Pak Jejen"/>
                    @elseif($claim->approvedByJejen || in_array($claim->approval_status, ['ACC_PAK_JEJEN', 'DISETUJUI']))
                        <div class="sig-stamp-approved">
                            <div style="font-size: 7px; font-weight: bold; color: #16a34a;">✓ ACC HEAD OF SALES</div>
                            <div style="font-size: 6px; color: #64748b;">Disetujui: {{ $claim->approved_by_jejen_at ? $claim->approved_by_jejen_at->format('d/m/Y') : date('d/m/Y') }}</div>
                            <div style="font-size: 6.5px; color: #0f172a; font-weight: bold;">{{ $claim->approvedByJejen?->name ?? 'Pak Jejen' }}</div>
                        </div>
                    @else
                        <div class="sig-placeholder">[ Menunggu Persetujuan ]</div>
                    @endif
                    <div class="sig-name">{{ $claim->approvedByJejen?->name ?? 'Pak Jejen' }}</div>
                    <div class="sig-role">Head of Sales</div>
                </div>
            </td>

            <!-- 4. Finance & Pencairan -->
            <td>
                <div class="sig-box">
                    <div class="sig-title">Finance & Pencairan</div>
                    @if($claim->disbursement_status === 'Sudah Dicairkan')
                        <div class="sig-stamp-approved">
                            <div style="font-size: 7px; font-weight: bold; color: #16a34a;">✓ DANA DICAIRKAN</div>
                            <div style="font-size: 6px; color: #64748b;">Tgl: {{ $claim->disbursed_at ? $claim->disbursed_at->format('d/m/Y') : date('d/m/Y') }}</div>
                            <div style="font-size: 6.5px; color: #0f172a; font-weight: bold;">Finance & Accounting</div>
                        </div>
                    @elseif($claim->approval_status === 'DISETUJUI')
                        <div class="sig-stamp">
                            <div style="font-size: 7px; font-weight: bold; color: #0284c7;">SIAP DICAIRKAN</div>
                            <div style="font-size: 6px; color: #64748b;">Disetujui Lengkap</div>
                            <div style="font-size: 6.5px; color: #0f172a; font-weight: bold;">Finance & Accounting</div>
                        </div>
                    @else
                        <div class="sig-placeholder">[ Antrean Finance ]</div>
                    @endif
                    <div class="sig-name">{{ $claim->financeApprovedBy?->name ?? 'Tim Finance' }}</div>
                    <div class="sig-role">Finance & Accounting</div>
                </div>
            </td>
        </tr>
    </table>

</body>
</html>
