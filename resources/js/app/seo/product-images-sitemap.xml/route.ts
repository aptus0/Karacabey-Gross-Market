import { NextResponse } from "next/server";
import type { KgmProduct } from "@/lib/catalog";
import { absoluteImageUrl, absoluteUrl } from "@/lib/seo";
import { fetchStorefrontProducts } from "@/lib/storefront-products";

const productsPerPage = 200;
const maxProductPages = 50;

function escapeXml(value: string) {
  return value
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&apos;");
}

function isProductImage(image?: string | null) {
  return Boolean(image && !/kgm-logo|kg-web|favicon|og-default|twitter-card/i.test(image));
}

async function fetchProductsForImageSitemap() {
  const products: KgmProduct[] = [];

  for (let page = 1; page <= maxProductPages; page += 1) {
    const result = await fetchStorefrontProducts({ page, perPage: productsPerPage });
    products.push(...result.products);

    if (page >= result.lastPage || result.products.length === 0) break;
  }

  return products;
}

export async function GET() {
  const products = await fetchProductsForImageSitemap();
  const urls = products
    .map((product) => {
      const images = Array.from(new Set(product.gallery?.length ? product.gallery : [product.image])).filter(isProductImage);
      if (images.length === 0) return "";

      return `  <url>\n    <loc>${escapeXml(absoluteUrl(`/product/${product.slug}`))}</loc>\n${images.map((image) => `    <image:image>\n      <image:loc>${escapeXml(absoluteImageUrl(image))}</image:loc>\n      <image:title>${escapeXml(product.name)}</image:title>\n      <image:caption>${escapeXml(`${product.name} - Karacabey Gross Market online market ürünü`)}</image:caption>\n    </image:image>`).join("\n")}\n  </url>`;
    })
    .filter(Boolean)
    .join("\n");

  const xml = `<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">\n${urls}\n</urlset>`;

  return new NextResponse(xml, {
    headers: {
      "Content-Type": "application/xml; charset=utf-8",
      "Cache-Control": "public, max-age=1800, s-maxage=3600, stale-while-revalidate=86400",
    },
  });
}
