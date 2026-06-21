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

async function fetchProductsForSitemap() {
  const products: KgmProduct[] = [];

  for (let page = 1; page <= maxProductPages; page += 1) {
    const result = await fetchStorefrontProducts({ page, perPage: productsPerPage });
    products.push(...result.products);

    if (page >= result.lastPage || result.products.length === 0) break;
  }

  return products;
}

export async function GET() {
  const products = await fetchProductsForSitemap();
  const urls = products
    .map((product) => {
      const image = (product.gallery?.length ? product.gallery : [product.image]).find(isProductImage);
      const imageXml = image
        ? `\n    <image:image>\n      <image:loc>${escapeXml(absoluteImageUrl(image))}</image:loc>\n      <image:title>${escapeXml(product.name)}</image:title>\n    </image:image>`
        : "";
      const priority = product.stock > 0 ? "0.82" : "0.58";
      const changefreq = product.stock > 0 ? "daily" : "weekly";

      return `  <url>\n    <loc>${escapeXml(absoluteUrl(`/product/${product.slug}`))}</loc>\n    <changefreq>${changefreq}</changefreq>\n    <priority>${priority}</priority>${imageXml}\n  </url>`;
    })
    .join("\n");

  const xml = `<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">\n${urls}\n</urlset>`;

  return new NextResponse(xml, {
    headers: {
      "Content-Type": "application/xml; charset=utf-8",
      "Cache-Control": "public, max-age=1800, s-maxage=3600, stale-while-revalidate=86400",
    },
  });
}
