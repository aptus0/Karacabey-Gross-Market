#!/bin/bash
set -euo pipefail

SOURCE=/tmp/docker-mailserver/opendkim
TARGET=/etc/opendkim

for file in KeyTable SigningTable TrustedHosts; do
  if [[ ! -s "${SOURCE}/${file}" ]]; then
    echo "Missing required DKIM configuration: ${SOURCE}/${file}" >&2
    exit 1
  fi
done

if [[ ! -d "${SOURCE}/keys" ]]; then
  echo "Missing required DKIM keys directory: ${SOURCE}/keys" >&2
  exit 1
fi

install -d -m 0700 -o opendkim -g opendkim "${TARGET}/keys"
install -m 0644 -o opendkim -g opendkim "${SOURCE}/KeyTable" "${TARGET}/KeyTable"
install -m 0644 -o opendkim -g opendkim "${SOURCE}/SigningTable" "${TARGET}/SigningTable"
install -m 0644 -o opendkim -g opendkim "${SOURCE}/TrustedHosts" "${TARGET}/TrustedHosts"
cp -R "${SOURCE}/keys/." "${TARGET}/keys/"

chown -R opendkim:opendkim "${TARGET}/keys"
find "${TARGET}/keys" -type d -exec chmod 0700 {} +
find "${TARGET}/keys" -type f -exec chmod 0600 {} +
