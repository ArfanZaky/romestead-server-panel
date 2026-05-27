# Romestead Server Panel

A lightweight web panel for managing a **Romestead Dedicated Server** on Linux with Docker, Apache/PHP, the .NET 8 runtime, and SteamCMD.

## Features

- Start, stop, and restart the Romestead server from a web panel
- Install or update the dedicated server through SteamCMD
- Edit host settings and raw server config from the browser
- Create, upload, restore, and delete save backups
- Daily automatic backups with 3-day retention
- Basic server status and resource monitoring
- Capped log viewer to avoid browser freezes from large server logs

## Requirements

- Linux server or VPS
- Docker and Docker Compose
- Open ports:
  - `5555/tcp` for the web panel
  - `5580/tcp` and `5580/udp` for game traffic
- Several GB of free disk space for the dedicated server files, saves, and backups

## Installation

Clone the repository:

```bash
git clone https://github.com/ArfanZaky/romestead-server-panel.git
cd romestead-server-panel
```

Build and start the container:

```bash
docker compose up -d --build
```

Open the panel in your browser:

```text
http://YOUR-SERVER-IP:5555
```

From the dashboard, click **Install / Update Server** to download the Romestead Dedicated Server through SteamCMD.

After the installation finishes:

1. Open **Host Settings**.
2. Set the world name, password if needed, max players, world size, seed, and game port.
3. Open **Server Config** if you need to edit the raw `config.json`.
4. Return to the dashboard.
5. Click **Start Server**.

## Platform Support

This panel is designed for **Linux server deployment through Docker**.

Supported usage:

- Linux VPS/server: supported and recommended
- Windows with Docker Desktop/WSL2: usable for development and testing
- Native Windows without Docker: not supported by this panel

The Romestead dedicated server files are not included in this repository. They are downloaded at runtime through SteamCMD.

## Default Ports

Default `docker-compose.yml` port mapping:

```yaml
ports:
  - "5555:80"       # Web panel
  - "5580:5580/tcp" # Game traffic
  - "5580:5580/udp" # Game traffic
```

If you change the game port from the panel, update the Docker port mapping too, then restart the container:

```bash
docker compose down
docker compose up -d
```

## Directory Layout

```text
.
├── api.php              # Panel backend actions
├── index.php            # Dashboard
├── settings.php         # Host settings editor
├── game_settings.php    # Raw server config editor
├── config.php           # Paths and helper functions
├── Dockerfile           # PHP + .NET runtime + SteamCMD image
├── docker-compose.yml   # Docker service definition
├── docker-entrypoint.sh # Starts cron and Apache
├── scripts/             # Scheduled maintenance scripts
├── assets/              # CSS/assets
└── engine/              # Runtime data, created while running
    ├── server/          # Dedicated server files from SteamCMD
    │   └── saved_worlds/ # Romestead world saves
    ├── savedata/        # Legacy/compatibility runtime directory
    └── backups/         # Save backups
```

Runtime folders such as `engine/server`, `engine/savedata`, `engine/backups`, and `engine/Steam` are ignored by Git because they contain generated data, saves, backups, and large SteamCMD files.

## Backups and Restore

Manual backups can be created from the dashboard.

Automatic backups run every day at `03:00 UTC`, use the filename format `daily_backup_YYYY-MM-DD_HHMMSS.tar.gz`, and automatically delete daily backups older than 3 days.

Backups are stored in:

```text
engine/backups/
```

For restore operations, use the restore or upload flow in the panel. Stop the server before restoring save data so files do not change during extraction.

## Updating the Dedicated Server

Use **Install / Update Server** from the dashboard. The panel runs SteamCMD for the Romestead Dedicated Server App ID and shows the installation log.

## Troubleshooting

If the server does not start:

- Make sure the dedicated server installation finished and `Server.dll` exists in `engine/server/`.
- Make sure the TCP/UDP game ports are open in your firewall or VPS provider panel.
- Check the container logs:

```bash
docker compose logs -f
```

- Check the runtime server log inside the container:

```bash
docker exec -it romestead_server bash
cat /tmp/romestead_server.log
```

If the panel is not reachable:

```bash
docker compose ps
docker compose logs -f romestead
```

## Security Notes

This panel does not include authentication. Do not expose it directly to the public internet without extra protection.

Recommended options:

- Put it behind a reverse proxy with authentication.
- Use a VPN.
- Restrict access by firewall or IP allowlist.
