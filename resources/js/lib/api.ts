export const apiBaseUrl = (
  process.env.NEXT_PUBLIC_GO_API_URL
  ?? process.env.NEXT_PUBLIC_API_URL
  ?? process.env.API_URL
  ?? ""
).replace(/\/+$/, "");

const serverApiBaseUrl = (
  process.env.GO_API_INTERNAL_URL
  ?? process.env.API_INTERNAL_URL
  ?? apiBaseUrl
).replace(/\/+$/, "");

type RequestWithTimeout = RequestInit & {
  timeoutMs?: number;
};

type ApiErrorPayload = {
  message?: string;
  errors?: Record<string, string | string[]>;
  remaining_attempts?: number;
  locked?: boolean;
  retry_after?: number;
  [key: string]: unknown;
};

const CUSTOMER_UID_KEY = "kgm_customer_uid";
const SESSION_UID_KEY = "kgm_session_uid";
const CART_TOKEN_KEY = "kgm_cart_token";
const UID_MAX_AGE = 60 * 60 * 24 * 365;
const SESSION_MAX_AGE = 60 * 60 * 24 * 30;
const ACTION_TOKEN_CACHE_TTL_MS = 60_000;
const ACTION_TOKEN_CLIENT_MODE = (process.env.NEXT_PUBLIC_ACTION_TOKEN_CLIENT ?? "report").toLowerCase();
const SERVICE_BACKOFF_BASE_MS = 8_000;
const SERVICE_BACKOFF_MAX_MS = 45_000;

type ActionTokenCacheEntry = { token: string; expiresAt: number };
const actionTokenCache = new Map<string, ActionTokenCacheEntry>();
const serviceBackoff = new Map<string, { failures: number; retryAt: number }>();

export class ApiRequestError extends Error {
  status: number;
  errors?: Record<string, string | string[]>;
  payload: ApiErrorPayload | null;

  constructor(
    message: string,
    status: number,
    errors?: Record<string, string | string[]>,
    payload: ApiErrorPayload | null = null,
  ) {
    super(message);
    this.name = "ApiRequestError";
    this.status = status;
    this.errors = errors;
    this.payload = payload;
  }

  get remainingAttempts(): number | null {
    return typeof this.payload?.remaining_attempts === "number"
      ? this.payload.remaining_attempts
      : null;
  }

  get locked(): boolean {
    return Boolean(this.payload?.locked);
  }

  get retryAfter(): number | null {
    return typeof this.payload?.retry_after === "number"
      ? this.payload.retry_after
      : null;
  }
}

export function buildApiUrl(path: string) {
  if (/^https?:\/\//i.test(path)) {
    return path;
  }

  const normalizedPath = path.startsWith("/") ? path : `/${path}`;
  const baseUrl = typeof window === "undefined" ? serverApiBaseUrl : apiBaseUrl;

  if (baseUrl && normalizedPath.startsWith("/api/")) {
    return `${baseUrl}${normalizedPath}`;
  }

  return normalizedPath;
}

export function buildRequestSignal(signal: AbortSignal | null | undefined, timeoutMs = 10_000) {
  if (typeof AbortSignal === "undefined" || typeof AbortSignal.timeout !== "function") {
    return signal;
  }

  const timeoutSignal = AbortSignal.timeout(timeoutMs);

  if (!signal) {
    return timeoutSignal;
  }

  if (typeof AbortSignal.any === "function") {
    return AbortSignal.any([signal, timeoutSignal]);
  }

  return signal;
}

export async function apiRequest<T>(path: string, init: RequestWithTimeout = {}): Promise<T> {
  const { timeoutMs = 10_000, signal, ...requestInit } = init;
  const method = resolveRequestMethod(requestInit.method);
  const requestUrl = buildApiUrl(path);
  const backoffKey = serviceBackoffKey(method, requestUrl);
  assertServiceAvailable(backoffKey);
  const baseHeaders: Record<string, string> = {
    Accept: "application/json",
    ...(requestInit.body ? { "Content-Type": "application/json" } : {}),
    ...clientIdentityHeaders(),
    ...headersToRecord(requestInit.headers),
  };
  const actionToken = await maybeActionToken(path, method, baseHeaders);
  if (actionToken && !baseHeaders["X-Action-Token"]) {
    baseHeaders["X-Action-Token"] = actionToken;
  }

  const response = await fetch(requestUrl, {
    ...requestInit,
    credentials: requestInit.credentials ?? "include",
    signal: buildRequestSignal(signal, timeoutMs),
    headers: baseHeaders,
  });

  persistIdentityFromResponse(response);

  const payload = (await response.json().catch(() => null)) as
    | ({ data?: T } & ApiErrorPayload)
    | null;

  if (!response.ok) {
    rememberServiceFailure(backoffKey, response.status);
    throw new ApiRequestError(
      resolveErrorMessage(payload) ?? `İstek başarısız oldu (${response.status}).`,
      response.status,
      payload?.errors,
      payload ?? null,
    );
  }

  clearServiceFailure(backoffKey);

  return (payload?.data ?? payload) as T;
}


function resolveRequestMethod(method?: string) {
  return (method ?? "GET").toUpperCase();
}

function serviceBackoffKey(method: string, url: string) {
  try {
    const parsedUrl = new URL(url, typeof window !== "undefined" ? window.location.origin : "http://localhost");
    return `${method} ${parsedUrl.origin}${parsedUrl.pathname}`;
  } catch {
    return `${method} ${url.split("?")[0]}`;
  }
}

function assertServiceAvailable(key: string) {
  const entry = serviceBackoff.get(key);
  if (!entry || entry.retryAt <= Date.now()) return;

  const retryAfter = Math.max(1, Math.ceil((entry.retryAt - Date.now()) / 1000));
  throw new ApiRequestError(
    "Servis geçici olarak yoğun. Birazdan tekrar deneyeceğiz.",
    503,
    undefined,
    { message: "Servis geçici olarak yoğun. Birazdan tekrar deneyeceğiz.", retry_after: retryAfter },
  );
}

function rememberServiceFailure(key: string, status: number) {
  if (![502, 503, 504].includes(status)) return;

  const previous = serviceBackoff.get(key);
  const failures = Math.min((previous?.failures ?? 0) + 1, 6);
  const delay = Math.min(SERVICE_BACKOFF_BASE_MS * 2 ** (failures - 1), SERVICE_BACKOFF_MAX_MS);
  serviceBackoff.set(key, {
    failures,
    retryAt: Date.now() + delay,
  });
}

function clearServiceFailure(key: string) {
  serviceBackoff.delete(key);
}

function headersToRecord(headers?: HeadersInit): Record<string, string> {
  if (!headers) return {};
  if (headers instanceof Headers) {
    const result: Record<string, string> = {};
    headers.forEach((value, key) => {
      result[key] = value;
    });
    return result;
  }
  if (Array.isArray(headers)) {
    return Object.fromEntries(headers.map(([key, value]) => [key, value]));
  }
  return { ...headers } as Record<string, string>;
}

async function maybeActionToken(path: string, method: string, headers: Record<string, string>) {
  if (typeof window === "undefined" || ACTION_TOKEN_CLIENT_MODE === "off" || path.includes("/api/v1/security/action-token")) {
    return null;
  }
  const action = resolveActionName(path, method);
  if (!action) return null;

  const cached = actionTokenCache.get(action);
  if (cached && cached.expiresAt > Date.now() + 5_000) {
    return cached.token;
  }

  try {
    const authHeader = headers.Authorization ?? headers.authorization;
    const response = await fetch(buildApiUrl(`/api/v1/security/action-token?action=${encodeURIComponent(action)}`), {
      method: "GET",
      credentials: "include",
      signal: buildRequestSignal(null, 2_500),
      headers: {
        Accept: "application/json",
        ...clientIdentityHeaders(),
        ...(authHeader ? { Authorization: authHeader } : {}),
      },
    });
    persistIdentityFromResponse(response);
    if (!response.ok) return null;
    const payload = await response.json().catch(() => null) as { data?: { token?: string; expires_at?: number } } | null;
    const token = payload?.data?.token;
    if (!token) return null;
    const expiresAt = payload?.data?.expires_at ? payload.data.expires_at * 1000 : Date.now() + ACTION_TOKEN_CACHE_TTL_MS;
    actionTokenCache.set(action, { token, expiresAt });
    return token;
  } catch {
    return null;
  }
}

function resolveActionName(path: string, method: string) {
  const urlPath = path.replace(/^https?:\/\/[^/]+/i, "").split("?")[0] || path;
  if (method === "POST" && urlPath === "/api/v1/cart/items") return "cart.add";
  if (method === "PATCH" && /^\/api\/v1\/cart\/items\//.test(urlPath)) return "cart.update";
  if (method === "DELETE" && /^\/api\/v1\/cart\/items\//.test(urlPath)) return "cart.delete";
  if (method === "DELETE" && urlPath === "/api/v1/cart") return "cart.clear";
  if (method === "POST" && urlPath === "/api/v1/cart/coupon") return "coupon.apply";
  if (method === "DELETE" && urlPath === "/api/v1/cart/coupon") return "coupon.remove";
  if (method === "POST" && urlPath === "/api/v1/c") return "checkout.start";
  if ((method === "PUT" || method === "PATCH") && urlPath === "/api/v1/auth/profile") return "profile.update";
  if (method === "DELETE" && /^\/api\/v1\/addresses\//.test(urlPath)) return "address.delete";
  if (method === "POST" && /^\/api\/v1\/favorites\//.test(urlPath)) return "favorite.add";
  if (method === "DELETE" && /^\/api\/v1\/favorites\//.test(urlPath)) return "favorite.delete";
  if (method === "POST" && urlPath === "/api/v1/notifications/read-all") return "notification.read_all";
  if (method === "POST" && /^\/api\/v1\/notifications\/[^/]+\/read$/.test(urlPath)) return "notification.read";
  return null;
}

export function extractErrorMessage(error: unknown, fallback = "Bir hata oluştu.") {
  if (error instanceof ApiRequestError) {
    return error.message;
  }

  if (error instanceof Error && error.message) {
    return error.message;
  }

  return fallback;
}

export function clientIdentityHeaders(): Record<string, string> {
  if (typeof window === "undefined") {
    return {};
  }

  const customerUid = getOrCreateClientUid(CUSTOMER_UID_KEY, "cus");
  const sessionUid = getOrCreateClientUid(SESSION_UID_KEY, "ses");

  return {
    "X-Customer-UID": customerUid,
    "X-Session-UID": sessionUid,
    "X-Platform": "web",
  };
}

export function createClientUID(prefix: "cus" | "ses" | "chk" | "pay" | "evt" | "cart" = "evt") {
  const randomPart = typeof crypto !== "undefined" && typeof crypto.randomUUID === "function"
    ? crypto.randomUUID().replace(/-/g, "").slice(0, 22)
    : `${Date.now().toString(36)}${Math.random().toString(36).slice(2, 14)}`.slice(0, 22);

  return `${prefix}_${randomPart}`;
}

export function getStoredCustomerUID() {
  return readStorage(CUSTOMER_UID_KEY);
}

export function getStoredSessionUID() {
  return readStorage(SESSION_UID_KEY);
}

export function getStoredCartToken() {
  return readStorage(CART_TOKEN_KEY);
}

export function persistCartToken(cartToken: string | null | undefined) {
  if (!cartToken) return;
  writeStorage(CART_TOKEN_KEY, cartToken);
}

function getOrCreateClientUid(key: string, prefix: "cus" | "ses") {
  const existing = readStorage(key);
  if (existing) {
    mirrorIdentityCookie(key, existing, key === CUSTOMER_UID_KEY ? UID_MAX_AGE : SESSION_MAX_AGE);
    return existing;
  }
  const uid = createClientUID(prefix);
  writeStorage(key, uid);
  mirrorIdentityCookie(key, uid, key === CUSTOMER_UID_KEY ? UID_MAX_AGE : SESSION_MAX_AGE);
  return uid;
}

function persistIdentityFromResponse(response: Response) {
  if (typeof window === "undefined") return;

  const customerUid = response.headers.get("X-Customer-UID");
  const sessionUid = response.headers.get("X-Session-UID");
  const cartToken = response.headers.get("X-Cart-Token");

  if (customerUid) {
    writeStorage(CUSTOMER_UID_KEY, customerUid);
    mirrorIdentityCookie(CUSTOMER_UID_KEY, customerUid, UID_MAX_AGE);
  }
  if (sessionUid) {
    writeStorage(SESSION_UID_KEY, sessionUid);
    mirrorIdentityCookie(SESSION_UID_KEY, sessionUid, SESSION_MAX_AGE);
  }
  if (cartToken) {
    persistCartToken(cartToken);
  }
}

function readStorage(key: string) {
  if (typeof window === "undefined") return null;
  try {
    return window.localStorage.getItem(key);
  } catch {
    return null;
  }
}

function writeStorage(key: string, value: string) {
  if (typeof window === "undefined") return;
  try {
    window.localStorage.setItem(key, value);
  } catch {
    // ignore blocked storage
  }
}

function mirrorIdentityCookie(key: string, value: string, maxAge: number) {
  if (typeof document === "undefined") return;
  const cookieName = key === CUSTOMER_UID_KEY ? "kgm_uid" : key === SESSION_UID_KEY ? "kgm_sid" : key;
  const secure = typeof window !== "undefined" && window.location.protocol === "https:" ? "; Secure" : "";
  document.cookie = `${cookieName}=${encodeURIComponent(value)}; Path=/; Max-Age=${maxAge}; SameSite=Lax${secure}`;
}

function resolveErrorMessage(payload: ApiErrorPayload | null) {
  if (!payload) {
    return null;
  }

  const firstValidationMessage = payload.errors
    ? Object.values(payload.errors)
        .flatMap((value) => (Array.isArray(value) ? value : [value]))
        .find(Boolean)
    : null;

  const nested = payload.data;
  const dataMessage =
    nested !== null && typeof nested === "object" && !Array.isArray(nested)
      ? (nested as Record<string, unknown>).message
      : null;

  return firstValidationMessage ?? payload.message ?? (typeof dataMessage === "string" ? dataMessage : null) ?? null;
}
