import type { Metadata } from "next";
import Link from "next/link";
import { ExternalLink, Truck } from "lucide-react";
import { CargoCalculator } from "@/app/_components/CargoCalculator";
import { SeoHead } from "@/app/_components/SeoHead";
import { GuestLayout } from "@/app/_layouts/GuestLayout";
import {
  breadcrumbSchema,
  buildMetadata,
  itemListSchema,
  jsonLdGraph,
  serviceSchema,
  webApplicationSchema,
  webPageSchema,
} from "@/lib/seo";
import { shippingCarriers } from "@/lib/shipping-policy";

export const metadata: Metadata = buildMetadata({
  title: "Kargo Hesaplama",
  description: "Aras, Yurtiçi, PTT ve DHL eCommerce için yaklaşık kargo hesaplama ekranı.",
  path: "/kargo-hesaplama",
  keywords: ["kargo hesaplama", "Yurtiçi Kargo fiyat hesaplama", "Aras Kargo", "PTT Kargo", "DHL eCommerce"],
});

export default function CargoCalculatorPage() {
  const breadcrumbItems = [
    { href: "/", label: "Ana Sayfa" },
    { href: "/kargo-hesaplama", label: "Kargo Hesaplama" },
  ];
  const description = "Aras, Yurtiçi, PTT ve DHL eCommerce için yaklaşık kargo hesaplama ekranı.";
  const schema = jsonLdGraph([
    webPageSchema({
      title: "Kargo Hesaplama",
      description,
      path: "/kargo-hesaplama",
      breadcrumbs: breadcrumbItems,
    }),
    breadcrumbSchema(breadcrumbItems),
    webApplicationSchema({
      name: "Karacabey Gross Market Kargo Hesaplama",
      description,
      path: "/kargo-hesaplama",
    }),
    serviceSchema({
      name: "Online Kargo Hesaplama",
      description,
      path: "/kargo-hesaplama",
      serviceType: "Shipping cost estimation",
    }),
    itemListSchema({
      name: "Desteklenen Kargo Firmaları",
      description: "Karacabey Gross Market kargo hesaplama ekranında listelenen taşıyıcılar.",
      path: "/kargo-hesaplama",
      items: shippingCarriers.map((carrier) => ({
        name: carrier.name,
        url: "/kurumsal/kargo-entegrasyonlari",
      })),
    }),
  ]);

  return (
    <GuestLayout>
      <SeoHead data={schema} />
      <main className="kgm-page-shell kgm-page-shell--small">
        <CargoCalculator />

        <section className="kgm-compact-panel">
          <div className="kgm-section-title kgm-section-title--compact">
            <span><Truck size={15} /> Kargo Firmaları</span>
            <h2>Entegrasyonlar</h2>
          </div>
          <div className="kgm-carrier-grid">
            {shippingCarriers.map((carrier) => (
              <article key={carrier.code}>
                <strong>{carrier.name}</strong>
                <span>{carrier.eta}</span>
              </article>
            ))}
          </div>
          <Link href="https://www.yurticikargo.com/tr/online-servisler/fiyat-hesapla" target="_blank" rel="noreferrer" className="kgm-text-link">
            Yurtiçi fiyat hesaplama <ExternalLink size={14} />
          </Link>
        </section>
      </main>
    </GuestLayout>
  );
}
