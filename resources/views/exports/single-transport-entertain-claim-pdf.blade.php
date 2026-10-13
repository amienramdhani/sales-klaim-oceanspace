<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Voucher Klaim Transport & Entertain - {{ $claim->_uid ?: ($employee->name ?? 'MSI') }}</title>
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


        .photos-section {
            page-break-before: auto;
            margin-top: 15px;
            page-break-inside: avoid;
        }
        .photo-card {
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 5px;
            text-align: center;
            background: #ffffff;
            margin-bottom: 8px;
            page-break-inside: avoid;
        }
        .photo-img {
            max-width: 100%;
            max-height: 250px;
            object-fit: contain;
            border: 0.5px solid #e2e8f0;
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
            vertical-align: top;
            padding: 0 4px;
        }
        .sig-box {
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            text-align: center;
            padding: 6px;
            background: #ffffff;
            min-height: 90px;
        }
        .sig-title {
            font-size: 8px;
            font-weight: bold;
            color: #475569;
            border-bottom: 0.5px solid #e2e8f0;
            padding-bottom: 3px;
            margin-bottom: 4px;
            text-transform: uppercase;
        }
        .sig-img {
            max-height: 48px;
            max-width: 120px;
            display: block;
            margin: 0 auto;
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
        .sig-placeholder {
            height: 48px;
            line-height: 48px;
            color: #94a3b8;
            font-style: italic;
            font-size: 8px;
        }
        .sig-name {
            font-size: 8px;
            font-weight: bold;
            color: #0f172a;
            margin-top: 3px;
        }
        .sig-role {
            font-size: 7px;
            color: #64748b;
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
                <div style="font-size: 9.5px; font-weight: bold; color: #0f766e;">FORMULIR KLAIM BIAYA</div>
                <div style="font-size: 8px; color: #475569;">No. UID: <strong style="font-family: monospace; font-size: 9px; color: #0f172a;">{{ $claim->_uid ?: 'DRAFT' }}</strong></div>
                <div style="font-size: 7.5px; color: #64748b;">Dicetak: {{ date('d/m/Y H:i') }} WIB</div>
            </td>
        </tr>
    </table>

    @if($claim->is_perdin || $claim->claim_category === 'perdin')
        <div class="voucher-title">FORM PENGAJUAN PERJALANAN DINAS</div>
        <div class="voucher-subtitle">
            Nomor: {{ $claim->_uid ?: 'DRAFT' }} | Tanggal Pengajuan: {{ $claim->claim_date ? $claim->claim_date->format('d F Y') : '-' }}
        </div>
    @else
        <div class="voucher-title">PENGAJUAN & REALISASI KLAIM BIAYA TRANSPORTASI & ENTERTAIN</div>
        <div class="voucher-subtitle">
            Kategori: {{ strtoupper($claim->claim_category ?: 'Transport & Entertain') }} | 
            Tanggal Pengajuan: {{ $claim->claim_date ? $claim->claim_date->format('d F Y') : '-' }}
        </div>
    @endif

    <!-- INFORMASI PENGAJUAN -->
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
                        <td style="font-weight: bold; color: #475569; padding: 2px 0;">Region / Homebase</td>
                        <td style="padding: 2px 0;">: {{ $claim->region ?: ($employee->region ?? ($employee->homebase ?? '-')) }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; color: #475569; padding: 2px 0;">Cabang / Branch</td>
                        <td style="padding: 2px 0;">: {{ $claim->branch?->name ?: ($claim->city ?: '-') }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; color: #475569; padding: 2px 0;">Rekening Tujuan</td>
                        <td style="padding: 2px 0;">: <strong>{{ $claim->getEffectiveBankName() }} - {{ $claim->getEffectiveBankAccountNumber() }}</strong><br>&nbsp; a/n {{ $claim->getEffectiveBankAccountName() }}
                            @if(!empty($claim->custom_bank_account_name))
                                <span style="font-size: 7px; color: #d97706; font-weight: bold;">(Vendor / Rekening Lain)</span>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
            <td style="width: 50%;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="width: 120px; font-weight: bold; color: #475569; padding: 2px 0;">Brand / Reffnote</td>
                        <td style="padding: 2px 0;">: {{ $claim->brand ?: '-' }} / {{ $claim->reffnote ?: '-' }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; color: #475569; padding: 2px 0;">Kota Tujuan / Kegiatan</td>
                        <td style="padding: 2px 0;">: {{ $claim->city ?: '-' }}</td>
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

    <!-- TABEL RINCIAN TRANSAKSI DENGAN KETERANGAN TEMPAT -->
    <div style="font-weight: bold; font-size: 9px; margin-bottom: 4px; color: #1e293b;">
        RINCIAN TRANSAKSI PENGELUARAN :
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 25px;">NO</th>
                <th style="width: 70px;">TANGGAL</th>
                <th style="width: 95px;">JENIS BIAYA</th>
                <th style="text-align: left;">KEPERLUAN / AGENDA</th>
                <th style="width: 130px; text-align: left;">KETERANGAN TEMPAT / TOKO</th>
                <th style="width: 75px;">KOTA</th>
                <th style="width: 95px;">NOMINAL (Rp)</th>
            </tr>
        </thead>
        <tbody>
            @php $subtotal = 0; @endphp
            @forelse($lineItems as $idx => $item)
                @php 
                    $amount = (float)($item['amount'] ?? 0);
                    $subtotal += $amount;
                    $itemDate = !empty($item['receipt_date']) ? date('d/m/Y', strtotime($item['receipt_date'])) : ($claim->claim_date ? $claim->claim_date->format('d/m/Y') : '-');
                    $itemPlace = $item['note'] ?? ($item['attendance_place'] ?? '-');
                @endphp
                <tr class="{{ $idx % 2 === 1 ? 'even' : '' }}">
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td class="text-center">{{ $itemDate }}</td>
                    <td class="text-center" style="font-weight: bold;">
                        {{ $item['claim_type'] ?? ($claim->claim_type_string ?: 'Entertain') }}
                        @if(!empty($item['entertain_subtype']))
                            <div style="font-size: 7px; color: #64748b;">({{ $item['entertain_subtype'] }})</div>
                        @endif
                    </td>
                    <td class="text-left">
                        {{ $item['purpose'] ?? '-' }}
                    </td>
                    <td class="text-left">
                        {{ $itemPlace }}
                    </td>
                    <td class="text-center">{{ $item['city'] ?? ($claim->city ?: '-') }}</td>
                    <td class="text-right" style="font-weight: bold;">{{ number_format($amount, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td class="text-center">1</td>
                    <td class="text-center">{{ $claim->claim_date ? $claim->claim_date->format('d/m/Y') : '-' }}</td>
                    <td class="text-center">{{ $claim->claim_type_string ?: 'Entertain' }}</td>
                    <td class="text-left">{{ $claim->purpose_string ?: '-' }}</td>
                    <td class="text-left">{{ $claim->note ?: '-' }}</td>
                    <td class="text-center">{{ $claim->city ?: '-' }}</td>
                    <td class="text-right" style="font-weight: bold;">{{ number_format((float)$claim->amount, 0, ',', '.') }}</td>
                </tr>
                @php $subtotal = (float)$claim->amount; @endphp
            @endforelse

            <tr class="total-row">
                <td colspan="6" class="text-right" style="padding-right: 8px;">TOTAL KLAIM DIAJUKAN :</td>
                <td class="text-right" style="color: #0f766e; font-size: 9px;">
                    Rp {{ number_format($subtotal > 0 ? $subtotal : (float)$claim->amount, 0, ',', '.') }}
                </td>
            </tr>
        </tbody>
    </table>

    <!-- LEMBAR TANDA TANGAN OTOMATIS (Item 10) -->
    <table class="signatures-table">
        <tr>
            <!-- 1. Diajukan Oleh (Pemohon) -->
            <td>
                <div class="sig-box">
                    <div class="sig-title">Yang Mengajukan (Pemohon)</div>
                    @if(!empty($signatureBase64))
                        <img src="{{ $signatureBase64 }}" class="sig-img" alt="TTD Pemohon"/>
                    @else
                        <div class="sig-stamp">
                            <div style="font-size: 7.5px; font-weight: bold; color: #0284c7;">✓ TTD DIGITAL SISTEM</div>
                            <div style="font-size: 6.5px; color: #64748b;">Diajukan: {{ $claim->claim_date ? $claim->claim_date->format('d/m/Y') : date('d/m/Y') }}</div>
                            <div style="font-size: 7px; color: #0f172a; font-weight: bold;">{{ $employee->name ?? ($claim->user->name ?? 'Pemohon') }}</div>
                        </div>
                    @endif
                    <div class="sig-name">{{ $employee->name ?? ($claim->user->name ?? 'Pemohon') }}</div>
                    <div class="sig-role">{{ $employee->position_name ?? ($employee->role->name ?? 'Sales / Submitter') }}</div>
                </div>
            </td>

            <!-- 2. Disetujui Atasan Langsung (ASM / RGM / Jejen) -->
            <td>
                <div class="sig-box">
                    <div class="sig-title">{{ $atasanTitle ?? 'Disetujui Oleh (Atasan Langsung)' }}</div>
                    @if(!empty($signatureSupervisorBase64))
                        <img src="{{ $signatureSupervisorBase64 }}" class="sig-img" alt="TTD Atasan"/>
                    @elseif(in_array($claim->approval_status, ['ACC_ASM', 'ACC_RGM', 'ACC_ADMIN', 'ACC_PAK_JEJEN', 'WAITING_YOGA', 'WAITING_NURBAITI', 'WAITING_THIA', 'DISETUJUI']))
                        <div class="sig-stamp-approved">
                            <div style="font-size: 7.5px; font-weight: bold; color: #16a34a;">✓ TERVERIFIKASI & DISETUJUI</div>
                            <div style="font-size: 6.5px; color: #64748b;">Status: {{ $claim->approval_status }}</div>
                            <div style="font-size: 7px; color: #0f172a; font-weight: bold;">{{ $atasanName ?? ($claim->approvedByJejen?->name ?? ($claim->approvedByRgm?->name ?? ($claim->approvedByAsm?->name ?? 'Atasan Langsung'))) }}</div>
                        </div>
                    @else
                        <div class="sig-placeholder">[ Menunggu Persetujuan ]</div>
                    @endif
                    <div class="sig-name">{{ $atasanName ?? ($claim->approvedByJejen?->name ?? ($claim->approvedByRgm?->name ?? ($claim->approvedByAsm?->name ?? 'Atasan Langsung'))) }}</div>
                    <div class="sig-role">{{ $atasanPosition ?? 'RGM / Head of Sales' }}</div>
                </div>
            </td>

            <!-- 3. Mengetahui / Finance -->
            <td>
                <div class="sig-box">
                    <div class="sig-title">Mengetahui / Pencairan Kas</div>
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
                    <div class="sig-name">Tim Finance & Accounting</div>
                    <div class="sig-role">{{ $claim->badan_usaha ?: 'PT Media Selular Indonesia' }}</div>
                </div>
            </td>
        </tr>
    </table>



    <!-- LAMPIRAN BUKTI NOTA / KWITANSI -->
    @if(!empty($photosBase64) && count($photosBase64) > 0)
        <div style="page-break-before: always;"></div>
        <table class="header-table">
            <tr>
                <td style="width: 70%;">
                    <div class="company-title">PT. MEDIA SELULAR INDONESIA</div>
                    <div style="font-size: 8px; color: #475569;">Lampiran Bukti Nota / Kwitansi - No. UID: {{ $claim->_uid ?: 'DRAFT' }}</div>
                </td>
                <td style="width: 30%; text-align: right;">
                    <div style="font-size: 8.5px; font-weight: bold; color: #0f766e;">LAMPIRAN DOKUMEN</div>
                    <div style="font-size: 7.5px; color: #64748b;">Pemohon: {{ $employee->name ?? '-' }}</div>
                </td>
            </tr>
        </table>

        <div style="font-weight: bold; font-size: 10px; color: #1e293b; margin-bottom: 8px;">
            DOKUMENTASI FOTO NOTA / STRUK PEMBAYARAN ({{ count($photosBase64) }} Foto) :
        </div>

        <table style="width: 100%; border-collapse: collapse;">
            @foreach(array_chunk($photosBase64, 2) as $chunk)
                <tr>
                    @foreach($chunk as $idx => $photo)
                        <td style="width: 50%; padding: 6px; vertical-align: top;">
                            <div class="photo-card">
                                <div style="font-size: 8px; font-weight: bold; color: #475569; margin-bottom: 4px;">
                                    Foto Bukti #{{ $loop->parent->index * 2 + $idx + 1 }}
                                </div>
                                <img src="{{ $photo }}" class="photo-img" style="max-height: 280px;" alt="Bukti Nota">
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

    <!-- LAMPIRAN LEMBAR DAFTAR HADIR PERTEMUAN / JAMUAN MAKAN -->
    @if(!empty($attendanceList) && count($attendanceList) > 0)
        @foreach($attendanceList as $attIdx => $att)
            <div style="page-break-before: always;"></div>
            <table class="header-table">
                <tr>
                    <td style="width: 70%;">
                        <div class="company-title">PT. MEDIA SELULAR INDONESIA</div>
                        <div style="font-size: 8px; color: #475569;">Lampiran Dokumen Daftar Hadir - No. UID: {{ $claim->_uid ?: 'DRAFT' }}</div>
                    </td>
                    <td style="width: 30%; text-align: right;">
                        <div style="font-size: 8.5px; font-weight: bold; color: #0f766e;">DAFTAR HADIR #{{ $attIdx + 1 }}</div>
                        <div style="font-size: 7.5px; color: #64748b;">Pemohon: {{ $employee->name ?? '-' }}</div>
                    </td>
                </tr>
            </table>

            <div style="background-color: #f8fafc; border: 1px solid #cbd5e1; border-radius: 4px; padding: 10px; margin-top: 8px; margin-bottom: 12px;">
                <div style="font-size: 10px; font-weight: bold; color: #0f766e; margin-bottom: 6px; text-transform: uppercase;">
                    LEMBAR DAFTAR HADIR PERTEMUAN / JAMUAN MAKAN
                </div>
                <table style="width: 100%; border-collapse: collapse; font-size: 8.5px;">
                    <tr>
                        <td style="width: 110px; font-weight: bold; color: #475569; padding: 2px 0;">Perihal / Agenda</td>
                        <td style="padding: 2px 0;">: {{ $att['purpose'] }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; color: #475569; padding: 2px 0;">Tempat / Resto</td>
                        <td style="padding: 2px 0;">: {{ $att['place'] }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; color: #475569; padding: 2px 0;">Tanggal Acara</td>
                        <td style="padding: 2px 0;">: {{ $att['date'] }}</td>
                    </tr>
                    @if(!empty($att['notes']))
                    <tr>
                        <td style="font-weight: bold; color: #475569; padding: 2px 0;">Catatan Tambahan</td>
                        <td style="padding: 2px 0;">: {{ $att['notes'] }}</td>
                    </tr>
                    @endif
                </table>
            </div>

            <div style="font-weight: bold; font-size: 9px; margin-bottom: 4px; color: #1e293b;">
                DAFTAR PESERTA YANG HADIR :
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 25px; text-align: center;">NO</th>
                        <th style="text-align: left;">NAMA LENGKAP PESERTA</th>
                        <th style="width: 140px; text-align: left;">JABATAN / DIVISI</th>
                        <th style="width: 90px; text-align: center;">STATUS KEHADIRAN</th>
                        <th style="width: 110px; text-align: center;">TANDA TANGAN / PARAF</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($att['participants'] as $pIdx => $p)
                        <tr class="{{ $pIdx % 2 === 1 ? 'even' : '' }}">
                            <td class="text-center">{{ $pIdx + 1 }}</td>
                            <td class="text-left" style="font-weight: bold;">{{ $p['name'] ?? '-' }}</td>
                            <td class="text-left">{{ $p['position'] ?? 'Peserta' }}</td>
                            <td class="text-center" style="font-weight: bold; color: #0f766e;">{{ $p['status'] ?? 'HADIR' }}</td>
                            <td class="text-center" style="padding: 2px 4px; vertical-align: middle; height: 38px;">
                                @if(!empty($p['signature_base64']))
                                    <img src="{{ $p['signature_base64'] }}" style="max-height: 34px; max-width: 95px; display: inline-block; vertical-align: middle;" alt="TTD"/>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center" style="color: #64748b; font-style: italic;">Tidak ada peserta terdaftar.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <!-- LEMBAR TANDA TANGAN PENGESAHAN DAFTAR HADIR (Pemohon & Atasan) -->
            <table class="signatures-table" style="margin-top: 15px; width: 100%;">
                <tr>
                    <td style="width: 50%; padding-right: 12px;">
                        <div class="sig-box">
                            <div class="sig-title">Yang Membuat / Melaporkan (Pemohon)</div>
                            @if(!empty($signatureBase64))
                                <img src="{{ $signatureBase64 }}" class="sig-img" alt="TTD Pemohon"/>
                            @else
                                <div class="sig-stamp">
                                    <div style="font-size: 7.5px; font-weight: bold; color: #0284c7;">✓ TTD DIGITAL SISTEM</div>
                                    <div style="font-size: 6.5px; color: #64748b;">Tanggal: {{ $att['date'] ?? ($claim->claim_date ? $claim->claim_date->format('d/m/Y') : date('d/m/Y')) }}</div>
                                    <div style="font-size: 7px; color: #0f172a; font-weight: bold;">{{ $employee->name ?? ($claim->user->name ?? 'Pemohon') }}</div>
                                </div>
                            @endif
                            <div class="sig-name">{{ $employee->name ?? ($claim->user->name ?? 'Pemohon') }}</div>
                            <div class="sig-role">{{ $employee->position_name ?? ($employee->role->name ?? 'Sales / Submitter') }}</div>
                        </div>
                    </td>
                    <td style="width: 50%; padding-left: 12px;">
                        <div class="sig-box">
                            <div class="sig-title">{{ $atasanTitle ?? 'Mengetahui / Disetujui (Atasan Langsung)' }}</div>
                            @if(!empty($signatureSupervisorBase64))
                                <img src="{{ $signatureSupervisorBase64 }}" class="sig-img" alt="TTD Atasan"/>
                            @elseif(in_array($claim->approval_status, ['ACC_ASM', 'ACC_RGM', 'ACC_ADMIN', 'ACC_PAK_JEJEN', 'WAITING_YOGA', 'WAITING_NURBAITI', 'WAITING_THIA', 'DISETUJUI']))
                                <div class="sig-stamp-approved">
                                    <div style="font-size: 7.5px; font-weight: bold; color: #16a34a;">✓ TERVERIFIKASI & DISETUJUI</div>
                                    <div style="font-size: 6.5px; color: #64748b;">Status: {{ $claim->approval_status }}</div>
                                    <div style="font-size: 7px; color: #0f172a; font-weight: bold;">{{ $atasanName ?? ($claim->approvedByJejen?->name ?? ($claim->approvedByRgm?->name ?? ($claim->approvedByAsm?->name ?? 'Atasan Langsung'))) }}</div>
                                </div>
                            @else
                                <div class="sig-placeholder">[ Menunggu Persetujuan Atasan ]</div>
                            @endif
                            <div class="sig-name">{{ $atasanName ?? ($claim->approvedByJejen?->name ?? ($claim->approvedByRgm?->name ?? ($claim->approvedByAsm?->name ?? 'Atasan Langsung'))) }}</div>
                            <div class="sig-role">{{ $atasanPosition ?? 'RGM / Head of Sales' }}</div>
                        </div>
                    </td>
                </tr>
            </table>
        @endforeach
    @endif

    <!-- LAMPIRAN DOKUMENTASI FOTO KEGIATAN PERTEMUAN / ENTERTAIN -->
    @if(!empty($attendanceDocsBase64) && count($attendanceDocsBase64) > 0)
        <div style="page-break-before: always;"></div>
        <table class="header-table">
            <tr>
                <td style="width: 70%;">
                    <div class="company-title">PT. MEDIA SELULAR INDONESIA</div>
                    <div style="font-size: 8px; color: #475569;">Dokumentasi Foto Acara / Pertemuan - No. UID: {{ $claim->_uid ?: 'DRAFT' }}</div>
                </td>
                <td style="width: 30%; text-align: right;">
                    <div style="font-size: 8.5px; font-weight: bold; color: #0f766e;">DOKUMENTASI ACARA</div>
                    <div style="font-size: 7.5px; color: #64748b;">Pemohon: {{ $employee->name ?? '-' }}</div>
                </td>
            </tr>
        </table>

        <div style="font-weight: bold; font-size: 10px; color: #1e293b; margin-bottom: 8px;">
            FOTO DOKUMENTASI KEGIATAN / ACARA ({{ count($attendanceDocsBase64) }} Foto) :
        </div>

        <table style="width: 100%; border-collapse: collapse;">
            @foreach(array_chunk($attendanceDocsBase64, 2) as $chunk)
                <tr>
                    @foreach($chunk as $idx => $photo)
                        <td style="width: 50%; padding: 6px; vertical-align: top;">
                            <div class="photo-card">
                                <div style="font-size: 8px; font-weight: bold; color: #475569; margin-bottom: 4px;">
                                    Dokumentasi Kegiatan #{{ $loop->parent->index * 2 + $idx + 1 }}
                                </div>
                                <img src="{{ $photo }}" class="photo-img" style="max-height: 280px;" alt="Dokumentasi Acara">
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
