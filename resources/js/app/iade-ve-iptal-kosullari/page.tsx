import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { PublicContentPage } from "@/app/_components/PublicContentPage";
import { findPublicPage } from "@/lib/public-pages";
import { buildMetadata } from "@/lib/seo";

const slug = "iade-ve-iptal-kosullari";

export function generateMetadata(): Metadata {
  const page = findPublicPage(slug);
  if (!page) return {};

  return buildMetadata({
    title: page.seo.title,
    description: page.seo.description,
    path: `/${page.slug}`,
    keywords: page.seo.keywords,
    type: "article",
  });
}

export default function Page() {
  const page = findPublicPage(slug);
  if (!page) notFound();
  return <PublicContentPage page={page} />;
}
