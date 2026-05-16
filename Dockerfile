FROM php:8.2-apache

# Bypass persetujuan instalasi SteamCMD
RUN echo steam steam/question select "I AGREE" | debconf-set-selections \
    && echo steam steam/license note '' | debconf-set-selections

# Aktifkan 32-bit, install semua dependencies dalam 1 layer untuk hemat disk
# Termasuk: wine, xvfb, xauth, lib32gcc, steamcmd dependencies
RUN dpkg --add-architecture i386 \
    && apt-get update \
    && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
       wine wine64 wine32 xvfb xauth wget curl procps ca-certificates lib32gcc-s1 \
    && rm -rf /var/lib/apt/lists/* \
    && rm -rf /usr/share/doc /usr/share/man /usr/share/locale \
    && apt-get clean

# Buat symlink wine jika belum ada (beberapa distro hanya punya wine64)
RUN if [ ! -f /usr/bin/wine ] && [ -f /usr/bin/wine64 ]; then \
        ln -s /usr/bin/wine64 /usr/bin/wine; \
    fi

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
