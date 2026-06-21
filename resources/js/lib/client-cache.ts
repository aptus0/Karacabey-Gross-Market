"use client";

type CacheEnvelope<T> = {
  savedAt: number;
  data: T;
};

const CACHE_PREFIX = "kgm-client-cache";

function storageKey(key: string) {
  return `${CACHE_PREFIX}:${key}`;
}

function canUseStorage() {
  return typeof window !== "undefined" && typeof window.localStorage !== "undefined";
}

export function readClientCache<T>(key: string, maxAgeMs?: number): T | null {
  if (!canUseStorage()) {
    return null;
  }

  try {
    const raw = window.localStorage.getItem(storageKey(key));

    if (!raw) {
      return null;
    }

    const parsed = JSON.parse(raw) as CacheEnvelope<T>;

    if (
      !parsed
      || typeof parsed !== "object"
      || typeof parsed.savedAt !== "number"
      || !("data" in parsed)
    ) {
      window.localStorage.removeItem(storageKey(key));
      return null;
    }

    if (typeof maxAgeMs === "number" && maxAgeMs > 0 && Date.now() - parsed.savedAt > maxAgeMs) {
      return null;
    }

    return parsed.data;
  } catch {
    return null;
  }
}

export function writeClientCache<T>(key: string, data: T) {
  if (!canUseStorage()) {
    return;
  }

  try {
    const envelope: CacheEnvelope<T> = {
      savedAt: Date.now(),
      data,
    };

    window.localStorage.setItem(storageKey(key), JSON.stringify(envelope));
  } catch {
    // Ignore quota and serialization failures so runtime UX stays responsive.
  }
}

export function isOfflineLikeError(error: unknown) {
  if (typeof navigator !== "undefined" && navigator.onLine === false) {
    return true;
  }

  if (error instanceof DOMException && error.name === "AbortError") {
    return true;
  }

  if (error instanceof Error) {
    const message = error.message.toLowerCase();

    return message.includes("failed to fetch")
      || message.includes("networkerror")
      || message.includes("network request failed")
      || message.includes("network timeout")
      || message.includes("signal is aborted")
      || message.includes("aborted");
  }

  return false;
}

export async function resolveCachedResource<T>({
  cacheKey,
  fetcher,
  maxAgeMs,
  fallback,
}: {
  cacheKey: string;
  fetcher: () => Promise<T>;
  maxAgeMs?: number;
  fallback: T;
}): Promise<T> {
  try {
    const data = await fetcher();
    writeClientCache(cacheKey, data);

    return data;
  } catch (error) {
    const cached = readClientCache<T>(cacheKey, maxAgeMs);

    if (cached !== null) {
      return cached;
    }

    if (isOfflineLikeError(error)) {
      return fallback;
    }

    throw error;
  }
}
