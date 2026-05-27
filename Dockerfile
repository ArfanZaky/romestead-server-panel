FROM php:8.2-apache

# Aktifkan 32-bit untuk SteamCMD, plus .NET 8 runtime dependencies untuk Romestead.
RUN dpkg --add-architecture i386 \
    && apt-get update \
    && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
       bash wget curl procps ca-certificates cron lib32gcc-s1 libicu76 \
    && rm -rf /var/lib/apt/lists/* \
    && rm -rf /usr/share/doc /usr/share/man /usr/share/locale \
    && apt-get clean

# Install .NET runtime yang dibutuhkan Server.dll.
RUN curl -sSL https://dot.net/v1/dotnet-install.sh -o /tmp/dotnet-install.sh \
    && bash /tmp/dotnet-install.sh --runtime dotnet --version 8.0.22 --install-dir /opt/dotnet \
    && rm /tmp/dotnet-install.sh

ENV DOTNET_ROOT=/opt/dotnet
ENV PATH="${PATH}:/opt/dotnet"

# Install SteamCMD secara manual (lebih reliable daripada apt)
RUN mkdir -p /usr/games \
    && cd /usr/games \
    && wget -q https://steamcdn-a.akamaihd.net/client/installer/steamcmd_linux.tar.gz \
    && tar -xzf steamcmd_linux.tar.gz \
    && rm steamcmd_linux.tar.gz \
    && chmod +x /usr/games/steamcmd.sh \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Set ownership SteamCMD agar www-data bisa menjalankannya tanpa error permission
RUN chown -R www-data:www-data /usr/games

# Buat folder yang dibutuhkan dan set permission
RUN mkdir -p /var/www/html/engine/server \
    && mkdir -p /var/www/html/engine/savedata/Settings \
    && mkdir -p /var/www/html/engine/backups \
    && mkdir -p /GameAnalytics \
    && chmod -R 0777 /GameAnalytics \
    && chown -R www-data:www-data /var/www/html/engine

# Jalankan SteamCMD sekali saat build agar dia update dirinya sendiri
# Ini mencegah error "Missing configuration" saat pertama kali install game
RUN su -s /bin/bash www-data -c 'HOME=/var/www/html/engine /usr/games/steamcmd.sh +quit' || true

# Naikkan PHP upload limit untuk restore savegame besar (max 512MB)
RUN echo "upload_max_filesize = 512M" > /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 520M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "max_input_time = 300" >> /usr/local/etc/php/conf.d/uploads.ini

# Pasang permission agar user web server setara dengan user utama server
RUN usermod -u 1000 www-data

# Backup harian jam 03:00 UTC, dengan retensi otomatis 3 hari untuk daily backup.
RUN printf '0 3 * * * www-data bash /var/www/html/scripts/daily-backup.sh >> /tmp/romestead_daily_backup.log 2>&1\n' > /etc/cron.d/romestead-daily-backup \
    && chmod 0644 /etc/cron.d/romestead-daily-backup

COPY docker-entrypoint.sh /usr/local/bin/romestead-entrypoint
RUN chmod +x /usr/local/bin/romestead-entrypoint

CMD ["romestead-entrypoint"]
