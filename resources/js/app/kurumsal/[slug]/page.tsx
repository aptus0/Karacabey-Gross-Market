import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { Breadcrumb } from "@/app/_components/Breadcrumb";
import { SeoHead } from "@/app/_components/SeoHead";
import { GuestLayout } from "@/app/_layouts/GuestLayout";
import { findStorePage, storePages } from "@/lib/content";
import {
  breadcrumbSchema,
  buildMetadata,
  jsonLdGraph,
  webPageSchema,
} from "@/lib/seo";

type CorporatePageProps = {
  params: Promise<{
    slug: string;
  }>;
};

export function generateStaticParams() {
  return storePages.map((page) => ({ slug: page.slug }));
}

export async function generateMetadata({ params }: CorporatePageProps): Promise<Metadata> {
  const { slug } = await params;
  const page = findStorePage(slug);

  if (!page) {
    return {};
  }

  return {
    ...buildMetadata({
      title: page.seo.title,
      description: page.seo.description,
      path: `/kurumsal/${page.slug}`,
      type: "article",
      keywords: [page.group, page.title, "kurumsal içerik", "bilgilendirme sayfası"],
    }),
  };
}

export default async function CorporatePage({ params }: CorporatePageProps) {
  const { slug } = await params;
  const page = findStorePage(slug);

  if (!page) {
    notFound();
  }

  const breadcrumbItems = [
    { href: "/", label: "Ana Sayfa" },
    { href: `/kurumsal/${page.slug}`, label: page.title },
  ];
  const jsonLd = jsonLdGraph([
    webPageSchema({
      title: page.title,
      description: page.seo.description,
      path: `/kurumsal/${page.slug}`,
      type: page.slug === "sss" ? "FAQPage" : "WebPage",
      breadcrumbs: breadcrumbItems,
    }),
    breadcrumbSchema(breadcrumbItems),
  ]);

  return (
    <GuestLayout>
      <SeoHead data={jsonLd} />
      <main className="content-page">
        <Breadcrumb
          items={[
            { href: "/", label: "Ana Sayfa" },
            { label: page.title },
          ]}
        />
        <article className="content-article">
          <p className="eyebrow">{page.group}</p>
          <h1>{page.title}</h1>
          <p>{page.body}</p>
        </article>
      </main>
    </GuestLayout>
  );
}
