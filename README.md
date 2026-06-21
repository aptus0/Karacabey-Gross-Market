# Karacabey Gross Market Web

Production e-commerce stack for Karacabey Gross Market.

## Stack

- Laravel/PHP admin and backoffice
- Next.js storefront
- Go public/mobile API and worker
- MySQL, Redis, Nginx, Cloudflare Tunnel
- Docker Compose production deployment
- Mail service and docker-mailserver integration

## Security

This repository intentionally excludes live environment files, Docker volumes,
uploaded files, generated build output, mail account password hashes, and
Cloudflare tunnel credentials.

Start from `.env.example`, fill real production secrets on the server, and keep
the real `.env` out of git.

Cloudflare tunnel credentials must be placed on the server under
`docker/cloudflared/` and must never be committed.

## Production Start

```bash
cp .env.example .env
docker compose -f docker-compose.production.yml up -d --build
docker compose -f docker-compose.production.yml ps
```

Run migrations only after taking a database backup in production.

```bash
docker exec kgm-php-admin php artisan migrate --force
```
