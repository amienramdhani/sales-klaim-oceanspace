# PRD — Sistem Rekap Klaim Operasional

**Versi:** 1.0
**Status:** Draft / MVP
**Platform:** Web Internal
**Tech Stack:** Laravel + Filament + MySQL/MariaDB
**Pengguna utama:** Administrasi

---

## 1. Tujuan Produk
Membuat aplikasi internal untuk membantu pekerjaan administrasi dalam mengelola dan merekap klaim operasional. Aplikasi ditujukan untuk mengurangi pekerjaan manual seperti:
*   Menulis data klaim berulang kali.
*   Menghitung total klaim secara manual.
*   Mencari data klaim lama.
*   Membuat rekap Excel secara manual.
*   Membuat dokumen pendukung secara manual.
*   Mengecek nominal klaim terhadap budget/ketentuan.

**Prinsip utama:**
Admin cukup memasukkan data transaksi, kemudian sistem membantu menghitung, menyimpan, mencari, dan menghasilkan dokumen sesuai format pekerjaan yang sudah digunakan.

## 2. Scope MVP
Untuk versi pertama, aplikasi menangani **Klaim yang aktif**:
*   Entertain
*   BBM
*   Transportasi

Ketiga jenis klaim tersebut menggunakan struktur Excel dan format Word daftar hadir yang sama. 

**Belum masuk MVP:**
*   Perjalanan Dinas
*   Service Motor

Kedua jenis tersebut akan dibuat pada versi berikutnya karena formatnya berbeda dan Perdin relatif jarang digunakan.

## 3. Gambaran Proses Bisnis
Berdasarkan contoh Excel yang diberikan, data klaim dikumpulkan berdasarkan tanggal transaksi dan kemudian direkap dalam suatu periode.

**Contoh Perhitungan:**
```text
21/08/2026 → Entertain → Rp118.000
26/08/2026 → Entertain → Rp87.000
27/08/2026 → Entertain → Rp198.000
                         ───────── +
                         Rp403.000
```

**Hasil:** `REALISASI BIAYA OPERASIONAL RGM — BULAN AGUSTUS 2026`
Akan dilengkapi informasi pemohon: Nama / Jabatan, Tanggal Pengajuan, Biaya Claim, Budget Claim, Sudah Claim, Over Budget, dan Nomor Pengajuan.

## 4. Jenis Data Klaim MVP
Untuk Entertain, BBM, dan Transportasi, struktur transaksi menggunakan field yang sama:

| Field | Keterangan |
| :--- | :--- |
| **Tanggal** | Tanggal transaksi |
| **Jenis Biaya** | Entertain / BBM / Transportasi |
| **Keperluan** | Tujuan penggunaan biaya |
| **Note** | Keterangan/detail |
| **Nominal** | Nilai klaim |
| **Brand** | Brand terkait |
| **Reffnote** | Referensi/catatan branch |
| **Kota** | Kota transaksi |

> **Contoh Data:**
> 26/08/2026 — ENTERTAIN — MEETING DENGAN ASM PURWOKERTO — WARUNG MAKAN SUMBER REJEKI SURABAYA — Rp87.000 — REALME — REALME PURWOKERTO — CILACAP.

## 5. Modul A — Dashboard
Dashboard dibuat sederhana (internal tool, bukan customer-facing).
*   **Rekap bulan berjalan:** Total Biaya Claim, Jumlah transaksi, Jumlah Entertain, Jumlah BBM, Jumlah Transportasi.
*   **Rekap terbaru:** Menampilkan beberapa pengajuan terakhir.
*   **Klaim yang perlu diperiksa:** Klaim yang melebihi budget/ketentuan.

## 6. Modul B — Data Master (Karyawan)
Admin dapat mengelola data orang yang melakukan klaim dengan fitur Tambah, Edit, Lihat, Cari, dan Aktifkan/Nonaktifkan.

| Field | Contoh |
| :--- | :--- |
| **Nama** | Hendra Setia Permana |
| **Jabatan** | RGM |
| **Status** | Aktif |

## 7. Modul C — Master Branch
Aturan Brand dan Reffnote ditentukan oleh branch, sehingga data tidak perlu diketik berulang. Ketika admin memilih branch, sistem mengisi data otomatis untuk menghindari typo, copy-paste, dan input berulang.

| Data | Contoh Pengisian |
| :--- | :--- |
| **Branch** | REALME PURWOKERTO |
| **Brand** | REALME |
| **Reffnote** | REALME PURWOKERTO |

## 8. Modul D — Input Transaksi Klaim
Modul utama untuk admin menambahkan transaksi. Tersedia form: Tanggal, Jenis Biaya (Dropdown: ENTERTAIN, BBM, TRANSPORTASI), Keperluan, Note, Nominal (Format Rupiah), Brand, Reffnote, Kota.

## 9. Modul E — Periode Rekap
Transaksi dikumpulkan untuk rekap melalui konsep periode. Admin tidak perlu membuat satu dokumen per transaksi.

```text
PERIODE (Agustus 2026)
   │
   ├── TRANSAKSI 1 (21 Agustus)
   ├── TRANSAKSI 2 (26 Agustus)
   └── TRANSAKSI 3 (27 Agustus)
```

## 10. Modul F — Perhitungan Rekap
Sistem otomatis menghitung total seluruh nominal transaksi dalam periode (Biaya Claim). Nilai ini tidak perlu diketik manual.

## 11. Budget Claim, 12. Sudah Claim, 13. Over Budget
Pada MVP, field ini disediakan namun **jangan membuat asumsi rumus** sebelum aturan perusahaan diketahui.

*   **Budget Claim:** Sumber nilai ditentukan kemudian.
*   **Sudah Claim:** Menunjukkan nominal yang diajukan sebelumnya.
*   **Over Budget:** Kondisi saat klaim melebihi budget (Contoh: Klaim 5jt, Budget 4jt, Over Budget 1jt).

## 14. Modul G — Daftar Hadir
Menghasilkan dokumen dengan format template perusahaan agar admin tidak membuat dari nol.
*   **Input Admin:** Perihal, Tanggal, Tempat, Peserta.
*   **Output:** File Word Daftar Hadir.

## 15. Hubungan Transaksi dengan Daftar Hadir
Dokumen pendukung tetap bisa ditelusuri dari transaksi.

```text
26/08/2026 - ENTERTAIN - Rp87.000
        │
        └── Daftar Hadir (Meeting dengan ASM, 26/08/2026, Warung Makan)
```

## 16. Modul H — Rekap
Tampilan setelah transaksi selesai diinput.

**Header Rekap:**
*   **Nama / Jabatan:** Hendra Setia Permana / RGM
*   **Tanggal Pengajuan:** 29 Agustus 2026
*   **Biaya Claim:** Rp403.000 (Otomatis)

| Tanggal | Jenis Biaya | Nominal | Brand | Reffnote | Kota |
| :--- | :--- | :--- | :--- | :--- | :--- |
| 21/08 | Entertain | 118.000 | REALME | REALME PURWOKERTO | Semarang |
| 26/08 | Entertain | 87.000 | REALME | REALME PURWOKERTO | Cilacap |
| 27/08 | Entertain | 198.000 | REALME | REALME PURWOKERTO | Majenang |
| **Total** | | **403.000** | | | |

## 17. Modul I & 18. Modul J — Export (Excel & Word)
*   **Excel:** Menghasilkan format mendekati file perusahaan (Header, Detail Transaksi, Grand Total). **Prinsip:** Input sekali di aplikasi ➔ Export Excel.
*   **Word:** Output daftar hadir sesuai template perusahaan (Perihal, Tanggal, Tempat, Tabel Peserta).

## 19. Modul K & 20. Status Rekap
*   **Riwayat Kolom:** Nomor Pengajuan, Periode, Nama, Jenis Klaim, Total Claim, Tanggal, Status.
*   **Pencarian:** Nama, Nomor pengajuan, Periode, Keperluan.
*   **Filter:** Bulan, Tahun, Jenis biaya.
*   **Status MVP:** DRAFT ➔ SELESAI ➔ DIAJUKAN.

## 21. Teknologi
*   **Backend:** Laravel
*   **Admin UI & Frontend:** Filament + Livewire + Tailwind bawaan Filament
*   **Database:** MySQL / MariaDB
*   **Export:** Laravel Excel / PhpSpreadsheet, PHPWord
*   **Storage:** Laravel Storage

## 22. Struktur Database MVP
```text
users           : id, name, email, password, timestamps
employees       : id, name, position, status, timestamps
branches        : id, name, brand, reffnote, status, timestamps
claim_periods   : id, month, year, employee_id, status, timestamps
claims          : id, claim_period_id, claim_date, claim_type, purpose, note, amount, branch_id, city, timestamps
attendance_docs : id, claim_id, purpose, date, place, file_path, timestamps
attendance_parts: id, attendance_document_id, name, timestamps
```

## 23. Relasi Utama & 24. Filament Resource
**Relasi Bisnis:**
```text
Employee ➔ Claim Period ➔ Claim ➔ Branch & Attendance
```
**Filament Resources:** `EmployeeResource`, `BranchResource`, `ClaimPeriodResource`, `ClaimResource` (Daftar hadir sebagai Relation Manager dari Claim).

## 25. Struktur Menu
```text
DASHBOARD
KLAIM (Buat Rekap, Transaksi Klaim, Riwayat Rekap)
MASTER DATA (Karyawan, Branch)
DOKUMEN (Template)
```

## 26. Task Development

| Epic | Task ID | Deskripsi |
| :--- | :--- | :--- |
| **1. Setup** | TASK-001 - 004 | Setup Laravel, Filament, MySQL, Git |
| **2. Master Data** | TASK-005 - 008 | Migration, Model & Resource (Employee, Branch) |
| **3. Periode** | TASK-009 - 011 | Migration, Model, Resource & Implementasi periode |
| **4. Transaksi** | TASK-012 - 016 | Claim struktur, Jenis (Entertain/BBM/Transport), Rupiah, Auto Total |
| **5. Daftar Hadir**| TASK-017 - 020 | Struktur attendance, Participant repeater, Word template & generator |
| **6. Rekap** | TASK-021 - 025 | Recap view, Hitung Biaya, Display Budget/Sudah Claim/Over Budget |
| **7. Export** | TASK-026 - 029 | Export Excel & Word (Match layout template) |
| **8. History** | TASK-030 - 034 | Riwayat, Search, Filter (Month/Year/Type), Detail view |
| **9. Backup** | TASK-035 - 036 | Database backup & File backup |

## 27. Prioritas Pengerjaan (Sprints)
*   **Sprint 1 — Fondasi:** Laravel, Filament, MySQL, Login, Employee, Branch
*   **Sprint 2 — Klaim:** Claim Period, Claim, Entertain, BBM, Transportasi, Auto Total
*   **Sprint 3 — Dokumen:** Daftar Hadir, Word, Excel
*   **Sprint 4 — Rekap:** Riwayat, Search, Filter, Budget, Sudah Claim, Over Budget
*   **Sprint 5 — Improvement:** Dashboard, Backup, Optimasi workflow

## 28. Future Update
*   **V1.1:** Perjalanan Dinas (struktur & template khusus).
*   **V1.2:** Service Motor (menyesuaikan format sebenarnya).
*   **V1.3:** Otomatisasi (Import Excel lama, multi-generate dokumen, rekap otomatis bulanan, history perubahan, sistem approval).

## 29. Definition of Done MVP
LOGIN ➔ Pilih periode ➔ Input transaksi ➔ Pilih jenis klaim ➔ Input nominal ➔ Sistem menghitung total ➔ Buat daftar hadir ➔ Simpan ➔ Lihat rekap ➔ Export Excel ➔ Generate Word.
