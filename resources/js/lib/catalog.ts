export type KgmProduct = {
  id?: number;
  slug: string;
  name: string;
  brand: string;
  sku?: string;
  barcode?: string;
  price: number;
  oldPrice?: number;
  unit: string;
  stock: number;
  image: string;
  gallery?: string[];
  badge: string;
  description: string;
  seo?: Record<string, unknown>;
  updatedAt?: string | null;
  category: string;
  categoryName?: string;
};

export type KgmCategory = {
  slug: string;
  name: string;
  count?: number;
  description?: string | null;
  imageUrl?: string | null;
  children?: KgmCategory[];
};

export type KgmCartItem = KgmProduct & {
  quantity: number;
};

export const categories: KgmCategory[] = [];

export const products: KgmProduct[] = [];

export function formatPrice(value: number) {
  return new Intl.NumberFormat("tr-TR", {
    style: "currency",
    currency: "TRY",
  }).format(value);
}

export function findProduct(slug: string) {
  return products.find((product) => product.slug === slug);
}

export function filterProducts(category?: string | string[], query?: string | string[]) {
  const categoryValue = Array.isArray(category) ? category[0] : category;
  const queryValue = Array.isArray(query) ? query[0] : query;
  const normalizedQuery = queryValue?.toLocaleLowerCase("tr-TR").trim();

  return products.filter((product) => {
    const matchesCategory = categoryValue ? product.category === categoryValue : true;
    const matchesQuery = normalizedQuery
      ? [product.name, product.brand, product.description].some((value) =>
          value.toLocaleLowerCase("tr-TR").includes(normalizedQuery),
        )
      : true;

    return matchesCategory && matchesQuery;
  });
}
