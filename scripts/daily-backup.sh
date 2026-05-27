#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

php -r '
$_GET["action"] = "daily_backup";
$_SERVER["REQUEST_METHOD"] = "GET";
require "api.php";
'
