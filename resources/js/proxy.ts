import { NextResponse, type NextRequest } from "next/server";

const SENSITIVE_QUERY_KEYS = new Set([
  "password",
  "pass",
  "pwd",
  "phone",
  "email",
  "token",
  "secret",
  "access_token",
  "refresh_token",
]);


let maintenanceCache: { active: boolean; expiresAt: number } | null = null;

function isMaintenanceBypassPath(pathname: string) {
  return pathname.startsWith("/bakim")
    || pathname.startsWith("/api/")
    || pathname.startsWith("/_next/")
    || pathname.startsWith("/assets/")
    || pathname.startsWith("/seo/")
    || pathname.startsWith("/images/")
    || pathname.startsWith("/KGLogo/")
    || pathname === "/favicon.ico"
    || pathname === "/robots.txt"
    || pathname === "/sitemap.xml"
    || pathname === "/manifest.webmanifest";
}

async function storefrontMaintenanceActive(request: NextRequest) {
  const now = Date.now();
  if (maintenanceCache && maintenanceCache.expiresAt > now) {
    return maintenanceCache.active;
  }

  const apiOrigin = (process.env.NEXT_PUBLIC_MAINTENANCE_STATUS_URL ?? process.env.NEXT_PUBLIC_PANEL_URL ?? process.env.PANEL_URL ?? "https://panel.karacabeygrossmarket.com").replace(/\/+$/, "");
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), 650);

  try {
    const response = await fetch(`${apiOrigin}/api/v1/system/status`, {
      headers: { Accept: "application/json", "X-Tenant": "karacabey-gross-market" },
      cache: "no-store",
      signal: controller.signal,
    });
    if (!response.ok) throw new Error("status_failed");
    const payload = await response.json() as { data?: { maintenance?: { active?: boolean; channels?: { storefront?: boolean } } } };
    const maintenance = payload.data?.maintenance;
    const active = Boolean(maintenance?.active && maintenance?.channels?.storefront);
    maintenanceCache = { active, expiresAt: now + 15_000 };
    return active;
  } catch {
    maintenanceCache = { active: false, expiresAt: now + 5_000 };
    return false;
  } finally {
    clearTimeout(timeout);
  }
}

const MAIN_HOSTNAME = process.env.NEXT_PUBLIC_SITE_URL
  ? (() => {
      try { return new URL(process.env.NEXT_PUBLIC_SITE_URL).hostname; } catch { return "karacabeygrossmarket.com"; }
    })()
  : "karacabeygrossmarket.com";

function getSubdomain(hostname: string): string | null {
  const bare = hostname.split(":")[0].toLowerCase();

  if (bare === MAIN_HOSTNAME || bare === "localhost" || bare === "127.0.0.1") {
    return null;
  }

  if (bare.endsWith(`.${MAIN_HOSTNAME}`)) {
    return bare.slice(0, bare.length - MAIN_HOSTNAME.length - 1);
  }

  const parts = bare.split(".");

  return parts.length > 2 ? parts[0] : null;
}

export async function proxy(request: NextRequest) {
  const hostname = request.headers.get("host") ?? "";
  const subdomain = getSubdomain(hostname);

  if (subdomain === "api" && !request.nextUrl.pathname.startsWith("/api-portal")) {
    const portalUrl = request.nextUrl.clone();
    portalUrl.pathname = "/api-portal";
    portalUrl.search = "";
    return NextResponse.rewrite(portalUrl);
  }

  if (subdomain === "cdn" && !request.nextUrl.pathname.startsWith("/cdn-portal")) {
    const portalUrl = request.nextUrl.clone();
    portalUrl.pathname = "/cdn-portal";
    portalUrl.search = "";
    return NextResponse.rewrite(portalUrl);
  }

  const url = request.nextUrl;

  if (!isMaintenanceBypassPath(url.pathname) && await storefrontMaintenanceActive(request)) {
    const maintenanceUrl = url.clone();
    maintenanceUrl.pathname = "/bakim";
    maintenanceUrl.search = "";
    return NextResponse.rewrite(maintenanceUrl);
  }

  const lowerPath = url.pathname.toLowerCase();
  const hasSensitiveQuery = Array.from(url.searchParams.keys()).some((key) =>
    SENSITIVE_QUERY_KEYS.has(key.toLowerCase()),
  );

  if (hasSensitiveQuery || (lowerPath.startsWith("/auth/") && url.search)) {
    const cleanUrl = url.clone();
    cleanUrl.search = "";

    const response = NextResponse.redirect(cleanUrl, 307);
    response.headers.set("Cache-Control", "no-store, no-cache, max-age=0, must-revalidate");
    response.headers.set("Referrer-Policy", "no-referrer");

    return response;
  }

  return NextResponse.next();
}

export const config = {
  matcher: [
    "/((?!_next/static|_next/image|favicon\\.ico|assets/|seo/|images/|KGLogo/).*)",
  ],
};
