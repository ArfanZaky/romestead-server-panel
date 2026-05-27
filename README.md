# Romestead Server Panel

Panel web sederhana untuk mengelola dedicated server **Romestead** di Linux menggunakan Docker, Apache/PHP, .NET 8 runtime, dan SteamCMD.

Fitur utama:

- Start, stop, restart server Romestead dari web panel
- Install/update dedicated server via SteamCMD
- Edit host settings dan server config dari browser
- Backup dan restore save data
- Backup otomatis harian dengan retensi 3 hari
- Monitoring status dasar server

## Kebutuhan

- Linux server
- Docker dan Docker Compose
- Port yang terbuka:
  - `5555/tcp` untuk web panel
  - `5580/tcp` dan `5580/udp` untuk game traffic
- Disk kosong beberapa GB untuk dedicated server Romestead dan save data

## Cara Install

Clone repository:

```bash
git clone https://github.com/ArfanZaky/romestead-server-panel.git
cd romestead-server-panel
```

Build dan jalankan container:

```bash
docker compose up -d --build
```

Buka panel di browser:

```text
http://IP-SERVER:5555
```

Lalu dari dashboard panel, klik tombol **Install / Update Server** untuk mengunduh dedicated server Romestead lewat SteamCMD.

Setelah instalasi selesai:

1. Buka menu **Host Settings**
2. Atur world name, password jika perlu, max players, dan port
3. Buka menu **Server Config** jika ingin mengubah JSON konfigurasi tambahan
4. Kembali ke dashboard
5. Klik **Start Server**

## Platform Support

Panel ini ditujukan untuk **Linux server** lewat Docker. Host Windows tetap bisa dipakai untuk development atau menjalankan Docker Desktop, tetapi dedicated server Romestead di panel ini dijalankan di dalam container Linux dengan .NET runtime dan SteamCMD.

Ringkasnya:

- Linux VPS/server: supported dan direkomendasikan
- Windows + Docker Desktop/WSL2: bisa untuk development/testing
- Windows native tanpa Docker: belum didukung oleh panel ini

## Port Default

Konfigurasi default di `docker-compose.yml`:

```yaml
ports:
  - "5555:80"       # Web panel
  - "5580:5580/tcp" # Game traffic
  - "5580:5580/udp" # Game traffic
```

Jika port game diubah dari panel, sesuaikan juga mapping port di `docker-compose.yml` lalu restart container:

```bash
docker compose down
docker compose up -d
```

## Struktur Folder

```text
.
├── api.php              # Backend action panel
├── index.php            # Dashboard
├── settings.php         # Host settings editor
├── game_settings.php    # Server config editor
├── config.php           # Path dan helper konfigurasi
├── Dockerfile           # Image PHP + .NET runtime + SteamCMD
├── docker-compose.yml   # Service Docker
├── assets/              # CSS/assets panel
└── engine/              # Runtime data, dibuat/diisi saat berjalan
    ├── server/          # File dedicated server dari SteamCMD
    │   └── saved_worlds/ # Save world Romestead
    ├── savedata/        # Folder kompatibilitas/legacy panel
    └── backups/         # Backup save data
```

Catatan: folder `engine/server`, `engine/savedata`, dan `engine/backups` tidak disimpan di Git karena ukurannya besar dan berisi data runtime. Folder tersebut akan dibuat otomatis oleh container/panel.

## Backup dan Restore

Backup manual dapat dibuat dari dashboard panel. Backup otomatis berjalan setiap hari pukul `03:00 UTC`, memakai nama `daily_backup_YYYY-MM-DD_HHMMSS.tar.gz`, dan otomatis menghapus daily backup yang lebih tua dari 3 hari.

File backup tersimpan di:

```text
engine/backups/
```

Untuk restore, gunakan fitur restore/upload di panel. Disarankan stop server sebelum restore save data agar file tidak berubah saat proses berlangsung.

## Update Dedicated Server

Gunakan tombol **Install / Update Server** di dashboard. Panel akan menjalankan SteamCMD untuk app dedicated server Romestead dan menampilkan log proses instalasi.

## Troubleshooting

Jika server gagal start:

- Pastikan instalasi dedicated server sudah selesai dan `Server.dll` ada di `engine/server/`
- Pastikan port TCP/UDP sudah dibuka di firewall/provider VPS
- Cek log container:

```bash
docker compose logs -f
```

- Cek log runtime server di dalam container:

```bash
docker exec -it romestead_server bash
cat /tmp/romestead_server.log
```

Jika panel tidak bisa diakses:

```bash
docker compose ps
docker compose logs -f romestead
```

## Catatan Keamanan

Panel ini sebaiknya tidak langsung dibuka publik tanpa proteksi tambahan. Jika digunakan di internet, pasang reverse proxy dengan autentikasi, VPN, atau firewall allowlist IP.
