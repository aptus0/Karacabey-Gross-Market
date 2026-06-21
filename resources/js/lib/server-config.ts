import "server-only";

import { existsSync, readFileSync } from "node:fs";
import { resolve } from "node:path";

function readLaravelEnvValue(key: string) {
  const envPath = resolve(process.cwd(), "..", "..", ".env");

  if (!existsSync(envPath)) {
    return null;
  }

  const file = readFileSync(envPath, "utf8");

  for (const rawLine of file.split(/\r?\n/)) {
    const line = rawLine.trim();

    if (!line || line.startsWith("#") || !line.startsWith(`${key}=`)) {
      continue;
    }

    return line.slice(key.length + 1).trim().replace(/^['"]|['"]$/g, "");
  }

  return null;
}

function stripTrailingSlash(value: string | null | undefined) {
  return value ? value.replace(/\/+$/, "") : "";
}

function resolveApiOrigin() {
  const internalCandidate = process.env.GO_API_INTERNAL_URL
    ?? process.env.INTERNAL_API_URL
    ?? process.env.API_INTERNAL_URL
    ?? readLaravelEnvValue("API_INTERNAL_URL")
    ?? "";
  const publicCandidate = process.env.API_URL
    ?? readLaravelEnvValue("API_URL")
    ?? "http://127.0.0.1:8080";
  const normalizedInternal = stripTrailingSlash(internalCandidate);

  if (!normalizedInternal) {
    return stripTrailingSlash(publicCandidate);
  }

  try {
    const host = new URL(normalizedInternal).hostname.toLowerCase();
    const dockerOnlyHosts = new Set(["web", "app", "php", "backend", "api"]);

    if (dockerOnlyHosts.has(host)) {
      return stripTrailingSlash(publicCandidate);
    }
  } catch {
    return stripTrailingSlash(publicCandidate);
  }

  return normalizedInternal;
}

export function resolveInternalApiOrigin() {
  return resolveApiOrigin();
}

export function resolveSiteUrl() {
  return stripTrailingSlash(
    process.env.NEXT_PUBLIC_SITE_URL
      ?? process.env.STOREFRONT_URL
      ?? readLaravelEnvValue("STOREFRONT_URL")
      ?? process.env.FRONTEND_URL
      ?? readLaravelEnvValue("FRONTEND_URL")
      ?? "https://karacabeygrossmarket.com",
  );
}

export function resolveCdnUrl() {
  return stripTrailingSlash(
    process.env.NEXT_PUBLIC_CDN_URL
      ?? process.env.CDN_URL
      ?? readLaravelEnvValue("CDN_URL")
      ?? "",
  );
}
