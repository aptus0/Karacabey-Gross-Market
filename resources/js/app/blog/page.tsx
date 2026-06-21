import Link from "next/link";
import type { Metadata } from "next";
import { ArrowRight, FileText } from "lucide-react";
import { SeoHead } from "@/app/_components/SeoHead";
import { GuestLayout } from "@/app/_layouts/GuestLayout";
import { blogPosts } from "@/lib/blog";
import {
  breadcrumbSchema,
  buildMetadata,
  itemListSchema,
  jsonLdGraph,
  siteUrl,
  webPageSchema,
} from "@/lib/seo";

export const metadata: Metadata = buildMetadata({
  title: "Blog",
  description: "Karacabey Gross Market blog: market alışverişi, kargo, ödeme, adres ve kurumsal sipariş rehberleri.",
  path: "/blog",
  keywords: ["blog", "market rehberi", "kargo rehberi", "ödeme güvenliği", "Karacabey online market"],
});

export default function BlogIndexPage() {
  const breadcrumbItems = [
    { href: "/", label: "Ana Sayfa" },
    { href: "/blog", label: "Blog" },
  ];
  const blogSchema = jsonLdGraph([
    webPageSchema({
      title: "Blog",
      description: "Karacabey Gross Market blog: market alışverişi, kargo, ödeme, adres ve kurumsal sipariş rehberleri.",
      path: "/blog",
      type: "CollectionPage",
      breadcrumbs: breadcrumbItems,
    }),
    breadcrumbSchema(breadcrumbItems),
    {
      "@type": "Blog",
      name: "Karacabey Gross Market Blog",
      url: `${siteUrl}/blog`,
      inLanguage: "tr-TR",
      blogPost: blogPosts.map((post) => ({
        "@type": "BlogPosting",
        headline: post.title,
        description: post.excerpt,
        datePublished: post.publishedAt,
        articleSection: post.category,
        url: `${siteUrl}/blog/${post.slug}`,
      })),
    },
    itemListSchema({
      name: "Market Rehberleri",
      description: "Karacabey Gross Market blog yazıları.",
      path: "/blog",
      items: blogPosts.map((post) => ({
        name: post.title,
        url: `/blog/${post.slug}`,
      })),
    }),
  ]);
  const categories = Array.from(new Set(blogPosts.map((post) => post.category))).slice(0, 8);

  return (
    <GuestLayout>
      <SeoHead data={blogSchema} />
      <main className="kgm-page-shell kgm-page-shell--small">
        <section className="kgm-blog-head">
          <div className="kgm-section-title kgm-section-title--compact">
            <span><FileText size={15} /> Blog</span>
            <h1>Market rehberleri</h1>
          </div>
          <div className="kgm-blog-chips">
            {categories.map((category) => <span key={category}>{category}</span>)}
          </div>
        </section>

        <section className="kgm-blog-grid">
          {blogPosts.map((post) => (
            <article key={post.slug} className="kgm-blog-card">
              <div>
                <span>{post.category}</span>
                <h2>{post.title}</h2>
                <p>{post.excerpt}</p>
              </div>
              <footer>
                <small>{post.readTime}</small>
                <Link href={`/blog/${post.slug}`}>Oku <ArrowRight size={14} /></Link>
              </footer>
            </article>
          ))}
        </section>
      </main>
    </GuestLayout>
  );
}
