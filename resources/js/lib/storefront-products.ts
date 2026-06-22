import "server-only";

import {
  filterProducts as fallbackFilterProducts,
  findProduct as fallbackFindProduct,
  type KgmCategory,
  type KgmProduct,
} from "@/lib/catalog";
import { productImageUrl } from "@/lib/media";
import { resolveInternalApiOrigin } from "@/lib/server-config";

type NextFetchInit = RequestInit & {
  next?: {
    revalidate?: number;
  };
};

type StorefrontCategory = {
  id: number;
  name: string;
  slug: string;
};

type StorefrontProductResponse = {
  id: number;
  name: string;
  slug: string;
  description?: string | null;
  brand?: string | null;
  barcode?: string | null;
  price_cents: number;
  price: string;
  compare_at_price_cents?: number | null;
  stock_quantity: number;
  unit_name?: string | null;
  image_url?: string | null;
  seo?: Record<string, unknown> | null;
  updated_at?: string | null;
  categories?: StorefrontCategory[];
};

type ProductIndexResponse = {
  data?: StorefrontProductResponse[];
  total?: number;
  per_page?: number;
  current_page?: number;
  last_page?: number;
  from?: number | null;
  to?: number | null;
};

type CategoryIndexResponse = {
  data?: Array<{
    id: number;
    name: string;
    slug: string;
    description?: string | null;
    image_url?: string | null;
    product_count?: number;
    children?: Array<{
      id: number;
      name: string;
      slug: string;
      description?: string | null;
      image_url?: string | null;
      product_count?: number;
    }>;
  }>;
};

function stripTrailingSlash(value: string | null | undefined) {
  return value ? value.replace(/\/+$/, "") : "";
}

function buildServerApiUrl(path: string) {
  const origin = stripTrailingSlash(resolveInternalApiOrigin());

  return `${origin}${path.startsWith("/") ? path : `/${path}`}`;
}

const productIndexRevalidateSeconds = Number.parseInt(
  process.env.PRODUCTS_REVALIDATE_SECONDS ?? process.env.NEXT_PUBLIC_PRODUCTS_REVALIDATE_SECONDS ?? "120",
  10,
);
const productDetailRevalidateSeconds = Number.parseInt(
  process.env.PRODUCT_DETAIL_REVALIDATE_SECONDS ?? process.env.NEXT_PUBLIC_PRODUCT_DETAIL_REVALIDATE_SECONDS ?? "180",
  10,
);
const categoryRevalidateSeconds = Number.parseInt(
  process.env.CATEGORIES_REVALIDATE_SECONDS ?? process.env.NEXT_PUBLIC_CATEGORIES_REVALIDATE_SECONDS ?? "300",
  10,
);

function safeRevalidate(seconds: number, fallback: number) {
  return Number.isFinite(seconds) && seconds > 0 ? Math.floor(seconds) : fallback;
}

function toStorefrontProduct(product: StorefrontProductResponse): KgmProduct {
  const primaryCategory = product.categories?.[0];
  const imageUrl = productImageUrl(product.image_url);
  const sku = getSeoString(product.seo, "erkur_kod") ?? getSeoString(product.seo, "sku");
  const barcode = getSeoString(product.seo, "gtin13") ?? product.barcode?.trim() ?? undefined;
  const hasCompareAtPrice = Boolean(
    product.compare_at_price_cents && product.compare_at_price_cents > product.price_cents,
  );

  return {
    id: product.id,
    slug: product.slug,
    name: product.name,
    brand: product.brand?.trim() || "Karacabey Gross Market",
    sku,
    barcode,
    price: product.price_cents / 100,
    oldPrice: hasCompareAtPrice ? product.compare_at_price_cents! / 100 : undefined,
    unit: product.unit_name?.trim() || "adet",
    stock: product.stock_quantity,
    image: imageUrl ?? "",
    gallery: imageUrl ? [imageUrl] : [],
    badge: hasCompareAtPrice
      ? "Avantaj"
      : product.stock_quantity > 0
        ? primaryCategory?.name ?? "Stokta"
        : "Tükendi",
    description: product.description?.trim() || `${product.name} için güncel ürün bilgisi.`,
    seo: product.seo ?? undefined,
    updatedAt: product.updated_at ?? null,
    category: primaryCategory?.slug ?? "genel",
    categoryName: primaryCategory?.name ?? "Genel",
  };
}

function getSeoString(seo: StorefrontProductResponse["seo"], key: string): string | undefined {
  const value = seo?.[key];

  return typeof value === "string" && value.trim() ? value.trim() : undefined;
}

function toStorefrontCategory(category: NonNullable<CategoryIndexResponse["data"]>[number]): KgmCategory {
  return {
    slug: category.slug,
    name: category.name,
    count: typeof category.product_count === "number" ? category.product_count : undefined,
    description: category.description ?? null,
    imageUrl: category.image_url ?? null,
    children: (category.children ?? []).map((child) => ({
      slug: child.slug,
      name: child.name,
      count: typeof child.product_count === "number" ? child.product_count : undefined,
      description: child.description ?? null,
      imageUrl: child.image_url ?? null,
    })),
  };
}

export async function fetchStorefrontProducts(options?: {
  category?: string;
  query?: string;
  perPage?: number;
  page?: number;
}) {
  const category = options?.category?.trim();
  const query = options?.query?.trim();
  const perPage = options?.perPage ?? 12;
  const page = options?.page && options.page > 0 ? Math.floor(options.page) : 1;
  const params = new URLSearchParams();

  if (category) {
    params.set("category", category);
  }

  if (query) {
    params.set("q", query);
  }

  params.set("per_page", String(perPage));
  params.set("page", String(page));

  try {
    const response = await fetch(buildServerApiUrl(`/api/v1/products?${params.toString()}`), {
      headers: {
        Accept: "application/json",
      },
      next: { revalidate: safeRevalidate(productIndexRevalidateSeconds, 120) },
    } as NextFetchInit);

    if (!response.ok) {
      throw new Error(`Products request failed with ${response.status}.`);
    }

    const payload = (await response.json()) as ProductIndexResponse;
    const products = (payload.data ?? []).map(toStorefrontProduct);

    return {
      products,
      total: typeof payload.total === "number" ? payload.total : products.length,
      perPage: typeof payload.per_page === "number" ? payload.per_page : perPage,
      currentPage: typeof payload.current_page === "number" ? payload.current_page : page,
      lastPage: typeof payload.last_page === "number" ? payload.last_page : 1,
      from: typeof payload.from === "number" ? payload.from : products.length > 0 ? (page - 1) * perPage + 1 : 0,
      to: typeof payload.to === "number" ? payload.to : (page - 1) * perPage + products.length,
    };
  } catch {
    const fallbackProducts = fallbackFilterProducts(category, query);
    const total = fallbackProducts.length;
    const start = (page - 1) * perPage;
    const visibleProducts = fallbackProducts.slice(start, start + perPage);

    return {
      products: visibleProducts,
      total,
      perPage,
      currentPage: page,
      lastPage: Math.max(Math.ceil(total / perPage), 1),
      from: visibleProducts.length > 0 ? start + 1 : 0,
      to: visibleProducts.length > 0 ? start + visibleProducts.length : 0,
    };
  }
}

export async function fetchStorefrontCategories(): Promise<KgmCategory[]> {
  try {
    const response = await fetch(buildServerApiUrl("/api/v1/categories"), {
      headers: { Accept: "application/json" },
      next: { revalidate: safeRevalidate(categoryRevalidateSeconds, 300) },
    } as NextFetchInit);

    if (!response.ok) {
      throw new Error(`Categories request failed with ${response.status}.`);
    }

    const payload = (await response.json()) as CategoryIndexResponse;

    return (payload.data ?? []).map(toStorefrontCategory);
  } catch {
    return [];
  }
}

export async function fetchFeaturedStorefrontProducts(limit = 8) {
  const { products } = await fetchStorefrontProducts({ perPage: limit });

  return products;
}

export async function fetchStorefrontProduct(slug: string) {
  const normalizedSlug = slug.trim();

  try {
    const response = await fetch(buildServerApiUrl(`/api/v1/products/${encodeURIComponent(normalizedSlug)}`), {
      headers: {
        Accept: "application/json",
      },
      next: { revalidate: safeRevalidate(productDetailRevalidateSeconds, 180) },
    } as NextFetchInit);

    if (!response.ok) {
      throw new Error(`Product request failed with ${response.status}.`);
    }

    const payload = (await response.json()) as { data?: StorefrontProductResponse };

    if (payload.data) {
      return toStorefrontProduct(payload.data);
    }
  } catch {
    // Fall through to the index lookup below. This keeps newly-synced Merchant
    // feed URLs available even if the detail endpoint has a stale negative cache.
  }

  try {
    const params = new URLSearchParams({
      q: normalizedSlug,
      per_page: "12",
      page: "1",
    });
    const response = await fetch(buildServerApiUrl(`/api/v1/products?${params.toString()}`), {
      headers: {
        Accept: "application/json",
      },
      next: { revalidate: safeRevalidate(productIndexRevalidateSeconds, 120) },
    } as NextFetchInit);

    if (response.ok) {
      const payload = (await response.json()) as ProductIndexResponse;
      const exactMatch = (payload.data ?? []).find((product) => product.slug === normalizedSlug);

      if (exactMatch) {
        return toStorefrontProduct(exactMatch);
      }
    }
  } catch {
    // Fall back to bundled fixtures for local/dev resilience.
  }

  return fallbackFindProduct(normalizedSlug) ?? null;
}
