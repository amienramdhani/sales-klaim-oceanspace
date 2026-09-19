#!/usr/bin/env bash
# ==============================================================================
# Script Auto Backup Media (storage/app/public) & Database ke Microsoft OneDrive
# Menggunakan rclone dan Cron Job
# ==============================================================================
set -uo pipefail

# 1. Direktori Project & Konfigurasi
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

export PATH="/usr/local/bin:/usr/bin:/bin:${PATH}"

# Konfigurasi Nama Remote rclone (default: onedrive)
ONEDRIVE_REMOTE="${ONEDRIVE_REMOTE:-onedrive}"
ONEDRIVE_FOLDER="${ONEDRIVE_FOLDER:-SalesKlaimBackup}"

# Folder Media Laravel
STORAGE_PUBLIC="${ROOT}/storage/app/public"

# Folder Log & Temp Staging
LOG_FILE="${ROOT}/storage/logs/backup_onedrive.log"
mkdir -p "${ROOT}/storage/logs"
TEMP_DIR="/tmp/sales_klaim_backup_$(date +%s)"
mkdir -p "$TEMP_DIR"

TIMESTAMP="$(date +'%Y%m%d_%H%M%S')"
DATE_HUMAN="$(date +'%d/%m/%Y %H:%M:%S')"
ARCHIVE_MEDIA_NAME="backup-media-${TIMESTAMP}.tar.gz"
ARCHIVE_DB_NAME="backup-db-${TIMESTAMP}.sql.gz"

log() {
    echo "[$(date +'%Y-%m-%d %H:%M:%S')] $*" | tee -a "$LOG_FILE"
}

# 2. Baca Konfigurasi .env (Database & Telegram)
DB_DATABASE=""
DB_USERNAME=""
DB_PASSWORD=""
DB_HOST="127.0.0.1"
DB_PORT="3306"
TELEGRAM_BOT_TOKEN=""
TELEGRAM_CHAT_ID=""

if [ -f "${ROOT}/.env" ]; then
    # Ambil nilai dari .env tanpa error syntax
    DB_DATABASE=$(grep -E '^DB_DATABASE=' "${ROOT}/.env" | cut -d '=' -f2- | tr -d '"' | tr -d "'" || true)
    DB_USERNAME=$(grep -E '^DB_USERNAME=' "${ROOT}/.env" | cut -d '=' -f2- | tr -d '"' | tr -d "'" || true)
    DB_PASSWORD=$(grep -E '^DB_PASSWORD=' "${ROOT}/.env" | cut -d '=' -f2- | tr -d '"' | tr -d "'" || true)
    DB_HOST=$(grep -E '^DB_HOST=' "${ROOT}/.env" | cut -d '=' -f2- | tr -d '"' | tr -d "'" || echo "127.0.0.1")
    DB_PORT=$(grep -E '^DB_PORT=' "${ROOT}/.env" | cut -d '=' -f2- | tr -d '"' | tr -d "'" || echo "3306")
    
    TELEGRAM_BOT_TOKEN=$(grep -E '^TELEGRAM_BOT_TOKEN=' "${ROOT}/.env" | cut -d '=' -f2- | tr -d '"' | tr -d "'" || true)
    TELEGRAM_CHAT_ID=$(grep -E '^(TELEGRAM_ADMIN_CHAT_ID|TELEGRAM_DEFAULT_CHAT_ID)=' "${ROOT}/.env" | grep -v '=$' | head -n 1 | cut -d '=' -f2- | tr -d '"' | tr -d "'" || true)
fi

send_telegram() {
    local message="$1"
    if [ -n "$TELEGRAM_BOT_TOKEN" ] && [ -n "$TELEGRAM_CHAT_ID" ]; then
        curl -s -X POST "https://api.telegram.org/bot${TELEGRAM_BOT_TOKEN}/sendMessage" \
            -d "chat_id=${TELEGRAM_CHAT_ID}" \
            --data-urlencode "text=${message}" \
            -d "parse_mode=HTML" > /dev/null 2>&1 || true
    fi
}

cleanup() {
    rm -rf "$TEMP_DIR"
}
trap cleanup EXIT

log "========================================================"
log "Memulai proses backup harian ke Microsoft OneDrive..."
log "Direktori sumber: ${STORAGE_PUBLIC}"

# 3. Validasi Tools (rclone)
if ! command -v rclone &> /dev/null; then
    log "ERROR: 'rclone' belum terinstall di server ini!"
    send_telegram "❌ <b>BACKUP ONEDRIVE GAGAL</b>%0APenyebab: Command <code>rclone</code> tidak ditemukan di VPS.%0ASilakan install rclone terlebih dahulu."
    exit 1
fi

# Cek apakah remote OneDrive valid
if ! rclone listremotes | grep -q "^${ONEDRIVE_REMOTE}:"; then
    log "ERROR: Remote '${ONEDRIVE_REMOTE}' belum dikonfigurasi di rclone!"
    send_telegram "❌ <b>BACKUP ONEDRIVE GAGAL</b>%0APenyebab: Remote '<code>${ONEDRIVE_REMOTE}</code>' belum terkonfigurasi di rclone.%0AJalankan <code>rclone config</code> di VPS."
    exit 1
fi

# 4. Arsipkan Media (storage/app/public)
MEDIA_SIZE="0 KB"
if [ -d "$STORAGE_PUBLIC" ]; then
    log "Mengompres folder media (storage/app/public)..."
    tar -czf "${TEMP_DIR}/${ARCHIVE_MEDIA_NAME}" -C "${ROOT}/storage/app" public 2>/dev/null || true
    if [ -f "${TEMP_DIR}/${ARCHIVE_MEDIA_NAME}" ]; then
        MEDIA_SIZE=$(du -h "${TEMP_DIR}/${ARCHIVE_MEDIA_NAME}" | cut -f1)
        log "Arsip media selesai: ${ARCHIVE_MEDIA_NAME} (${MEDIA_SIZE})"
    fi
else
    log "WARNING: Folder ${STORAGE_PUBLIC} tidak ditemukan!"
fi

# 5. Backup Database MySQL (Opsional jika mysqldump tersedia)
DB_BACKUP_SUCCESS=false
DB_SIZE="0 KB"
if command -v mysqldump &> /dev/null && [ -n "$DB_DATABASE" ] && [ -n "$DB_USERNAME" ]; then
    log "Membuat dump database: ${DB_DATABASE}..."
    MYSQL_PWD="${DB_PASSWORD}" mysqldump -h "${DB_HOST}" -P "${DB_PORT}" -u "${DB_USERNAME}" "${DB_DATABASE}" 2>/dev/null | gzip > "${TEMP_DIR}/${ARCHIVE_DB_NAME}" || true
    if [ -f "${TEMP_DIR}/${ARCHIVE_DB_NAME}" ] && [ -s "${TEMP_DIR}/${ARCHIVE_DB_NAME}" ]; then
        DB_SIZE=$(du -h "${TEMP_DIR}/${ARCHIVE_DB_NAME}" | cut -f1)
        DB_BACKUP_SUCCESS=true
        log "Dump database selesai: ${ARCHIVE_DB_NAME} (${DB_SIZE})"
    fi
fi

# 6. Upload Arsip Harian ke OneDrive
log "Mengupload arsip ke OneDrive (${ONEDRIVE_REMOTE}:${ONEDRIVE_FOLDER}/archives)..."
UPLOAD_SUCCESS=true

if [ -f "${TEMP_DIR}/${ARCHIVE_MEDIA_NAME}" ]; then
    if ! rclone copy "${TEMP_DIR}/${ARCHIVE_MEDIA_NAME}" "${ONEDRIVE_REMOTE}:${ONEDRIVE_FOLDER}/archives" --retries 3 --low-level-retries 10; then
        log "ERROR: Gagal mengupload arsip media ke OneDrive!"
        UPLOAD_SUCCESS=false
    fi
fi

if [ "$DB_BACKUP_SUCCESS" = true ]; then
    if ! rclone copy "${TEMP_DIR}/${ARCHIVE_DB_NAME}" "${ONEDRIVE_REMOTE}:${ONEDRIVE_FOLDER}/archives" --retries 3 --low-level-retries 10; then
        log "WARNING: Gagal mengupload arsip database ke OneDrive!"
    fi
fi

# 7. Sinkronisasi Live Folder Media (Mirroring agar file bisa langsung dibuka di OneDrive)
if [ -d "$STORAGE_PUBLIC" ]; then
    log "Sinkronisasi live mirror folder media ke OneDrive (${ONEDRIVE_REMOTE}:${ONEDRIVE_FOLDER}/media_live)..."
    rclone sync "$STORAGE_PUBLIC" "${ONEDRIVE_REMOTE}:${ONEDRIVE_FOLDER}/media_live" \
        --transfers 4 \
        --checkers 8 \
        --fast-list \
        --retries 3 || log "WARNING: Live sync media mengalami kendala non-fatal."
fi

# 8. Rotasi Arsip Lama di OneDrive (Hapus arsip lebih dari 30 hari)
log "Membersihkan arsip lama (> 30 hari) di OneDrive..."
rclone delete "${ONEDRIVE_REMOTE}:${ONEDRIVE_FOLDER}/archives" --min-age 30d --rmdirs 2>/dev/null || true

# 9. Laporan Selesai & Notifikasi Telegram
if [ "$UPLOAD_SUCCESS" = true ]; then
    log "✅ Backup harian ke OneDrive berhasil diselesaikan!"
    
    TELE_MSG="<b>✅ BACKUP HARIAN ONEDRIVE SUKSES</b>%0A"
    TELE_MSG="${TELE_MSG}━━━━━━━━━━━━━━━━━━━━━━%0A"
    TELE_MSG="${TELE_MSG}📅 <b>Waktu:</b> ${DATE_HUMAN}%0A"
    TELE_MSG="${TELE_MSG}☁️ <b>Tujuan:</b> OneDrive (<code>${ONEDRIVE_FOLDER}</code>)%0A"
    TELE_MSG="${TELE_MSG}📁 <b>Arsip Media:</b> ${ARCHIVE_MEDIA_NAME} (${MEDIA_SIZE})%0A"
    if [ "$DB_BACKUP_SUCCESS" = true ]; then
        TELE_MSG="${TELE_MSG}🗄️ <b>Database:</b> ${ARCHIVE_DB_NAME} (${DB_SIZE})%0A"
    fi
    TELE_MSG="${TELE_MSG}🔄 <b>Live Mirror:</b> Terupdate di folder <code>media_live</code>%0A"
    TELE_MSG="${TELE_MSG}━━━━━━━━━━━━━━━━━━━━━━%0A"
    TELE_MSG="${TELE_MSG}<i>Semua foto klaim & bukti transfer aman tersimpan di OneDrive.</i>"
    
    send_telegram "$TELE_MSG"
else
    log "❌ Backup gagal pada salah satu proses!"
    send_telegram "❌ <b>BACKUP ONEDRIVE GAGAL</b>%0A%0ATerjadi kesalahan saat mengunggah file ke OneDrive.%0ASilakan periksa file log di VPS:%0A<code>${LOG_FILE}</code>"
    exit 1
fi

log "Selesai."
