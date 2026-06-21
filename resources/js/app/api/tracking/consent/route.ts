import { NextRequest, NextResponse } from "next/server";
import { resolveInternalApiOrigin } from "@/lib/server-config";

export const runtime = "nodejs";
export const dynamic = "force-dynamic";

export async function POST(request: NextRequest) {
  const body = await request.text();
  const origin = resolveInternalApiOrigin();

  if (!origin) {
    return acceptedFallback("missing_upstream");
  }

  try {
    const response = await fetch(`${origin}/api/v1/tracking/consent`, {
      method: "POST",
      headers: forwardedHeaders(request),
      body,
      cache: "no-store",
    });

    if (!response.ok) {
      return acceptedFallback(`upstream_${response.status}`);
    }

    const payload = await response.json().catch(() => ({ data: { accepted: response.ok } }));
    return NextResponse.json(payload, { status: response.status });
  } catch {
    return acceptedFallback("upstream_unreachable");
  }
}

function forwardedHeaders(request: NextRequest) {
  return {
    Accept: "application/json",
    "Content-Type": request.headers.get("content-type") ?? "application/json",
    "X-Customer-UID": request.headers.get("x-customer-uid") ?? "",
    "X-Session-UID": request.headers.get("x-session-uid") ?? "",
    "X-Cart-Token": request.headers.get("x-cart-token") ?? "",
    "X-Request-ID": request.headers.get("x-request-id") ?? "",
    "User-Agent": request.headers.get("user-agent") ?? "",
    "X-Forwarded-For": request.headers.get("x-forwarded-for") ?? "",
  };
}

function acceptedFallback(reason: string) {
  return NextResponse.json(
    { data: { accepted: true, stored: false, reason } },
    { status: 202 },
  );
}
