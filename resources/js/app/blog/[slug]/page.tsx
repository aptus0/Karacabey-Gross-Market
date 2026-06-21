import Link from "next/link";
import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { ArrowLeft } from "lucide-react";
import { SeoHead } from "@/app/_components/SeoHead";
import { GuestLayout } from "@/app/_layouts/GuestLayout";
import { blogPosts, findBlogPost } from "@/lib/blog";
import {
  absoluteImageUrl,
  breadcrumbSchema,
  buildMetadata,
  jsonLdGraph,
  siteUrl,
  webPageSchema,
} from "@/lib/seo";

type BlogDetailPageProps = {
  params: Promise<{
    slug: string;
  }>;
};

export function generateStaticParams() {
  return blogPosts.map((post) => ({ slug: post.slug }));
}

export async function generateMetadata({ params }: BlogDetailPageProps): Promise<Metadata> {
  const { slug } = await params;
  const post = findBlogPost(slug);

  if (!post) return {};

  return buildMetadata({
    title: post.seo.title,
    description: post.seo.description,
    path: `/blog/${post.slug}`,
    image: post.heroImage,
    type: "article",
    keywords: [...post.seo.keywords, post.category],
  });
}

export default async function BlogDetailPage({ params }: BlogDetailPageProps) {
  const { slug } = await params;
  const post = findBlogPost(slug);

  if (!post) notFound();

  const breadcrumbItems = [
    { href: "/", label: "Ana Sayfa" },
    { href: "/blog", label: "Blog" },
    { href: `/blog/${post.slug}`, label: post.title },
  ];
  const articleSchema = jsonLdGraph([
    webPageSchema({
      title: post.title,
      description: post.excerpt,
      path: `/blog/${post.slug}`,
      breadcrumbs: breadcrumbItems,
    }),
    breadcrumbSchema(breadcrumbItems),
    {
      "@type": "BlogPosting",
      headline: post.title,
      description: post.excerpt,
      datePublished: post.publishedAt,
      dateModified: post.publishedAt,
      inLanguage: "tr-TR",
      articleSection: post.category,
      image: absoluteImageUrl(post.heroImage),
      keywords: post.seo.keywords.join(", "),
      mainEntityOfPage: `${siteUrl}/blog/${post.slug}`,
      publisher: {
        "@id": `${siteUrl}/#organization`,
      },
    },
  ]);

  return (
    <GuestLayout>
      <SeoHead data={articleSchema} />
      <main className="kgm-page-shell kgm-page-shell--article">
        <Link href="/blog" className="kgm-text-link"><ArrowLeft size={14} /> Blog</Link>
        <article className="kgm-article">
          <header>
            <span>{post.category} · {post.readTime}</span>
            <h1>{post.title}</h1>
            <p>{post.excerpt}</p>
          </header>
          <div className="kgm-article__body">
            {post.content.map((paragraph) => (
              <p key={paragraph}>{paragraph}</p>
            ))}
          </div>
        </article>
      </main>
    </GuestLayout>
  );
}
