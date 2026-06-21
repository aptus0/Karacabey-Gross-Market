#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

gofmt -w cmd internal

go test ./...

go vet ./...

go build -trimpath -ldflags="-s -w" -o bin/kgm-api ./cmd/api

go build -trimpath -ldflags="-s -w" -o bin/kgm-worker ./cmd/worker
