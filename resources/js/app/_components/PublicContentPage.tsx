import Link from "next/link";
import { ArrowRight, FileText } from "lucide-react";
import { SeoHead } from "@/app/_components/SeoHead";
import { GuestLayout } from "@/app/_layouts/GuestLayout";
import type { PublicPageData } from "@/lib/public-pages";
import {
  breadcrumbSchema,
  businessAddress,
  businessEmail,
  businessPhone,
  faqPageSchema,
  jsonLdGraph,
  webPageSchema,
} from "@/lib/seo";

export function PublicContentPage({ page }: { page: PublicPageData }) {
  const breadcrumbItems = [
    { href: "/", label: "Ana Sayfa" },
    { href: `/${page.slug}`, label: page.title },
  ];
  const webPageType = page.slug === "iletisim"
    ? "ContactPage"
    : page.slug === "hakkimizda"
      ? "AboutPage"
      : page.slug === "sikca-sorulan-sorular"
        ? "FAQPage"
        : "WebPage";
  const nodes: Array<Record<string, unknown>> = [
    webPageSchema({
      title: page.title,
      description: page.seo.description,
      path: `/${page.slug}`,
      type: webPageType,
      breadcrumbs: breadcrumbItems,
    }),
    breadcrumbSchema(breadcrumbItems),
  ];

  if (page.slug === "sikca-sorulan-sorular") {
    nodes.push(faqPageSchema(page.sections.map((section) => ({
      question: section.title,
      answer: section.body,
    }))));
  }

  if (page.slug === "iletisim") {
    nodes.push({
      "@type": "ContactPage",
      mainEntity: {
        "@type": "Organization",
        name: "Karacabey Gross Market",
        telephone: businessPhone,
        email: businessEmail,
        address: businessAddress,
      },
    });
  }

  return (
    <GuestLayout>
      <SeoHead data={jsonLdGraph(nodes)} />
      <main className="kgm-public-page">
        <section className="kgm-public-page__shell">
          <div className="kgm-public-page__head">
            <span className="kgm-public-page__eyebrow">
              <FileText size={15} />
              {page.eyebrow}
            </span>
            <h1>{page.title}</h1>
            <p>{page.description}</p>
          </div>

          <div className="kgm-public-page__sections">
            {page.sections.map((section) => (
              <article key={section.title} className="kgm-public-page__section">
                <h2>{section.title}</h2>
                <p>{section.body}</p>
              </article>
            ))}
          </div>

          {page.links && page.links.length > 0 ? (
            <div className="kgm-public-page__links">
              {page.links.map((link) => (
                <Link key={link.href} href={link.href}>
                  {link.label}
                  <ArrowRight size={15} />
                </Link>
              ))}
            </div>
          ) : null}
        </section>
      </main>
    </GuestLayout>
  );
}
