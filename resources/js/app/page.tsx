import type { Metadata } from "next";
import { HomeCommerceExperience } from "@/app/_components/HomeCommerceExperience";
import { MobileCatalogRedirect } from "@/app/_components/MobileCatalogRedirect";
import { SeoHead } from "@/app/_components/SeoHead";
import { GuestLayout } from "@/app/_layouts/GuestLayout";
import {
  buildMetadata,
  groceryStoreSchema,
  itemListSchema,
  jsonLdGraph,
  organizationSchema,
  webPageSchema,
  websiteSchema,
} from "@/lib/seo";
import {
  fetchFeaturedStorefrontProducts,
  fetchStorefrontCategories,
} from "@/lib/storefront-products";
import { fetchHomepageBlocks } from "@/lib/homepage";

export const dynamic = "force-dynamic";
export const revalidate = 0;
export const metadata: Metadata = buildMetadata({
  title: "Karacabey Gross Market — Karacabey & Bursa Online Market",
  description:
    "Karacabey Gross Market: Karacabey ve Bursa'nın online gross marketi. Drama Mahallesi Runguçpaşa Caddesi mağazamızdan taze ürünler, şarküteri, kuruyemiş, temizlik ve daha fazlası — kapınıza hızlı teslimat, güvenli online ödeme. Tel: 0224 676 84 33",
  path: "/",
  keywords: [
    "karacabeygrossmarket",
    "karacabey gross market",
    "Karacabey online market",
    "Bursa market siparişi",
    "online market teslimat",
    "gross market Karacabey",
    "hızlı market teslimatı",
    "taze ürün sipariş",
    "market kampanyaları",
    "şarküteri online",
    "kuruyemiş sipariş",
    "temizlik ürünleri market",
    "Drama Mahallesi market",
    "Karacabey Bursa market",
  ],
});

export default async function Home() {
  const [featuredProducts, categories, homepageBlocks] = await Promise.all([
    fetchFeaturedStorefrontProducts(12),
    fetchStorefrontCategories(),
    fetchHomepageBlocks(),
  ]);
  const jsonLd = jsonLdGraph([
    organizationSchema(),
    groceryStoreSchema(),
    websiteSchema(),
    webPageSchema({
      title: "Karacabey Gross Market | Online Market Siparişi",
      description:
        "Karacabey ve Bursa'nın online gross marketi. Taze ürünler, şarküteri, kuruyemiş, temizlik ve daha fazlası — kapınıza hızlı teslimat, güvenli online ödeme.",
      path: "/",
    }),
    itemListSchema({
      name: "Öne Çıkan Ürünler",
      description: "Karacabey Gross Market ana sayfasında öne çıkan kampanyalı ve indirimli ürünler.",
      path: "/",
      items: featuredProducts.map((product) => ({
        name: product.name,
        url: `/product/${product.slug}`,
      })),
    }),
    itemListSchema({
      name: "Market Reyonları",
      description: "Karacabey Gross Market alışveriş reyonları: gıda, içecek, şarküteri, temizlik ve daha fazlası.",
      path: "/kategoriler",
      items: categories.map((category) => ({
        name: category.name,
        url: `/kategori/${category.slug}`,
      })),
    }),
  ]);

  return (
    <GuestLayout>
      <MobileCatalogRedirect />
      <SeoHead data={jsonLd} />
      <HomeCommerceExperience
        categories={categories}
        featuredProducts={featuredProducts}
        homepageBlocks={homepageBlocks}
      />
    </GuestLayout>
  );
}
