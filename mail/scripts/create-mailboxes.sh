#!/usr/bin/env bash
set -euo pipefail

echo "Karacabey Gross Market mailbox creation"
echo "Set strong passwords when prompted. They will be stored hashed in docker-mailserver config."

docker exec -it kgm-mailserver setup email add destek@karacabeygrossmarket.com
docker exec -it kgm-mailserver setup email add siparis@karacabeygrossmarket.com
docker exec -it kgm-mailserver setup email add noreply@karacabeygrossmarket.com
docker exec -it kgm-mailserver setup alias add postmaster@karacabeygrossmarket.com destek@karacabeygrossmarket.com
docker exec -it kgm-mailserver setup alias add abuse@karacabeygrossmarket.com destek@karacabeygrossmarket.com

echo "DKIM key üretmek için: docker exec -it kgm-mailserver setup config dkim domain karacabeygrossmarket.com selector kgm2026"
