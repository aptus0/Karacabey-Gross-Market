const blockedMediaPattern = /kgm-logo|kg-web|favicon|placeholder/i;

function stripTrailingSlash(value: string | null | undefined) {
  return value ? value.replace(/\/+$/, "") : "";
}

function normalizePath(path: string) {
  const cleaned = path.replace(/^\.?\//, "").replace(/^public\//, "");

  if (!cleaned) return null;
  if (blockedMediaPattern.test(cleaned)) return null;

  if (
    cleaned.startsWith("storage/")
    || cleaned.startsWith("uploads/")
    || cleaned.startsWith("images/")
    || cleaned.startsWith("assets/")
  ) {
    return `/${cleaned}`;
  }

  return null;
}

export function safeMediaUrl(url?: string | null): string | null {
  const cleanedUrl = url?.trim();

  if (!cleanedUrl || blockedMediaPattern.test(cleanedUrl)) {
    return null;
  }

  if (cleanedUrl.startsWith("//")) {
    return `https:${cleanedUrl}`;
  }

  if (cleanedUrl.startsWith("/")) {
    return cleanedUrl;
  }

  const normalizedPath = normalizePath(cleanedUrl);
  if (normalizedPath) {
    return normalizedPath;
  }

  try {
    const parsedUrl = new URL(cleanedUrl);

    return parsedUrl.protocol === "https:" || parsedUrl.protocol === "http:"
      ? parsedUrl.toString()
      : null;
  } catch {
    return null;
  }
}

export function productImageUrl(url?: string | null): string | null {
  const cdnOrigin = stripTrailingSlash(process.env.NEXT_PUBLIC_CDN_URL);
  const rawPath = url?.trim();
  const normalizedPath = rawPath ? normalizePath(rawPath) : null;

  if (cdnOrigin && normalizedPath) {
    return `${cdnOrigin}${normalizedPath}`;
  }

  const safeUrl = safeMediaUrl(url);

  if (safeUrl) {
    return safeUrl;
  }

  return null;
}

export function normalizeProductImageList(images: Array<string | null | undefined>) {
  const seen = new Set<string>();

  return images
    .map(productImageUrl)
    .filter((image): image is string => Boolean(image))
    .filter((image) => {
      if (seen.has(image)) return false;
      seen.add(image);
      return true;
    });
}
