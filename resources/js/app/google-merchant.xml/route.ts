import type { KgmProduct } from "@/lib/catalog";
import { absoluteImageUrl, absoluteUrl, businessCountry, siteName, siteUrl } from "@/lib/seo";
import { fetchStorefrontProducts } from "@/lib/storefront-products";

export const dynamic = "force-dynamic";
export const revalidate = 3600;

const maxProductPages = 25;
const productsPerPage = 200;
const feedUpdatedAt = new Date().toISOString();
const minHandlingDays = 0;
const maxHandlingDays = 1;
const minTransitDays = 1;
const maxTransitDays = 3;

function escapeXml(value: string | number | null | undefined) {
  return String(value ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&apos;");
}

function stripHtml(value: string) {
  return value.replace(/<[^>]*>/g, " ").replace(/\s+/g, " ").trim();
}

function truncate(value: string, maxLength: number) {
  const normalized = stripHtml(value);

  return normalized.length > maxLength ? `${normalized.slice(0, maxLength - 1).trim()}…` : normalized;
}

function formatPrice(value: number) {
  return `${value.toFixed(2)} TRY`;
}

function merchantId(product: KgmProduct) {
  const rawId = product.barcode || product.sku || product.id || product.slug;

  return String(rawId).slice(0, 50);
}

function googleProductCategory(product: KgmProduct) {
  const source = `${product.category} ${product.categoryName ?? ""} ${product.name}`.toLocaleLowerCase("tr-TR");

  if (source.includes("bebek")) return "Baby & Toddler";
  if (source.includes("evcil") || source.includes("kedi") || source.includes("köpek")) {
    return "Animals & Pet Supplies > Pet Supplies";
  }
  if (source.includes("temizlik") || source.includes("deterjan") || source.includes("çamaşır")) {
    return "Home & Garden > Household Supplies";
  }
  if (source.includes("kişisel") || source.includes("bakım") || source.includes("kozmetik") || source.includes("şampuan")) {
    return "Health & Beauty > Personal Care";
  }
  if (source.includes("içecek") || source.includes("su") || source.includes("kola") || source.includes("meyve suyu")) {
    return "Food, Beverages & Tobacco > Beverages";
  }
  if (source.includes("kahvaltılık") || source.includes("reçel") || source.includes("bal")) {
    return "Food, Beverages & Tobacco > Food Items > Breakfast Foods";
  }
  if (source.includes("şarküteri") || source.includes("kaşar") || source.includes("peynir") || source.includes("salam") || source.includes("sucuk")) {
    return "Food, Beverages & Tobacco > Food Items > Dairy Products";
  }
  if (source.includes("meyve") || source.includes("sebze") || source.includes("taze")) {
    return "Food, Beverages & Tobacco > Food Items > Fruits & Vegetables";
  }
  if (source.includes("et") || source.includes("tavuk") || source.includes("balık")) {
    return "Food, Beverages & Tobacco > Food Items > Meat, Seafood & Eggs";
  }
  if (source.includes("kuruyemiş") || source.includes("fındık") || source.includes("badem") || source.includes("ceviz")) {
    return "Food, Beverages & Tobacco > Food Items > Snack Foods";
  }
  return "Food, Beverages & Tobacco > Food Items";
}

function productType(product: KgmProduct) {
  return [product.categoryName, product.category, product.brand].filter(Boolean).join(" > ");
}

function parseUnitMeasure(unit: string | undefined): string | null {
  if (!unit) return null;
  const cleaned = unit.toLocaleLowerCase("tr-TR").replace(/\s+/g, "").replace(",", ".");
  const match = cleaned.match(/^([\d.]+)?(kg|gr|gram|g|l|lt|litre|ml|cl|adet|paket|pk)$/);
  if (!match) return null;
  const amount = match[1] ? parseFloat(match[1]) : 1;
  const rawUnit = match[2];
  const unitMap: Record<string, string> = {
    kg: "kg",
    gr: "g",
    gram: "g",
    g: "g",
    l: "l",
    lt: "l",
    litre: "l",
    ml: "ml",
    cl: "ml",
    adet: "ct",
    paket: "ct",
    pk: "ct",
  };
  const normalized = unitMap[rawUnit];
  if (!normalized) return null;
  const finalAmount = rawUnit === "cl" ? amount * 10 : amount;
  return `${finalAmount} ${normalized}`;
}

function productHighlights(product: KgmProduct): string[] {
  const highlights: string[] = [];
  if (product.brand) highlights.push(`Marka: ${product.brand}`);
  if (product.unit) highlights.push(`Birim: ${product.unit}`);
  highlights.push("Karacabey'den hızlı teslimat");
  highlights.push("1500 TL üzeri ücretsiz kargo");
  highlights.push("PayTR güvenli ödeme");
  return highlights.slice(0, 5);
}

function customLabels(product: KgmProduct) {
  const isOnSale = product.oldPrice && product.oldPrice > product.price;
  const stockBand =
    product.stock <= 0 ? "tukendi" : product.stock < 5 ? "kritik" : product.stock < 25 ? "az" : "bol";
  const priceBand =
    product.price < 50 ? "uygun" : product.price < 200 ? "orta" : product.price < 500 ? "ust" : "premium";

  return {
    label0: isOnSale ? "indirimli" : "standart",
    label1: stockBand,
    label2: priceBand,
    label3: product.categoryName || product.category || "genel",
    label4: product.badge || (product.brand ? `marka:${product.brand}` : "genel"),
  };
}

function productXml(product: KgmProduct) {
  const url = absoluteUrl(`/product/${product.slug}`);
  const image = absoluteImageUrl(product.image);
  const gallery = (product.gallery ?? [])
    .filter((item) => item && item !== product.image)
    .slice(0, 10);
  const title = truncate(`${product.brand ? `${product.brand} ` : ""}${product.name}`, 150);
  const description = truncate(
    product.description || `${product.name} — Karacabey Gross Market online market kataloğunda. Hızlı teslimat, güvenli ödeme.`,
    5000,
  );
  const brand = truncate(product.brand || siteName, 70);
  const id = merchantId(product);
  const mpn = product.sku || product.barcode || product.slug;
  const gtin = product.barcode && /^\d{8,14}$/.test(product.barcode) ? product.barcode : null;
  const onSale = !!(product.oldPrice && product.oldPrice > product.price);
  const listPrice = onSale ? product.oldPrice! : product.price;
  const labels = customLabels(product);
  const unitMeasure = parseUnitMeasure(product.unit);
  const hasIdentifier = !!(gtin || (product.sku && product.brand));

  return `
    <item>
      <g:id>${escapeXml(id)}</g:id>
      <g:title>${escapeXml(title)}</g:title>
      <g:description>${escapeXml(description)}</g:description>
      <g:link>${escapeXml(url)}</g:link>
      <g:mobile_link>${escapeXml(url)}</g:mobile_link>
      <g:image_link>${escapeXml(image)}</g:image_link>
      ${gallery.map((item) => `<g:additional_image_link>${escapeXml(absoluteImageUrl(item))}</g:additional_image_link>`).join("\n      ")}
      <g:availability>${product.stock > 0 ? "in_stock" : "out_of_stock"}</g:availability>
      <g:price>${escapeXml(formatPrice(listPrice))}</g:price>
      ${onSale ? `<g:sale_price>${escapeXml(formatPrice(product.price))}</g:sale_price>` : ""}
      <g:condition>new</g:condition>
      <g:brand>${escapeXml(brand)}</g:brand>
      ${gtin ? `<g:gtin>${escapeXml(gtin)}</g:gtin>` : ""}
      <g:mpn>${escapeXml(mpn)}</g:mpn>
      <g:identifier_exists>${hasIdentifier ? "yes" : "no"}</g:identifier_exists>
      <g:google_product_category>${escapeXml(googleProductCategory(product))}</g:google_product_category>
      <g:product_type>${escapeXml(productType(product))}</g:product_type>
      <g:adult>no</g:adult>
      ${unitMeasure ? `<g:unit_pricing_measure>${escapeXml(unitMeasure)}</g:unit_pricing_measure>` : ""}
      ${productHighlights(product).map((highlight) => `<g:product_highlight>${escapeXml(highlight)}</g:product_highlight>`).join("\n      ")}
      <g:custom_label_0>${escapeXml(labels.label0)}</g:custom_label_0>
      <g:custom_label_1>${escapeXml(labels.label1)}</g:custom_label_1>
      <g:custom_label_2>${escapeXml(labels.label2)}</g:custom_label_2>
      <g:custom_label_3>${escapeXml(labels.label3)}</g:custom_label_3>
      <g:custom_label_4>${escapeXml(labels.label4)}</g:custom_label_4>
      <g:min_handling_time>${minHandlingDays}</g:min_handling_time>
      <g:max_handling_time>${maxHandlingDays}</g:max_handling_time>
      <g:shipping>
        <g:country>${escapeXml(businessCountry)}</g:country>
        <g:service>Standart Kargo</g:service>
        <g:price>49.90 TRY</g:price>
        <g:min_transit_time>${minTransitDays}</g:min_transit_time>
        <g:max_transit_time>${maxTransitDays}</g:max_transit_time>
      </g:shipping>
      <g:shipping>
        <g:country>${escapeXml(businessCountry)}</g:country>
        <g:service>Ücretsiz Kargo (1500₺ üzeri)</g:service>
        <g:price>0.00 TRY</g:price>
        <g:min_transit_time>${minTransitDays}</g:min_transit_time>
        <g:max_transit_time>${maxTransitDays}</g:max_transit_time>
      </g:shipping>
    </item>`;
}

async function fetchAllMerchantProducts() {
  const allProducts: KgmProduct[] = [];

  for (let page = 1; page <= maxProductPages; page += 1) {
    const result = await fetchStorefrontProducts({
      page,
      perPage: productsPerPage,
    });

    allProducts.push(...result.products);

    if (page >= result.lastPage || result.products.length === 0) {
      break;
    }
  }

  const seen = new Set<string>();

  return allProducts.filter((product) => {
    const id = merchantId(product);

    if (!product.slug || !product.name || product.price <= 0 || seen.has(id)) {
      return false;
    }

    seen.add(id);
    return true;
  });
}

export async function GET() {
  const products = await fetchAllMerchantProducts();
  const xml = `<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">
  <channel>
    <title>${escapeXml(siteName)} Google Merchant Feed</title>
    <link>${escapeXml(siteUrl)}</link>
    <description>${escapeXml(`${siteName} ürün kataloğu — fiyat, stok, görsel ve kargo bilgileri ile Google Merchant Center uyumlu feed.`)}</description>
    <language>tr-TR</language>
    <lastBuildDate>${escapeXml(feedUpdatedAt)}</lastBuildDate>
    ${products.map(productXml).join("\n")}
  </channel>
</rss>`;

  return new Response(xml, {
    headers: {
      "Cache-Control": "public, max-age=1800, s-maxage=3600, stale-while-revalidate=86400",
      "Content-Type": "application/xml; charset=utf-8",
      "X-Robots-Tag": "noindex",
      "X-Product-Count": String(products.length),
    },
  });
}
