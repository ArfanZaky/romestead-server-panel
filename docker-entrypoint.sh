#!/usr/bin/env bash
set -euo pipefail

if command -v cron >/dev/null 2>&1; then
    cron
fi

exec apache2-foreground
