import type { Metadata } from "next";
import Link from "next/link";
import { ArrowUpDown, Grid3X3, Search, SlidersHorizontal } from "lucide-react";
import { ProductGrid } from "@/app/_components/ProductGrid";
import { SearchBar } from "@/app/_components/SearchBar";
import { SeoHead } from "@/app/_components/SeoHead";
import { GuestLayout } from "@/app/_layouts/GuestLayout";
import {
  breadcrumbSchema,
  buildMetadata,
  jsonLdGraph,
  productItemListSchema,
  webPageSchema,
} from "@/lib/seo";
import {
  fetchStorefrontCategories,
  fetchStorefrontProducts,
} from "@/lib/storefront-products";

export const revalidate = 120;

type ProductsPageProps = {
  searchParams: Promise<{
    category?: string;
    page?: string;
    q?: string;
  }>;
};

export async function generateMetadata({ searchParams }: ProductsPageProps): Promise<Metadata> {
  const params = await searchParams;
  const categories = await fetchStorefrontCategories();
  const activeCategory = categories
    .flatMap((category) => [category, ...(category.children ?? [])])
    .find((category) => category.slug === params.category);
  const title = activeCategory ? `${activeCategory.name} Ürünleri` : "Ürünler";
  const description = activeCategory?.description
    ? `${activeCategory.description} Karacabey Gross Market üzerinden online sipariş ver.`
    : "Karacabey Gross Market ürün kataloğu, kategori filtreleri ve hızlı online alışveriş akışı.";

  return buildMetadata({
    title,
    description,
    path: "/products",
    keywords: [
      "ürünler",
      "ürün kataloğu",
      "market kategorileri",
      "online alışveriş",
      activeCategory?.name ?? "",
    ].filter(Boolean),
    robots: params.q || params.page ? { index: false, follow: true } : undefined,
  });
}

export default async function ProductsPage({ searchParams }: ProductsPageProps) {
  const params = await searchParams;
  const currentPage = Math.max(1, Number.parseInt(params.page ?? "1", 10) || 1);

  const [{
    products: selectedProducts,
    total,
    currentPage: resolvedPage,
    lastPage,
    from,
    to,
  }, categories] = await Promise.all([
    fetchStorefrontProducts({
      category: params.category,
      page: currentPage,
      query: params.q,
      perPage: 48,
    }),
    fetchStorefrontCategories(),
  ]);

  const activeCategory = categories
    .flatMap((category) => [category, ...(category.children ?? [])])
    .find((category) => category.slug === params.category);
  const pageWindowStart = Math.max(1, resolvedPage - 2);
  const pageWindowEnd = Math.min(lastPage, resolvedPage + 2);
  const visiblePages = Array.from(
    { length: Math.max(pageWindowEnd - pageWindowStart + 1, 0) },
    (_, index) => pageWindowStart + index,
  );

  const buildProductsHref = (page: number) => {
    const nextParams = new URLSearchParams();

    if (params.category) {
      nextParams.set("category", params.category);
    }

    if (params.q) {
      nextParams.set("q", params.q);
    }

    if (page > 1) {
      nextParams.set("page", String(page));
    }

    const queryString = nextParams.toString();

    return queryString ? `/products?${queryString}` : "/products";
  };

  const pageTitle = activeCategory ? `${activeCategory.name} Ürünleri` : "Karacabey Gross Market Ürünler";
  const pageDescription = activeCategory?.description
    ? `${activeCategory.description} Karacabey Gross Market ürün sayfası.`
    : "Karacabey Gross Market ürünleri ve hızlı online sipariş akışı.";
  const breadcrumbItems = [
    { href: "/", label: "Ana Sayfa" },
    { href: "/products", label: "Ürünler" },
    ...(activeCategory ? [{ href: `/kategori/${activeCategory.slug}`, label: activeCategory.name }] : []),
  ];
  const jsonLd = jsonLdGraph([
    webPageSchema({
      title: pageTitle,
      description: pageDescription,
      path: "/products",
      type: "CollectionPage",
      breadcrumbs: breadcrumbItems,
    }),
    breadcrumbSchema(breadcrumbItems),
    productItemListSchema({
      name: pageTitle,
      description: pageDescription,
      path: "/products",
      products: selectedProducts,
    }),
  ]);

  return (
    <GuestLayout>
      <SeoHead data={jsonLd} />
      <main className="catalog-page catalog-page--phase7">

        {/* ── Compact catalog header ─────────────────────── */}
        <section className="catalog-compact-head" aria-label="Ürün kataloğu başlığı">
          <div>
            <p className="catalog-compact-head__eyebrow">Ürünler</p>
            <h1>{activeCategory ? activeCategory.name : "Tüm Ürünler"}</h1>
          </div>
          <div className="catalog-compact-head__search">
            <Search size={16} aria-hidden="true" />
            <SearchBar />
          </div>
        </section>

        {/* ── Category chips ────────────────────────────── */}
        {categories.length > 0 && (
          <section className="catalog-filter-panel catalog-filter-panel--phase7" aria-label="Kategori filtreleri">
            <div className="catalog-filter-panel__head">
              <div>
                <span className="catalog-filter-panel__label">Kategoriler</span>
                <strong>{activeCategory ? activeCategory.name : "Tüm reyonlar"}</strong>
              </div>
              <span className="catalog-filter-panel__meta">{categories.length} kategori</span>
            </div>
            <div className="catalog-chips">
              <Link
                href="/products"
                className={`catalog-chip${!params.category ? " catalog-chip--active" : ""}`}
              >
                <Grid3X3 size={14} /> Tümü
              </Link>
              {categories.map((cat) => (
                <Link
                  key={cat.slug}
                  href={`/kategori/${cat.slug}`}
                  className={`catalog-chip${params.category === cat.slug ? " catalog-chip--active" : ""}`}
                >
                  {cat.name}
                </Link>
              ))}
            </div>
          </section>
        )}

        {/* ── Toolbar ───────────────────────────────────── */}
        <div className="catalog-mobile-filter-summary" aria-label="Mobil ürün özeti">
          <span><SlidersHorizontal size={15} /> {activeCategory ? activeCategory.name : "Tüm reyonlar"}</span>
          <span><ArrowUpDown size={15} /> Sıralama</span>
        </div>

        <div className="catalog-toolbar catalog-toolbar--phase7">
          <span
            className="catalog-toolbar__count"
            data-nosnippet
            translate="no"
          >
            <SlidersHorizontal size={15} />
            {from > 0 && to > 0 ? (
              <>
                <span aria-hidden="true">{from}</span>
                <span aria-hidden="true">–</span>
                <span aria-hidden="true">{to}</span>
                <span aria-hidden="true"> / </span>
                <span aria-hidden="true">{total}</span>
              </>
            ) : (
              <span aria-hidden="true">{total}</span>
            )}{" "}
            ürün
          </span>
          <div className="catalog-toolbar__actions">
            {params.category && (
              <Link className="catalog-chip" href="/products">
                Filtreyi Kaldır
              </Link>
            )}
            <Link className="secondary-action" href="/sepet">
              Sepete Git
            </Link>
          </div>
        </div>

        {/* ── Product grid ──────────────────────────────── */}
        {selectedProducts.length === 0 ? (
          <div className="catalog-empty">
            <p>Bu kategoride ürün bulunamadı.</p>
            <Link className="primary-action" href="/products">
              Tüm ürünleri gör
            </Link>
          </div>
        ) : (
          <ProductGrid products={selectedProducts} />
        )}

        {lastPage > 1 && (
          <nav className="catalog-pagination" aria-label="Ürün sayfalama">
            <Link
              href={buildProductsHref(Math.max(resolvedPage - 1, 1))}
              aria-disabled={resolvedPage <= 1}
              className={`catalog-page-link catalog-page-link--text${resolvedPage <= 1 ? " catalog-page-link--disabled" : ""}`}
            >
              Önceki
            </Link>

            {pageWindowStart > 1 && (
              <>
                <Link href={buildProductsHref(1)} className="catalog-page-link">
                  1
                </Link>
                {pageWindowStart > 2 && <span className="catalog-pagination__ellipsis">…</span>}
              </>
            )}

            {visiblePages.map((page) => (
              <Link
                key={page}
                href={buildProductsHref(page)}
                aria-current={page === resolvedPage ? "page" : undefined}
                className={`catalog-page-link${page === resolvedPage ? " catalog-page-link--active" : ""}`}
              >
                {page}
              </Link>
            ))}

            {pageWindowEnd < lastPage && (
              <>
                {pageWindowEnd < lastPage - 1 && <span className="catalog-pagination__ellipsis">…</span>}
                <Link href={buildProductsHref(lastPage)} className="catalog-page-link">
                  {lastPage}
                </Link>
              </>
            )}

            <Link
              href={buildProductsHref(Math.min(resolvedPage + 1, lastPage))}
              aria-disabled={resolvedPage >= lastPage}
              className={`catalog-page-link catalog-page-link--text${resolvedPage >= lastPage ? " catalog-page-link--disabled" : ""}`}
            >
              Sonraki
            </Link>
          </nav>
        )}

      </main>
    </GuestLayout>
  );
}
