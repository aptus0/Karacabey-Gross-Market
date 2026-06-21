import type { Metadata } from "next";
import Link from "next/link";
import { ArrowRight, CheckCircle2, Settings, Truck } from "lucide-react";
import { SeoHead } from "@/app/_components/SeoHead";
import { GuestLayout } from "@/app/_layouts/GuestLayout";
import {
  breadcrumbSchema,
  buildMetadata,
  itemListSchema,
  jsonLdGraph,
  serviceSchema,
  webPageSchema,
} from "@/lib/seo";
import { shippingCarriers } from "@/lib/shipping-policy";

export const metadata: Metadata = buildMetadata({
  title: "Kargo Entegrasyonları",
  description: "Karacabey Gross Market Aras Kargo, Yurtiçi Kargo, PTT Kargo ve DHL eCommerce entegrasyon planı.",
  path: "/kurumsal/kargo-entegrasyonlari",
  keywords: ["kargo entegrasyonu", "Aras Kargo entegrasyon", "Yurtiçi Kargo entegrasyon", "PTT Kargo", "DHL eCommerce"],
});

export default function CargoIntegrationsPage() {
  const breadcrumbItems = [
    { href: "/", label: "Ana Sayfa" },
    { href: "/kurumsal/kargo-entegrasyonlari", label: "Kargo Entegrasyonları" },
  ];
  const description = "Karacabey Gross Market Aras Kargo, Yurtiçi Kargo, PTT Kargo ve DHL eCommerce entegrasyon planı.";
  const schema = jsonLdGraph([
    webPageSchema({
      title: "Kargo Entegrasyonları",
      description,
      path: "/kurumsal/kargo-entegrasyonlari",
      breadcrumbs: breadcrumbItems,
    }),
    breadcrumbSchema(breadcrumbItems),
    serviceSchema({
      name: "E-ticaret Kargo Entegrasyonları",
      description,
      path: "/kurumsal/kargo-entegrasyonlari",
      serviceType: "E-commerce shipping integration",
    }),
    itemListSchema({
      name: "Kargo Entegrasyon Firmaları",
      description: "Karacabey Gross Market kargo operasyonunda planlanan taşıyıcı entegrasyonları.",
      path: "/kurumsal/kargo-entegrasyonlari",
      items: shippingCarriers.map((carrier) => ({
        name: carrier.name,
        url: "/kurumsal/kargo-entegrasyonlari",
      })),
    }),
  ]);

  return (
    <GuestLayout>
      <SeoHead data={schema} />
      <main className="content-band content-band--compact">
        <section className="mx-auto grid w-full max-w-[1120px] gap-5">
          <div className="cargo-integrations-hero">
            <Truck size={24} />
            <div>
              <p className="eyebrow">Kargo Operasyonu</p>
              <h1>Aras, Yurtiçi, PTT ve DHL eCommerce hazırlığı</h1>
              <p>Checkout ve panel tarafında kargo hesaplama, barkod/etiket, takip numarası ve gönderi durum senkronizasyonu için sade entegrasyon iskeleti.</p>
            </div>
          </div>

          <div className="grid gap-3 md:grid-cols-2">
            {shippingCarriers.map((carrier) => (
              <article key={carrier.code} className="cargo-integration-card">
                <Settings size={18} />
                <div>
                  <strong>{carrier.name}</strong>
                  <span>{carrier.description}</span>
                </div>
                <small>{carrier.eta}</small>
              </article>
            ))}
          </div>

          <section className="cargo-flow-card">
            <h2>Kurulum akışı</h2>
            <div className="grid gap-3 md:grid-cols-4">
              {["API bilgileri", "Kargo kuralı", "Etiket/barkod", "Takip senkronu"].map((item) => (
                <div key={item}><CheckCircle2 size={17} /><span>{item}</span></div>
              ))}
            </div>
            <Link className="primary-action" href="/kargo-hesaplama">Kargo Hesaplamaya Git <ArrowRight size={15} /></Link>
          </section>
        </section>
      </main>
    </GuestLayout>
  );
}
