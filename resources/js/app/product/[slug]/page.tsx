import type { Metadata } from "next";
import Link from "next/link";
import {
  ArrowRight,
  BadgeCheck,
  Barcode,
  CheckCircle2,
  CreditCard,
  Hash,
  MapPin,
  PackageCheck,
  Star,
  Store,
  Truck,
} from "lucide-react";
import { notFound } from "next/navigation";
import { Breadcrumb } from "@/app/_components/Breadcrumb";
import { FavoriteButton } from "@/app/_components/FavoriteButton";
import { PriceBox } from "@/app/_components/PriceBox";
import { ProductGallery } from "@/app/_components/ProductGallery";
import { ProductInfoAccordions } from "@/app/_components/ProductInfoAccordions";
import { ProductPurchasePanel } from "@/app/_components/ProductPurchasePanel";
import { ProductSlider } from "@/app/_components/ProductSlider";
import { SeoHead } from "@/app/_components/SeoHead";
import { GuestLayout } from "@/app/_layouts/GuestLayout";
import { formatCartMoney } from "@/lib/cart";
import { productImageUrl } from "@/lib/media";
import {
  breadcrumbSchema,
  buildMetadata,
  jsonLdGraph,
  productSchema,
  productSeoImages,
  webPageSchema,
} from "@/lib/seo";
import { fetchStorefrontProduct, fetchStorefrontProducts } from "@/lib/storefront-products";
import { FREE_SHIPPING_CENTS } from "@/lib/shipping-policy";

export const revalidate = 120;

type ProductPageProps = {
  params: Promise<{
    slug: string;
  }>;
};

function seoString(seo: Record<string, unknown> | undefined, key: string) {
  const value = seo?.[key];

  return typeof value === "string" && value.trim() ? value.trim() : undefined;
}

function seoKeywords(seo: Record<string, unknown> | undefined) {
  const value = seo?.keywords;

  return Array.isArray(value)
    ? value.filter((item): item is string => typeof item === "string" && item.trim().length > 0)
    : [];
}

function seoNumberValue(seo: Record<string, unknown> | undefined, key: string): number | null {
  const value = seo?.[key];

  if (typeof value === "number" && Number.isFinite(value)) return value;
  if (typeof value === "string" && value.trim() !== "" && Number.isFinite(Number(value))) return Number(value);

  return null;
}

export async function generateMetadata({ params }: ProductPageProps): Promise<Metadata> {
  const { slug } = await params;
  const product = await fetchStorefrontProduct(slug);

  if (!product) {
    return {};
  }

  const categoryLabel = product.categoryName ?? product.category;
  const brandLabel = product.brand && product.brand !== product.name ? product.brand : null;
  const stockLabel = product.stock > 0 ? "Stokta var" : "Stokta yok";
  const autoDescription = brandLabel
    ? `${product.name} — ${brandLabel} markası, ${categoryLabel} kategorisi. ${stockLabel}, Karacabey Gross Market'te online sipariş ve hızlı teslimat.`
    : `${product.name} — ${categoryLabel} kategorisi. ${stockLabel}. Karacabey Gross Market üzerinden güvenli online sipariş ve kapıya teslimat.`;
  const metadataTitle = seoString(product.seo, "title") ?? (brandLabel ? `${product.name} — ${brandLabel}` : product.name);
  const metadataDescription = seoString(product.seo, "description") ?? product.description ?? autoDescription;
  const firstProductImage = productSeoImages(product)[0];
  const metadataImage = seoString(product.seo, "og_image") ?? seoString(product.seo, "twitter_image") ?? firstProductImage;
  const metadataImageAlt = seoString(product.seo, "og_image_alt") ?? seoString(product.seo, "image_alt");
  const metadataKeywords = seoKeywords(product.seo);
  const metadata = buildMetadata({
    title: metadataTitle,
    description: metadataDescription,
    path: `/product/${product.slug}`,
    image: metadataImage,
    imageAlt: metadataImageAlt,
    type: "website",
    keywords: [
      ...metadataKeywords,
      product.name,
      ...(brandLabel ? [brandLabel, `${brandLabel} ${product.name}`] : []),
      categoryLabel,
      `${categoryLabel} sipariş`,
      `${categoryLabel} fiyat`,
      `${product.name} fiyat`,
      `${product.name} online sipariş`,
      "online market",
      "Karacabey market",
      "Bursa market",
      "hızlı teslimat",
      stockLabel,
    ].filter(Boolean),
  });

  return {
    ...metadata,
    other: {
      ...(metadata.other ?? {}),
      "product:brand": brandLabel ?? product.brand,
      "product:availability": product.stock > 0 ? "in stock" : "out of stock",
      "product:condition": "new",
      "product:price:amount": product.price.toFixed(2),
      "product:price:currency": "TRY",
      "og:price:amount": product.price.toFixed(2),
      "og:price:currency": "TRY",
    },
  };
}

export default async function ProductPage({ params }: ProductPageProps) {
  const { slug } = await params;
  const product = await fetchStorefrontProduct(slug);

  if (!product) {
    notFound();
  }

  const categorySlug = product.category !== "genel" ? product.category : undefined;
  const { products: relatedCandidates } = await fetchStorefrontProducts({
    category: categorySlug,
    perPage: 14,
  });
  const primaryRelatedProducts = relatedCandidates.filter((item) => item.slug !== product.slug).slice(0, 12);
  const relatedProducts = primaryRelatedProducts.length > 0
    ? primaryRelatedProducts
    : (await fetchStorefrontProducts({ perPage: 14 })).products.filter((item) => item.slug !== product.slug).slice(0, 12);

  const breadcrumbItems = [
    { href: "/", label: "Ana Sayfa" },
    { href: "/products", label: "Ürünler" },
    ...(categorySlug && product.categoryName ? [{ href: `/kategori/${categorySlug}`, label: product.categoryName }] : []),
    { href: `/product/${product.slug}`, label: product.name },
  ];
  const categoryLabel = product.categoryName ?? product.category;
  const brandLabel = product.brand && product.brand !== product.name ? product.brand : null;
  const pageDescription = product.description
    || (brandLabel
      ? `${product.name} — ${brandLabel} markası, ${categoryLabel} kategorisi. Karacabey Gross Market online sipariş.`
      : `${product.name} — ${categoryLabel} kategorisi. Karacabey Gross Market üzerinden güvenli online sipariş.`);
  const seoPageTitle = seoString(product.seo, "title") ?? (brandLabel ? `${product.name} — ${brandLabel}` : product.name);
  const seoPageDescription = seoString(product.seo, "content_description")
    ?? seoString(product.seo, "description")
    ?? pageDescription;
  const stockStatus = product.stock > 0 ? (product.stock <= 5 ? `${product.stock} adet kaldı` : "Stokta var") : "Stokta yok";
  const galleryStockStatus = product.stock > 0 ? "Stokta var" : "Stokta yok";
  const productCode = product.sku ?? product.slug;
  const primaryImage = productImageUrl(product.image);
  const ratingValue = seoNumberValue(product.seo, "rating_value");
  const reviewCount = seoNumberValue(product.seo, "review_count");
  const productSpecs = [
    { label: "Marka", value: brandLabel ?? product.brand },
    { label: "Net Miktar", value: product.unit === "adet" ? "1 adet" : product.unit },
    { label: "Menşei", value: "Türkiye" },
    { label: "Saklama Koşulu", value: "Serin ve kuru yerde muhafaza ediniz." },
  ];

  const jsonLd = jsonLdGraph([
    webPageSchema({
      title: seoPageTitle,
      description: seoPageDescription,
      path: `/product/${product.slug}`,
      breadcrumbs: breadcrumbItems,
    }),
    breadcrumbSchema(breadcrumbItems),
    productSchema(product),
  ]);

  return (
    <GuestLayout>
      <SeoHead data={jsonLd} />
      <main className="kgm-product-page kgm-product-page-v3 kgm-product-stable">
        <Breadcrumb
          items={breadcrumbItems.map((item, index) => (
            index === breadcrumbItems.length - 1 ? { label: item.label } : item
          ))}
        />

        <section className="kgm-product-detail kgm-product-detail-v3">
          <div className="kgm-product-detail__media">
            <div className="kgm-product-gallery-shell kgm-product-gallery-shell-v3">
              <span className="kgm-product-gallery-stock">
                <CheckCircle2 size={16} />
                {galleryStockStatus}
              </span>
              <FavoriteButton productSlug={product.slug} className="kgm-product-gallery-favorite" iconSize={20} />
              <ProductGallery images={product.gallery?.length ? product.gallery : [product.image]} name={product.name} />
            </div>
          </div>

          <div className="kgm-product-detail__info">
            <div className="kgm-product-summary kgm-product-summary-v3">
              <div className="kgm-product-detail__meta">
                {brandLabel ? (
                  <span>{brandLabel}</span>
                ) : null}
                <span>{product.categoryName ?? product.category}</span>
              </div>

              <h1>{product.name}</h1>

              <div className="kgm-product-rating-row" aria-label="Ürün değerlendirmesi">
                {ratingValue && reviewCount ? (
                  <>
                    <span className="kgm-product-stars" aria-hidden="true">
                      {Array.from({ length: 5 }).map((_, index) => (
                        <Star key={index} size={17} fill="currentColor" />
                      ))}
                    </span>
                    <strong>{ratingValue.toFixed(1)}</strong>
                    <span>({Math.floor(reviewCount)} değerlendirme)</span>
                    <span className="kgm-product-rating-row__divider" aria-hidden="true" />
                  </>
                ) : (
                  <span>İlk değerlendiren sen ol</span>
                )}
                <span><Hash size={14} /> Ürün Kodu: {productCode}</span>
                {product.barcode ? <span><Barcode size={14} /> {product.barcode}</span> : null}
              </div>

              <div className="kgm-product-price-row kgm-product-price-row-v3">
                <PriceBox price={product.price} oldPrice={product.oldPrice} unit={product.unit} />
              </div>

              <p className="kgm-product-tax-note">
                <BadgeCheck size={15} />
                Fiyatlarımıza KDV dahildir.
              </p>

              <div className="kgm-product-trust-strip" aria-label="Alışveriş avantajları">
                <span><Truck size={17} /> <strong>Hızlı teslimat</strong><small>Konumuna göre</small></span>
                <span><CreditCard size={17} /> <strong>Güvenli ödeme</strong><small>%100 güvenli</small></span>
                <span><CheckCircle2 size={17} /> <strong>Stok kontrolü</strong><small>Taze ürün garantisi</small></span>
              </div>

              {product.description ? (
                <p>{product.description}</p>
              ) : null}

              <dl className="kgm-product-mini-specs">
                {productSpecs.map((spec) => (
                  <div key={spec.label}>
                    <dt>{spec.label}</dt>
                    <dd>{spec.value}</dd>
                  </div>
                ))}
              </dl>
            </div>
          </div>

          <aside className="kgm-product-detail__buy kgm-product-detail__buy-v3">
            <div className="kgm-product-side-card">
              <div className="kgm-product-seller-card">
                <div className="kgm-product-seller-card__icon" aria-hidden="true">
                  <Store size={22} />
                </div>
                <div>
                  <span>Satıcı</span>
                  <strong>Karacabey Gross Market</strong>
                  <div className="kgm-store-scoreline">
                    <span className="kgm-store-score"><Star size={12} fill="currentColor" /> 9.6</span>
                    <small>Mağaza Puanı</small>
                  </div>
                </div>
              </div>

              <div className="kgm-product-side-card__rows">
                <div>
                  <MapPin size={20} />
                  <span>Teslimat</span>
                  <strong>Konumunuza göre hızlı teslimat</strong>
                </div>
                <div>
                  <PackageCheck size={20} />
                  <span>Stok Durumu</span>
                  <strong>{stockStatus}</strong>
                </div>
                <div>
                  <Truck size={20} />
                  <span>Kargo</span>
                  <strong>{formatCartMoney(FREE_SHIPPING_CENTS)} üzeri ücretsiz</strong>
                </div>
              </div>

              <ProductPurchasePanel
                productSlug={product.slug}
                productId={product.id}
                stock={product.stock}
                optimisticProduct={typeof product.id === "number" ? {
                  id: product.id,
                  name: product.name,
                  slug: product.slug,
                  brand: product.brand,
                  price_cents: Math.round(product.price * 100),
                  price: product.price.toFixed(2),
                  stock_quantity: product.stock,
                  unit_name: product.unit,
                  image_url: primaryImage,
                } : undefined}
              />
            </div>
          </aside>
        </section>

        <section className="kgm-product-lower-grid">
          <ProductInfoAccordions product={product} />
        </section>

        {relatedProducts.length > 0 ? (
          <section className="kgm-related-products kgm-related-products--bottom" aria-label="Benzer ürünler">
            <div className="kgm-related-products__head">
              <div>
                <span>Birlikte iyi gider</span>
                <h2>Benzer Ürünler</h2>
                <p>Bu ürüne yakın kategoriden seçilen alternatifleri hızlıca sepete ekleyebilirsin.</p>
              </div>
              <Link href={categorySlug ? `/kategori/${categorySlug}` : "/products"}>
                Tümünü Gör
                <ArrowRight size={16} />
              </Link>
            </div>
            <ProductSlider products={relatedProducts} ariaLabel="Benzer ürünler" autoplay />
          </section>
        ) : null}
      </main>
    </GuestLayout>
  );
}
