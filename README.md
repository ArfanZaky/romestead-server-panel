# V Rising Server Panel

Panel web sederhana untuk mengelola dedicated server **V Rising** di Linux menggunakan Docker, Apache/PHP, Wine, Xvfb, dan SteamCMD.

Fitur utama:

- Start, stop, restart server V Rising dari web panel
- Install/update dedicated server via SteamCMD
- Edit host settings dan game rules dari browser
- Backup dan restore save data
- Monitoring status dasar server

## Kebutuhan

- Linux server
- Docker dan Docker Compose
- Port yang terbuka:
  - `5555/tcp` untuk web panel
  - `5580/udp` untuk game traffic
  - `5581/udp` untuk query/master list
- Disk kosong beberapa GB untuk dedicated server V Rising dan save data

## Cara Install

Clone repository:

```bash
git clone https://github.com/ArfanZaky/vrising-server-panel.git
cd vrising-server-panel
```

Build dan jalankan container:

```bash
docker compose up -d --build
```

Buka panel di browser:

```text
http://IP-SERVER:5555
```

Lalu dari dashboard panel, klik tombol **Install / Update Server** untuk mengunduh dedicated server V Rising lewat SteamCMD.

Setelah instalasi selesai:

1. Buka menu **Host Settings**
2. Atur nama server, password jika perlu, max players, port, dan query port
3. Buka menu **Game Rules** jika ingin mengubah mode PvE/PvP, difficulty, clan size, loot multiplier, dan aturan lain
4. Kembali ke dashboard
5. Klik **Start Server**

## Port Default

Konfigurasi default di `docker-compose.yml`:

```yaml
ports:
  - "5555:80"       # Web panel
  - "5580:5580/udp" # Game traffic
  - "5581:5581/udp" # Query/master list
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
├── game_settings.php    # Game rules editor
├── config.php           # Path dan helper konfigurasi
├── Dockerfile           # Image PHP + Wine + SteamCMD
├── docker-compose.yml   # Service Docker
├── assets/              # CSS/assets panel
└── engine/              # Runtime data, dibuat/diisi saat berjalan
    ├── server/          # File dedicated server dari SteamCMD
    ├── savedata/        # Save game dan settings runtime
    └── backups/         # Backup save data
```

Catatan: folder `engine/server`, `engine/savedata`, dan `engine/backups` tidak disimpan di Git karena ukurannya besar dan berisi data runtime. Folder tersebut akan dibuat otomatis oleh container/panel.

## Backup dan Restore

Backup dapat dibuat dari dashboard panel. File backup tersimpan di:

```text
engine/backups/
```

Untuk restore, gunakan fitur restore/upload di panel. Disarankan stop server sebelum restore save data agar file tidak berubah saat proses berlangsung.

## Update Dedicated Server

Gunakan tombol **Install / Update Server** di dashboard. Panel akan menjalankan SteamCMD untuk app dedicated server V Rising dan menampilkan log proses instalasi.

## Troubleshooting

Jika server gagal start:

- Pastikan instalasi dedicated server sudah selesai dan `VRisingServer.exe` ada di `engine/server/`
- Pastikan port UDP sudah dibuka di firewall/provider VPS
- Cek log container:

```bash
docker compose logs -f
```

- Cek log runtime server di dalam container:

```bash
docker exec -it vrising_server bash
cat /tmp/vrising_server.log
```

Jika panel tidak bisa diakses:

```bash
docker compose ps
docker compose logs -f vrising
```

## Catatan Keamanan

Panel ini sebaiknya tidak langsung dibuka publik tanpa proteksi tambahan. Jika digunakan di internet, pasang reverse proxy dengan autentikasi, VPN, atau firewall allowlist IP.
