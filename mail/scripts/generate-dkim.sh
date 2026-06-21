#!/usr/bin/env bash
set -euo pipefail
docker exec -it kgm-mailserver setup config dkim domain karacabeygrossmarket.com selector kgm2026
echo "DNS'e eklenecek DKIM kaydını görmek için:"
echo "docker exec -it kgm-mailserver cat /tmp/docker-mailserver/opendkim/keys/karacabeygrossmarket.com/kgm2026.txt"
