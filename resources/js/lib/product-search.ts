import { liteClient as algoliasearch } from "algoliasearch/lite";
import { buildApiUrl } from "@/lib/api";
import { formatPrice, products } from "@/lib/catalog";
import { readClientCache, writeClientCache } from "@/lib/client-cache";

export type ProductSuggestion = {
  id?: number | string;
  name: string;
  slug: string;
  brand?: string | null;
  price: string;
  image_url?: string | null;
  category?: string | null;
};

type SuggestResponse = {
  data?: ProductSuggestion[];
};

type AlgoliaProductHit = {
  objectID: string;
  name?: string;
  slug?: string;
  brand?: string | null;
  price?: string;
  price_cents?: number;
  image_url?: string | null;
  category?: string | null;
};

const algoliaAppId = process.env.NEXT_PUBLIC_ALGOLIA_APP_ID;
const algoliaSearchKey = process.env.NEXT_PUBLIC_ALGOLIA_SEARCH_KEY;
const algoliaProductsIndex = process.env.NEXT_PUBLIC_ALGOLIA_PRODUCTS_INDEX;
const suggestionCacheTtlMs = 5 * 60 * 1000;
const suggestionTimeoutMs = 3_500;

export async function fetchProductSuggestions(query: string, signal?: AbortSignal): Promise<ProductSuggestion[]> {
  const normalizedQuery = query.trim();

  if (normalizedQuery.length < 2) {
    return [];
  }

  const cacheKey = `suggestions:${normalizedQuery.toLocaleLowerCase("tr-TR")}`;
  const cached = readClientCache<ProductSuggestion[]>(cacheKey, suggestionCacheTtlMs);

  if (cached?.length) {
    void refreshProductSuggestions(cacheKey, normalizedQuery);
    return cached;
  }

  const suggestions = await requestProductSuggestions(normalizedQuery, signal);
  writeClientCache(cacheKey, suggestions);

  return suggestions;
}

export function localProductSuggestions(query: string): ProductSuggestion[] {
  const normalizedQuery = query.toLocaleLowerCase("tr-TR").trim();

  if (normalizedQuery.length < 2) {
    return [];
  }

  return products
    .filter((product) =>
      [product.name, product.brand, product.category, product.description].some((value) =>
        value.toLocaleLowerCase("tr-TR").includes(normalizedQuery),
      ),
    )
    .slice(0, 6)
    .map((product, index) => ({
      id: index + 1,
      name: product.name,
      slug: product.slug,
      brand: product.brand,
      price: formatPrice(product.price),
      image_url: product.image,
      category: product.category,
    }));
}

async function refreshProductSuggestions(cacheKey: string, query: string) {
  try {
    const suggestions = await requestProductSuggestions(query);
    writeClientCache(cacheKey, suggestions);
  } catch {
    // Cached suggestions are still safe to show.
  }
}

async function requestProductSuggestions(query: string, signal?: AbortSignal) {
  if (algoliaAppId && algoliaSearchKey && algoliaProductsIndex) {
    return fetchAlgoliaSuggestions(query);
  }

  const params = new URLSearchParams({ q: query });
  const response = await fetch(buildApiUrl(`/api/v1/products/suggest?${params.toString()}`), {
    headers: {
      Accept: "application/json",
    },
    signal: buildSuggestionSignal(signal),
  });

  if (!response.ok) {
    throw new Error("Search failed.");
  }

  const payload = (await response.json()) as SuggestResponse;

  return payload.data?.slice(0, 6) ?? [];
}

function buildSuggestionSignal(signal?: AbortSignal) {
  if (typeof AbortSignal === "undefined" || typeof AbortSignal.timeout !== "function") {
    return signal;
  }

  const timeoutSignal = AbortSignal.timeout(suggestionTimeoutMs);

  if (!signal) return timeoutSignal;

  return typeof AbortSignal.any === "function" ? AbortSignal.any([signal, timeoutSignal]) : signal;
}

async function fetchAlgoliaSuggestions(query: string): Promise<ProductSuggestion[]> {
  const client = algoliasearch(algoliaAppId!, algoliaSearchKey!);
  const response = await client.searchForHits<AlgoliaProductHit>({
    requests: [
      {
        indexName: algoliaProductsIndex!,
        query,
        hitsPerPage: 6,
      },
    ],
  });
  const result = response.results[0];

  return result.hits
    .filter((hit) => hit.name && hit.slug)
    .map((hit) => ({
      id: hit.objectID,
      name: hit.name!,
      slug: hit.slug!,
      brand: hit.brand,
      price: hit.price ?? (typeof hit.price_cents === "number" ? formatPrice(hit.price_cents / 100) : ""),
      image_url: hit.image_url,
      category: hit.category,
    }));
}
