import type { NextConfig } from "next";
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

    const value = line.slice(key.length + 1).trim();

    return value.replace(/^['"]|['"]$/g, "");
  }

  return null;
}

function stripTrailingSlash(value: string | null | undefined) {
  return value ? value.replace(/\/+$/, "") : "";
}

function normalizeOrigin(value: string | null | undefined) {
  const origin = stripTrailingSlash(value);

  if (!origin) {
    return "";
  }

  try {
    const parsed = new URL(origin);

    if (parsed.protocol === "http:" && parsed.hostname.endsWith(".test")) {
      parsed.protocol = "https:";
    }

    return stripTrailingSlash(parsed.toString());
  } catch {
    return origin;
  }
}

function toRemotePattern(origin: string | null | undefined) {
  const normalized = normalizeOrigin(origin);

  if (!normalized) {
    return null;
  }

  try {
    const parsed = new URL(normalized);

    return {
      protocol: parsed.protocol.replace(":", "") as "http" | "https",
      hostname: parsed.hostname,
      port: parsed.port || undefined,
    };
  } catch {
    return null;
  }
}

type RemotePattern = NonNullable<ReturnType<typeof toRemotePattern>>;

function resolveApiOrigin() {
  const internalCandidate = process.env.INTERNAL_API_URL
    ?? process.env.API_INTERNAL_URL
    ?? readLaravelEnvValue("API_INTERNAL_URL")
    ?? "";
  const publicCandidate = process.env.API_URL
    ?? readLaravelEnvValue("API_URL")
    ?? "http://127.0.0.1:8000";
  const normalizedInternal = normalizeOrigin(internalCandidate);

  if (!normalizedInternal) {
    return publicCandidate;
  }

  try {
    const host = new URL(normalizedInternal).hostname.toLowerCase();
    const dockerOnlyHosts = new Set(["web", "app", "php", "backend", "api"]);

    if (dockerOnlyHosts.has(host)) {
      return publicCandidate;
    }
  } catch {
    return publicCandidate;
  }

  return normalizedInternal;
}

const internalApiOrigin = resolveApiOrigin();

const publicApiOrigin = process.env.NEXT_PUBLIC_API_URL
  ?? process.env.API_URL
  ?? readLaravelEnvValue("API_URL")
  ?? "https://api.karacabeygrossmarket.com";

const storefrontOrigin = process.env.NEXT_PUBLIC_SITE_URL
  ?? process.env.STOREFRONT_URL
  ?? readLaravelEnvValue("STOREFRONT_URL")
  ?? process.env.FRONTEND_URL
  ?? readLaravelEnvValue("FRONTEND_URL")
  ?? "https://karacabeygrossmarket.com";

const cdnOrigin = process.env.NEXT_PUBLIC_CDN_URL
  ?? process.env.CDN_URL
  ?? readLaravelEnvValue("CDN_URL")
  ?? "https://cdn.karacabeygrossmarket.com";

const useCdnAssetPrefix = process.env.NEXT_USE_CDN_ASSET_PREFIX === "true";
const normalizedStorefrontOrigin = normalizeOrigin(storefrontOrigin);
const normalizedPublicApiOrigin = normalizeOrigin(publicApiOrigin);
const normalizedCdnOrigin = normalizeOrigin(cdnOrigin);
const isProduction = process.env.NODE_ENV === "production";

const remotePatterns: RemotePattern[] = [
  {
    protocol: "https",
    hostname: "images.unsplash.com",
    port: undefined,
  },
  {
    protocol: "https",
    hostname: "images.migrosone.com",
    port: undefined,
  },
  {
    protocol: "https",
    hostname: "**.dsmcdn.com",
    port: undefined,
  },
  {
    protocol: "https",
    hostname: "**.kaganparfumeri.com",
    port: undefined,
  },
  {
    protocol: "https",
    hostname: "**.karacabeygrossmarket.com",
    port: undefined,
  },
  {
    protocol: "https",
    hostname: "karacabeygrossmarket.com",
    port: undefined,
  },
  ...[storefrontOrigin, publicApiOrigin, cdnOrigin]
    .map(toRemotePattern)
    .filter((pattern): pattern is RemotePattern => pattern !== null),
];

const nextConfig: NextConfig = {
  output: "standalone",
  allowedDevOrigins: ["karacabeygrossmarket.com", "www.karacabeygrossmarket.com"],
  turbopack: {
    root: __dirname,
  },
  poweredByHeader: false,
  productionBrowserSourceMaps: false,
  compiler: {
    removeConsole: process.env.NODE_ENV === "production" ? { exclude: ["error", "warn"] } : false,
  },
  env: {
    NEXT_PUBLIC_SITE_URL: normalizeOrigin(storefrontOrigin),
    NEXT_PUBLIC_API_URL: normalizeOrigin(publicApiOrigin),
    NEXT_PUBLIC_CDN_URL: normalizeOrigin(cdnOrigin),
  },
  assetPrefix: process.env.NODE_ENV === "production" && useCdnAssetPrefix && cdnOrigin
    ? normalizeOrigin(cdnOrigin)
    : undefined,
  images: {
    remotePatterns,
  },
  async headers() {
    const scriptSources = [
      "'self'",
      "'unsafe-inline'",
      "https://www.googletagmanager.com",
      "https://www.google-analytics.com",
      "https://googleads.g.doubleclick.net",
      "https://connect.facebook.net",
      "https://mc.yandex.ru",
      "https://bat.bing.com",
      "https://www.clarity.ms",
      "https://analytics.tiktok.com",
      "https://static.cloudflareinsights.com",
      "https://challenges.cloudflare.com",
    ];
    const connectSources = [
      "'self'",
      "https://karacabeygrossmarket.com",
      "https://*.karacabeygrossmarket.com",
      normalizedStorefrontOrigin,
      normalizedPublicApiOrigin,
      normalizedCdnOrigin,
      "https://www.googletagmanager.com",
      "https://www.google-analytics.com",
      "https://www.google.com",
      "https://www.googleadservices.com",
      "https://googleads.g.doubleclick.net",
      "https://region1.google-analytics.com",
      "https://*.google-analytics.com",
      "https://*.analytics.google.com",
      "https://connect.facebook.net",
      "https://www.facebook.com",
      "https://graph.facebook.com",
      "https://mc.yandex.ru",
      "https://bat.bing.com",
      "https://www.clarity.ms",
      "https://*.clarity.ms",
      "https://analytics.tiktok.com",
      "https://business-api.tiktok.com",
      "https://static.cloudflareinsights.com",
      "https://*.cloudflareinsights.com",
      "https://challenges.cloudflare.com",
    ].filter(Boolean);
    const imageSources = [
      "'self'",
      "data:",
      "blob:",
      "https://images.unsplash.com",
      "https://images.migrosone.com",
      "https://*.dsmcdn.com",
      "https://*.kaganparfumeri.com",
      "https://karacabeygrossmarket.com",
      "https://*.karacabeygrossmarket.com",
      normalizedStorefrontOrigin,
      normalizedPublicApiOrigin,
      normalizedCdnOrigin,
      "https:",
    ].filter(Boolean);

    if (!isProduction) {
      scriptSources.push("'unsafe-eval'", "http://localhost:*", "http://127.0.0.1:*");
      connectSources.push("http://localhost:*", "http://127.0.0.1:*", "ws://localhost:*", "ws://127.0.0.1:*");
      imageSources.push("http://localhost:*", "http://127.0.0.1:*");
    }

    const contentSecurityPolicy = [
      "default-src 'self'",
      `script-src ${scriptSources.join(" ")}`,
      `script-src-elem ${scriptSources.join(" ")}`,
      `connect-src ${connectSources.join(" ")}`,
      `img-src ${imageSources.join(" ")}`,
      "style-src 'self' 'unsafe-inline'",
      "font-src 'self' data:",
      "frame-src 'self' https://www.google.com https://www.paytr.com https://*.paytr.com https://challenges.cloudflare.com",
      "form-action 'self' https://www.paytr.com https://*.paytr.com",
      "base-uri 'self'",
      "object-src 'none'",
      "frame-ancestors 'none'",
      ...(isProduction ? ["upgrade-insecure-requests"] : []),
    ].join("; ");
    const longLivedAssetHeaders = [
      {
        key: "Cache-Control",
        value: "public, max-age=2592000, immutable",
      },
    ];
    const privatePageHeaders = [
      {
        key: "Cache-Control",
        value: "no-store, no-cache, max-age=0, must-revalidate",
      },
    ];
    const securityHeaders = [
      {
        key: "Strict-Transport-Security",
        value: "max-age=31536000; includeSubDomains; preload",
      },
      {
        key: "X-Frame-Options",
        value: "DENY",
      },
      {
        key: "X-Content-Type-Options",
        value: "nosniff",
      },
      {
        key: "Referrer-Policy",
        value: "strict-origin-when-cross-origin",
      },
      {
        key: "Permissions-Policy",
        value: "camera=(), microphone=(), geolocation=(self), payment=(self \"https://www.paytr.com\")",
      },
      {
        key: "Content-Security-Policy",
        value: contentSecurityPolicy,
      },
      {
        key: "Cross-Origin-Opener-Policy",
        value: "same-origin",
      },
      {
        key: "Cross-Origin-Resource-Policy",
        value: "same-site",
      },
      {
        key: "Origin-Agent-Cluster",
        value: "?1",
      },
      {
        key: "X-Permitted-Cross-Domain-Policies",
        value: "none",
      },
      {
        key: "X-DNS-Prefetch-Control",
        value: "off",
      },
    ];

    return [
      {
        source: "/((?!_next/static|_next/image|favicon.ico).*)",
        headers: securityHeaders,
      },
      {
        source: "/assets/:path*",
        headers: longLivedAssetHeaders,
      },
      {
        source: "/seo/:path*",
        headers: longLivedAssetHeaders,
      },
      {
        source: "/google-merchant.xml",
        headers: [
          {
            key: "Cache-Control",
            value: "public, max-age=1800, s-maxage=3600, stale-while-revalidate=86400",
          },
        ],
      },
      {
        source: "/opensearch.xml",
        headers: [
          {
            key: "Cache-Control",
            value: "public, max-age=86400, s-maxage=86400",
          },
        ],
      },
      {
        source: "/browserconfig.xml",
        headers: [
          {
            key: "Cache-Control",
            value: "public, max-age=86400, s-maxage=86400",
          },
        ],
      },
      {
        source: "/indexnow/:path*",
        headers: [
          {
            key: "Cache-Control",
            value: "public, max-age=86400, s-maxage=86400",
          },
        ],
      },
      {
        source: "/images/:path*",
        headers: longLivedAssetHeaders,
      },
      {
        source: "/storage/:path*",
        headers: longLivedAssetHeaders,
      },
      {
        source: "/KGLogo/:path*",
        headers: longLivedAssetHeaders,
      },
      {
        source: "/api/:path*",
        headers: privatePageHeaders,
      },
      {
        source: "/cart/:path*",
        headers: privatePageHeaders,
      },
      {
        source: "/sepet/:path*",
        headers: privatePageHeaders,
      },
      {
        source: "/checkout/:path*",
        headers: privatePageHeaders,
      },
      {
        source: "/auth/:path*",
        headers: privatePageHeaders,
      },
      {
        source: "/account/:path*",
        headers: privatePageHeaders,
      },
      {
        source: "/hesabim/:path*",
        headers: privatePageHeaders,
      },
      {
        source: "/favorites/:path*",
        headers: privatePageHeaders,
      },
      {
        source: "/notifications/:path*",
        headers: privatePageHeaders,
      },
      {
        source: "/addresses/:path*",
        headers: privatePageHeaders,
      },
    ];
  },
  async redirects() {
    return [
      {
        source: "/contact",
        destination: "/iletisim",
        permanent: true,
      },
      {
        source: "/stores",
        destination: "/teslimat-bolgeleri",
        permanent: true,
      },
      {
        source: "/cargo-tracking",
        destination: "/kargo-takip",
        permanent: true,
      },
      {
        source: "/feed/google-merchant.xml",
        destination: "/google-merchant.xml",
        permanent: true,
      },
      {
        source: "/feed/facebook-catalog.xml",
        destination: "/google-merchant.xml",
        permanent: true,
      },
      {
        source: "/sarkuteri",
        destination: "/kategori/sarkuteri",
        permanent: true,
      },
      {
        source: "/kurumsal/hakkimizda",
        destination: "/hakkimizda",
        permanent: true,
      },
      {
        source: "/kurumsal/iletisim",
        destination: "/iletisim",
        permanent: true,
      },
      {
        source: "/kurumsal/kvkk",
        destination: "/kvkk",
        permanent: true,
      },
      {
        source: "/kurumsal/gizlilik-politikasi",
        destination: "/gizlilik-politikasi",
        permanent: true,
      },
      {
        source: "/kurumsal/mesafeli-satis-sozlesmesi",
        destination: "/mesafeli-satis-sozlesmesi",
        permanent: true,
      },
      {
        source: "/kurumsal/teslimat-bolgeleri",
        destination: "/teslimat-bolgeleri",
        permanent: true,
      },
      {
        source: "/kurumsal/sss",
        destination: "/sikca-sorulan-sorular",
        permanent: true,
      },
      {
        source: "/kurumsal/iade-ve-degisim",
        destination: "/iade-ve-iptal-kosullari",
        permanent: true,
      },
    ];
  },
  async rewrites() {
    return [
      {
        source: "/api/:path*",
        destination: `${normalizeOrigin(internalApiOrigin)}/api/:path*`,
      },
      {
        source: "/oauth/:path*",
        destination: `${normalizeOrigin(internalApiOrigin)}/oauth/:path*`,
      },
    ];
  },
};

export default nextConfig;
