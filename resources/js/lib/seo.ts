import type { Metadata } from "next";
import type { KgmCategory, KgmProduct } from "@/lib/catalog";
import { FREE_SHIPPING_CENTS, STANDARD_SHIPPING_CENTS } from "@/lib/shipping-policy";

export const siteName = "Karacabey Gross Market";
export const legalName = "Karacabey Gross Market";
export const siteUrl = process.env.NEXT_PUBLIC_SITE_URL ?? "https://karacabeygrossmarket.com";
export const defaultSeoImage = "/assets/kgm-logo.png";
export const defaultOpenGraphImage = "/seo/og-default.png";
export const defaultTwitterImage = "/seo/twitter-card.png";
export const businessPhone = "+90 224 676 84 33";
export const businessEmail = "destek@karacabeygrossmarket.com";
export const businessStreetAddress = "Drama Mahallesi, Runguçpaşa Caddesi";
export const businessLocality = "Karacabey";
export const businessRegion = "Bursa";
export const businessPostalCode = "16700";
export const businessCountry = "TR";
export const businessAddress = `${businessStreetAddress}, ${businessPostalCode} ${businessLocality}/${businessRegion}, Türkiye`;
export const businessGeo = { latitude: 40.21283, longitude: 28.36172 } as const;
export const businessOpeningHours = [
  {
    days: ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"],
    opens: "09:00",
    closes: "21:00",
  },
] as const;
export const businessSameAs = [
  "https://www.google.com/maps/place/?q=place_id:Karacabey+Gross+Market",
  "https://yandex.com.tr/maps/org/karacabey_gross_market/",
  "https://www.instagram.com/atsgross/",
  "https://www.facebook.com/karacabeygrossmarket",
];
export const defaultSeoKeywords = [
  "Karacabey Gross Market",
  "karacabeygrossmarket",
  "karacabey gross market",
  "Karacabey online market",
  "Bursa market siparişi",
  "Karacabey market online sipariş",
  "Drama Mahallesi market",
  "Runguçpaşa Caddesi market",
  "gross market Karacabey",
  "hızlı teslimat",
  "güvenli ödeme",
];

type BuildMetadataInput = {
  title: string;
  description: string;
  path?: string;
  image?: string;
  imageAlt?: string;
  keywords?: string[];
  robots?: Metadata["robots"];
  type?: "website" | "article";
};

export function absoluteUrl(path = "/") {
  if (/^https?:\/\//i.test(path)) {
    return path;
  }

  const normalizedPath = path.startsWith("/") ? path : `/${path}`;

  return normalizedPath === "/" ? siteUrl : `${siteUrl}${normalizedPath}`;
}

export function absoluteImageUrl(path = defaultSeoImage) {
  return absoluteUrl(path);
}

export function buildMetadata({
  title,
  description,
  path = "/",
  image = defaultOpenGraphImage,
  imageAlt,
  keywords = [],
  robots,
  type = "website",
}: BuildMetadataInput): Metadata {
  const fullTitle = title.includes(siteName) ? title : `${title} | ${siteName}`;
  const canonicalPath = path.startsWith("/") ? path : `/${path}`;
  const url = absoluteUrl(canonicalPath);
  const imageUrl = absoluteImageUrl(image);
  const twitterImageUrl = absoluteImageUrl(image === defaultOpenGraphImage ? defaultTwitterImage : image);
  const resolvedImageAlt = imageAlt ?? fullTitle;

  return {
    title: {
      absolute: fullTitle,
    },
    description,
    authors: [{ name: siteName, url: siteUrl }],
    creator: siteName,
    publisher: siteName,
    category: type === "article" ? "article" : "shopping",
    keywords: [...defaultSeoKeywords, ...keywords],
    referrer: "strict-origin-when-cross-origin",
    alternates: {
      canonical: canonicalPath,
      languages: {
        "tr-TR": canonicalPath,
      },
    },
    openGraph: {
      title: fullTitle,
      description,
      url,
      siteName,
      locale: "tr_TR",
      type,
      images: [
        {
          url: imageUrl,
          alt: resolvedImageAlt,
          width: 1200,
          height: 630,
        },
      ],
    },
    twitter: {
      card: "summary_large_image",
      title: fullTitle,
      description,
      images: [twitterImageUrl],
      creator: siteName,
      site: siteName,
    },
    robots,
  };
}

export function breadcrumbSchema(items: Array<{ label: string; href: string }>) {
  return {
    "@type": "BreadcrumbList",
    itemListElement: items.map((item, index) => ({
      "@type": "ListItem",
      position: index + 1,
      name: item.label,
      item: absoluteUrl(item.href),
    })),
  };
}

export function organizationSchema() {
  return {
    "@type": "Organization",
    "@id": `${siteUrl}/#organization`,
    name: siteName,
    legalName,
    alternateName: ["karacabeygrossmarket", "Karacabey Gross", "KGM"],
    url: siteUrl,
    logo: {
      "@type": "ImageObject",
      url: absoluteImageUrl(defaultSeoImage),
      width: 512,
      height: 512,
    },
    image: absoluteImageUrl(defaultSeoImage),
    description:
      "Karacabey ve Bursa'nın online gross marketi. Taze ürünler, şarküteri, kuruyemiş, temizlik ve daha fazlasını kapınıza hızlı teslimat ile sunar.",
    email: businessEmail,
    telephone: businessPhone,
    address: {
      "@type": "PostalAddress",
      streetAddress: businessStreetAddress,
      addressLocality: businessLocality,
      addressRegion: businessRegion,
      postalCode: businessPostalCode,
      addressCountry: businessCountry,
    },
    sameAs: businessSameAs,
    contactPoint: [
      {
        "@type": "ContactPoint",
        telephone: businessPhone,
        email: businessEmail,
        contactType: "customer support",
        areaServed: "TR",
        availableLanguage: ["tr"],
      },
    ],
  };
}

export function groceryStoreSchema() {
  return {
    "@type": "GroceryStore",
    "@id": `${siteUrl}/#store`,
    name: siteName,
    alternateName: ["karacabeygrossmarket", "Karacabey Gross", "KGM"],
    description:
      "Karacabey'in yerel gross marketi — online sipariş, hızlı teslimat ve güvenli ödeme ile market alışverişi.",
    url: siteUrl,
    image: absoluteImageUrl(defaultSeoImage),
    logo: absoluteImageUrl(defaultSeoImage),
    telephone: businessPhone,
    email: businessEmail,
    address: {
      "@type": "PostalAddress",
      streetAddress: businessStreetAddress,
      addressLocality: businessLocality,
      addressRegion: businessRegion,
      postalCode: businessPostalCode,
      addressCountry: businessCountry,
    },
    geo: {
      "@type": "GeoCoordinates",
      latitude: businessGeo.latitude,
      longitude: businessGeo.longitude,
    },
    hasMap: `https://www.google.com/maps/search/?api=1&query=${businessGeo.latitude}%2C${businessGeo.longitude}`,
    openingHoursSpecification: businessOpeningHours.map((spec) => ({
      "@type": "OpeningHoursSpecification",
      dayOfWeek: spec.days,
      opens: spec.opens,
      closes: spec.closes,
    })),
    areaServed: [
      {
        "@type": "City",
        name: "Karacabey",
      },
      {
        "@type": "AdministrativeArea",
        name: "Bursa",
      },
    ],
    paymentAccepted: ["Cash", "Credit Card", "Debit Card", "Online Payment"],
    currenciesAccepted: "TRY",
    priceRange: "₺₺",
    sameAs: businessSameAs,
    parentOrganization: {
      "@id": `${siteUrl}/#organization`,
    },
  };
}

export function websiteSchema() {
  return {
    "@type": "WebSite",
    "@id": `${siteUrl}/#website`,
    name: siteName,
    url: siteUrl,
    inLanguage: "tr-TR",
    publisher: {
      "@id": `${siteUrl}/#organization`,
    },
    potentialAction: {
      "@type": "SearchAction",
      target: `${siteUrl}/products?q={search_term_string}`,
      "query-input": "required name=search_term_string",
    },
  };
}

export function webPageSchema({
  title,
  description,
  path,
  type = "WebPage",
  breadcrumbs,
}: {
  title: string;
  description: string;
  path: string;
  type?: "WebPage" | "AboutPage" | "ContactPage" | "FAQPage" | "CollectionPage";
  breadcrumbs?: Array<{ label: string; href: string }>;
}) {
  const schema: Record<string, unknown> = {
    "@type": type,
    "@id": `${absoluteUrl(path)}#webpage`,
    url: absoluteUrl(path),
    name: title,
    description,
    inLanguage: "tr-TR",
    isPartOf: {
      "@id": `${siteUrl}/#website`,
    },
    publisher: {
      "@id": `${siteUrl}/#organization`,
    },
  };

  if (breadcrumbs?.length) {
    schema.breadcrumb = breadcrumbSchema(breadcrumbs);
  }

  return schema;
}

export function faqPageSchema(questions: Array<{ question: string; answer: string }>) {
  return {
    "@type": "FAQPage",
    mainEntity: questions.map((item) => ({
      "@type": "Question",
      name: item.question,
      acceptedAnswer: {
        "@type": "Answer",
        text: item.answer,
      },
    })),
  };
}

export function itemListSchema({
  name,
  description,
  path,
  items,
}: {
  name: string;
  description: string;
  path: string;
  items: Array<{ name: string; url: string }>;
}) {
  return {
    "@type": "ItemList",
    name,
    description,
    url: absoluteUrl(path),
    numberOfItems: items.length,
    itemListElement: items.map((item, index) => ({
      "@type": "ListItem",
      position: index + 1,
      name: item.name,
      url: absoluteUrl(item.url),
    })),
  };
}

export function serviceSchema({
  name,
  description,
  path,
  serviceType,
  areaServed = "Türkiye",
}: {
  name: string;
  description: string;
  path: string;
  serviceType: string;
  areaServed?: string;
}) {
  return {
    "@type": "Service",
    "@id": `${absoluteUrl(path)}#service`,
    name,
    description,
    serviceType,
    areaServed,
    provider: {
      "@id": `${siteUrl}/#organization`,
    },
    url: absoluteUrl(path),
  };
}

export function webApplicationSchema({
  name,
  description,
  path,
  category = "ShoppingApplication",
}: {
  name: string;
  description: string;
  path: string;
  category?: string;
}) {
  return {
    "@type": "WebApplication",
    "@id": `${absoluteUrl(path)}#webapplication`,
    name,
    description,
    url: absoluteUrl(path),
    applicationCategory: category,
    operatingSystem: "Web",
    offers: {
      "@type": "Offer",
      price: "0",
      priceCurrency: "TRY",
    },
    publisher: {
      "@id": `${siteUrl}/#organization`,
    },
  };
}

export function categoryListSchema(categories: KgmCategory[]) {
  return itemListSchema({
    name: "Karacabey Gross Market Reyonları",
    description: "Karacabey Gross Market ürün reyonları ve alt kategorileri.",
    path: "/kategoriler",
    items: categories.flatMap((category) => [
      { name: category.name, url: `/kategori/${category.slug}` },
      ...(category.children ?? []).map((child) => ({
        name: child.name,
        url: `/kategori/${child.slug}`,
      })),
    ]),
  });
}

export function isSeoProductImage(image?: string | null) {
  return Boolean(image && !/kgm-logo|kg-web|favicon|og-default|twitter-card/i.test(image));
}

export function productSeoImages(product: KgmProduct) {
  return Array.from(new Set(product.gallery?.length ? product.gallery : [product.image]))
    .filter((image): image is string => isSeoProductImage(image));
}

function seoNumber(seo: Record<string, unknown> | undefined, key: string): number | null {
  const value = seo?.[key];

  if (typeof value === "number" && Number.isFinite(value)) return value;
  if (typeof value === "string" && value.trim() !== "" && Number.isFinite(Number(value))) return Number(value);

  return null;
}

export function productSchema(product: KgmProduct) {
  const brand = product.brand && product.brand !== product.name ? product.brand : undefined;
  const gtin13 = product.barcode && /^\d{13}$/.test(product.barcode) ? product.barcode : undefined;
  const gtin14 = product.barcode && /^\d{14}$/.test(product.barcode) ? product.barcode : undefined;
  const ratingValue = seoNumber(product.seo, "rating_value");
  const reviewCount = seoNumber(product.seo, "review_count");
  const standardShippingValue = (STANDARD_SHIPPING_CENTS / 100).toFixed(2);
  const freeShippingThresholdValue = (FREE_SHIPPING_CENTS / 100).toFixed(2);

  return {
    "@type": "Product",
    "@id": `${absoluteUrl(`/product/${product.slug}`)}#product`,
    name: product.name,
    ...(brand ? { brand: { "@type": "Brand", name: brand } } : {}),
    sku: product.sku ?? product.slug,
    mpn: product.sku ?? product.slug,
    ...(gtin13 ? { gtin13 } : {}),
    ...(gtin14 ? { gtin14 } : {}),
    category: product.categoryName ?? product.category,
    url: absoluteUrl(`/product/${product.slug}`),
    ...(productSeoImages(product).length > 0 ? { image: productSeoImages(product).map(absoluteImageUrl) } : {}),
    ...(product.description ? { description: product.description } : {}),
    ...(ratingValue && reviewCount ? {
      aggregateRating: {
        "@type": "AggregateRating",
        ratingValue: Math.max(1, Math.min(5, ratingValue)),
        reviewCount: Math.max(1, Math.floor(reviewCount)),
      },
    } : {}),
    offers: {
      "@type": "Offer",
      "@id": `${absoluteUrl(`/product/${product.slug}`)}#offer`,
      url: absoluteUrl(`/product/${product.slug}`),
      priceCurrency: "TRY",
      price: product.price,
      priceValidUntil: new Date(Date.now() + Number(process.env.KGM_SEO_PRICE_VALID_DAYS ?? 7) * 24 * 60 * 60 * 1000).toISOString().split("T")[0],
      itemCondition: "https://schema.org/NewCondition",
      availability: product.stock > 0 ? "https://schema.org/InStock" : "https://schema.org/OutOfStock",
      hasMerchantReturnPolicy: {
        "@type": "MerchantReturnPolicy",
        applicableCountry: "TR",
        returnPolicyCategory: "https://schema.org/MerchantReturnFiniteReturnWindow",
        merchantReturnDays: 14,
        returnMethod: "https://schema.org/ReturnByMail",
        returnFees: "https://schema.org/FreeReturn",
      },
      shippingDetails: {
        "@type": "OfferShippingDetails",
        shippingRate: { "@type": "MonetaryAmount", value: standardShippingValue, currency: "TRY" },
        freeShippingThreshold: {
          "@type": "MonetaryAmount",
          value: freeShippingThresholdValue,
          currency: "TRY",
        },
        shippingDestination: { "@type": "DefinedRegion", addressCountry: "TR" },
        deliveryTime: {
          "@type": "ShippingDeliveryTime",
          handlingTime: { "@type": "QuantitativeValue", minValue: 0, maxValue: 1, unitCode: "DAY" },
          transitTime: { "@type": "QuantitativeValue", minValue: 1, maxValue: 3, unitCode: "DAY" },
        },
      },
      seller: {
        "@id": `${siteUrl}/#store`,
      },
    },
  };
}

export function productItemListSchema({
  name,
  description,
  path,
  products,
}: {
  name: string;
  description: string;
  path: string;
  products: KgmProduct[];
}) {
  return {
    "@type": "ItemList",
    name,
    description,
    url: absoluteUrl(path),
    numberOfItems: products.length,
    itemListElement: products.map((product, index) => ({
      "@type": "ListItem",
      position: index + 1,
      item: {
        "@type": "Product",
        name: product.name,
        url: absoluteUrl(`/product/${product.slug}`),
        ...(productSeoImages(product)[0] ? { image: absoluteImageUrl(productSeoImages(product)[0]) } : {}),
        sku: product.sku ?? product.slug,
        ...(product.brand && product.brand !== product.name ? { brand: { "@type": "Brand", name: product.brand } } : {}),
        offers: {
          "@type": "Offer",
          priceCurrency: "TRY",
          price: product.price,
          availability: product.stock > 0 ? "https://schema.org/InStock" : "https://schema.org/OutOfStock",
          url: absoluteUrl(`/product/${product.slug}`),
          seller: { "@id": `${siteUrl}/#store` },
        },
      },
    })),
  };
}

export function jsonLdGraph(nodes: Array<Record<string, unknown>>) {
  return {
    "@context": "https://schema.org",
    "@graph": nodes,
  };
}
