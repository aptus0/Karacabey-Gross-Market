import type { Metadata } from "next";
import Link from "next/link";
import { Grid3X3, Search } from "lucide-react";

import { SeoHead } from "@/app/_components/SeoHead";
import { GuestLayout } from "@/app/_layouts/GuestLayout";
import { fetchStorefrontCategories } from "@/lib/storefront-products";
import {
  breadcrumbSchema,
  buildMetadata,
  categoryListSchema,
  jsonLdGraph,
  webPageSchema,
} from "@/lib/seo";

export const revalidate = 120;

export const metadata: Metadata = buildMetadata({
  title: "Reyonlar",
  description: "Karacabey Gross Market reyonları ve ürün kategorileri.",
  path: "/kategoriler",
  keywords: ["reyonlar", "market kategorileri", "karacabey market"],
});

export default async function CategoriesPage() {
  const categories = await fetchStorefrontCategories();
  const breadcrumbItems = [
    { href: "/", label: "Ana Sayfa" },
    { href: "/kategoriler", label: "Reyonlar" },
  ];
  const jsonLd = jsonLdGraph([
    webPageSchema({
      title: "Reyonlar",
      description: "Karacabey Gross Market reyonları ve ürün kategorileri.",
      path: "/kategoriler",
      type: "CollectionPage",
      breadcrumbs: breadcrumbItems,
    }),
    breadcrumbSchema(breadcrumbItems),
    categoryListSchema(categories),
  ]);

  return (
    <GuestLayout>
      <SeoHead data={jsonLd} />
      <main className="kgm-category-list-page">
        <section className="kgm-category-list-head">
          <div>
            <h1>Reyonlar</h1>
          </div>
          <Link href="/products" className="kgm-category-list-head__search">
            <Search size={16} />
            Ürün ara
          </Link>
        </section>

        {categories.length === 0 ? (
          <section className="kgm-category-list-empty">
            <Grid3X3 size={24} />
            <p>Şu anda kategori bilgisi alınamadı.</p>
            <Link href="/products">Ürünleri Gör</Link>
          </section>
        ) : (
          <section className="kgm-category-list-grid" aria-label="Kategori listesi">
            {categories.map((category) => (
              <article key={category.slug} className="kgm-category-list-card">
                <Link href={`/kategori/${category.slug}`}>
                  <span className="kgm-category-list-card__icon">
                    <Grid3X3 size={18} />
                  </span>
                  <span>
                    <strong>{category.name}</strong>
                    {category.count ? <small>{category.count} ürün</small> : null}
                  </span>
                </Link>

                {category.children?.length ? (
                  <div className="kgm-category-list-card__children">
                    {category.children.slice(0, 6).map((child) => (
                      <Link
                        key={child.slug}
                        href={`/kategori/${child.slug}`}
                      >
                        {child.name}
                      </Link>
                    ))}
                  </div>
                ) : null}
              </article>
            ))}
          </section>
        )}
      </main>
    </GuestLayout>
  );
}
