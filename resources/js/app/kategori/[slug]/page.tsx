import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { Grid3X3, Search, SlidersHorizontal } from "lucide-react";
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

type CategorySlugPageProps = {
  params: Promise<{ slug: string }>;
  searchParams: Promise<{
    page?: string;
  }>;
};

async function resolveCategory(slug: string) {
  const categories = await fetchStorefrontCategories();
  const flatCategories = categories.flatMap((category) => [
    category,
    ...(category.children ?? []),
  ]);

  return {
    categories,
    category: flatCategories.find((item) => item.slug === slug) ?? null,
  };
}

export async function generateMetadata({ params }: CategorySlugPageProps): Promise<Metadata> {
  const { slug } = await params;
  const { category } = await resolveCategory(slug);

  if (!category) {
    return {
      title: "Kategori Bulunamadı",
      robots: { index: false, follow: true },
    };
  }

  return buildMetadata({
    title: `${category.name} Ürünleri`,
    description: category.description
      ? `${category.description} Karacabey Gross Market ${category.name} reyonunda online sipariş.`
      : `${category.name} ürünleri, güncel fiyatlar ve hızlı online market alışverişi Karacabey Gross Market'te.`,
    path: `/kategori/${category.slug}`,
    keywords: [
      category.name,
      `${category.name} ürünleri`,
      "Karacabey market",
      "online market",
      "Bursa market",
    ],
  });
}

export default async function CategorySlugPage({ params, searchParams }: CategorySlugPageProps) {
  const { slug } = await params;
  const queryParams = await searchParams;
  const currentPage = Math.max(1, Number.parseInt(queryParams.page ?? "1", 10) || 1);
  const { categories, category } = await resolveCategory(slug);

  if (!category) {
    notFound();
  }

  const {
    products,
    total,
    currentPage: resolvedPage,
    lastPage,
    from,
    to,
  } = await fetchStorefrontProducts({
    category: category.slug,
    page: currentPage,
    perPage: 48,
  });
  const pageWindowStart = Math.max(1, resolvedPage - 2);
  const pageWindowEnd = Math.min(lastPage, resolvedPage + 2);
  const visiblePages = Array.from(
    { length: Math.max(pageWindowEnd - pageWindowStart + 1, 0) },
    (_, index) => pageWindowStart + index,
  );
  const buildCategoryHref = (page: number) => {
    const nextPage = Math.max(1, Math.min(page, lastPage));
    return nextPage > 1 ? `/kategori/${category.slug}?page=${nextPage}` : `/kategori/${category.slug}`;
  };
  const breadcrumbItems = [
    { href: "/", label: "Ana Sayfa" },
    { href: "/kategoriler", label: "Reyonlar" },
    { href: `/kategori/${category.slug}`, label: category.name },
  ];
  const jsonLd = jsonLdGraph([
    webPageSchema({
      title: `${category.name} Ürünleri`,
      description: category.description ?? `${category.name} ürünleri Karacabey Gross Market kataloğu.`,
      path: `/kategori/${category.slug}`,
      type: "CollectionPage",
      breadcrumbs: breadcrumbItems,
    }),
    breadcrumbSchema(breadcrumbItems),
    productItemListSchema({
      name: `${category.name} Ürünleri`,
      description: `${category.name} reyonundaki ürünler.`,
      path: `/kategori/${category.slug}`,
      products,
    }),
  ]);

  return (
    <GuestLayout>
      <SeoHead data={jsonLd} />
      <main className="catalog-page catalog-page--phase7">
        <section className="catalog-compact-head" aria-label={`${category.name} kategorisi`}>
          <div>
            <p className="catalog-compact-head__eyebrow">Reyon</p>
            <h1>{category.name}</h1>
            {category.description ? <p>{category.description}</p> : null}
          </div>
          <div className="catalog-compact-head__search">
            <Search size={16} aria-hidden="true" />
            <SearchBar />
          </div>
        </section>

        {categories.length > 0 ? (
          <section className="catalog-filter-panel catalog-filter-panel--phase7" aria-label="Kategori bağlantıları">
            <div className="catalog-filter-panel__head">
              <div>
                <span className="catalog-filter-panel__label">Kategoriler</span>
                <strong>{category.name}</strong>
              </div>
              <span className="catalog-filter-panel__meta">{categories.length} kategori</span>
            </div>
            <div className="catalog-chips">
              <Link href="/products" className="catalog-chip">
                <Grid3X3 size={14} /> Tümü
              </Link>
              {categories.map((cat) => (
                <Link
                  key={cat.slug}
                  href={`/kategori/${cat.slug}`}
                  className={`catalog-chip${category.slug === cat.slug ? " catalog-chip--active" : ""}`}
                >
                  {cat.name}
                </Link>
              ))}
            </div>
          </section>
        ) : null}

        <div className="catalog-toolbar catalog-toolbar--phase7">
          <span className="catalog-toolbar__count" data-nosnippet translate="no">
            <SlidersHorizontal size={15} />
            {from > 0 && to > 0 ? `${from}-${to} / ${total}` : total} ürün
          </span>
          <div className="catalog-toolbar__actions">
            <Link className="catalog-chip" href="/kategoriler">
              Reyonlar
            </Link>
            <Link className="secondary-action" href="/sepet">
              Sepete Git
            </Link>
          </div>
        </div>

        {products.length === 0 ? (
          <div className="catalog-empty">
            <p>Bu kategoride ürün bulunamadı.</p>
            <Link className="primary-action" href="/products">
              Tüm ürünleri gör
            </Link>
          </div>
        ) : (
          <ProductGrid products={products} />
        )}

        {lastPage > 1 ? (
          <nav className="catalog-pagination catalog-pagination--wide" aria-label="Ürün sayfalama">
            <Link
              href={buildCategoryHref(resolvedPage - 1)}
              aria-disabled={resolvedPage <= 1}
              className={`catalog-page-link catalog-page-link--text${resolvedPage <= 1 ? " catalog-page-link--disabled" : ""}`}
            >
              Önceki
            </Link>

            {pageWindowStart > 1 ? (
              <>
                <Link href={buildCategoryHref(1)} className="catalog-page-link">
                  1
                </Link>
                {pageWindowStart > 2 ? <span className="catalog-pagination__ellipsis">…</span> : null}
              </>
            ) : null}

            {visiblePages.map((page) => (
              <Link
                key={page}
                href={buildCategoryHref(page)}
                aria-current={page === resolvedPage ? "page" : undefined}
                className={`catalog-page-link${page === resolvedPage ? " catalog-page-link--active" : ""}`}
              >
                {page}
              </Link>
            ))}

            {pageWindowEnd < lastPage ? (
              <>
                {pageWindowEnd < lastPage - 1 ? <span className="catalog-pagination__ellipsis">…</span> : null}
                <Link href={buildCategoryHref(lastPage)} className="catalog-page-link">
                  {lastPage}
                </Link>
              </>
            ) : null}

            <Link
              href={buildCategoryHref(resolvedPage + 1)}
              aria-disabled={resolvedPage >= lastPage}
              className={`catalog-page-link catalog-page-link--text${resolvedPage >= lastPage ? " catalog-page-link--disabled" : ""}`}
            >
              Sonraki
            </Link>
          </nav>
        ) : null}
      </main>
    </GuestLayout>
  );
}
