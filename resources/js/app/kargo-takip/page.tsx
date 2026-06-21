import type { Metadata } from "next";
import Link from "next/link";
import { PackageSearch, Search, UserRound } from "lucide-react";
import { SeoHead } from "@/app/_components/SeoHead";
import { GuestLayout } from "@/app/_layouts/GuestLayout";
import {
  breadcrumbSchema,
  buildMetadata,
  faqPageSchema,
  jsonLdGraph,
  serviceSchema,
  webPageSchema,
} from "@/lib/seo";

export const metadata: Metadata = buildMetadata({
  title: "Kargo Takip",
  description: "Karacabey Gross Market sipariş teslimat durumu, kargo takip yönlendirmesi ve sipariş destek ekranı.",
  path: "/kargo-takip",
  keywords: ["kargo takip", "sipariş takip", "teslimat durumu", "Karacabey Gross Market kargo"],
});

const faqItems = [
  {
    question: "Kargo takip numaramı nereden görebilirim?",
    answer: "Hesabım alanındaki sipariş detaylarında kargo takip ve teslimat durumu bilgilerini görüntüleyebilirsiniz.",
  },
  {
    question: "Siparişim hazırlanıyor görünüyorsa ne yapmalıyım?",
    answer: "Hazırlık aşamasındaki siparişler kargo firmasına teslim edildiğinde takip bilgisi güncellenir.",
  },
  {
    question: "Teslimat adresimi kontrol edebilir miyim?",
    answer: "Adreslerinizi hesabınızdan kontrol edebilir, yeni siparişler için güncel teslimat adresinizi seçebilirsiniz.",
  },
];

export default function KargoTakipPage() {
  const breadcrumbItems = [
    { href: "/", label: "Ana Sayfa" },
    { href: "/kargo-takip", label: "Kargo Takip" },
  ];
  const description = "Karacabey Gross Market sipariş teslimat durumu, kargo takip yönlendirmesi ve sipariş destek ekranı.";
  const schema = jsonLdGraph([
    webPageSchema({
      title: "Kargo Takip",
      description,
      path: "/kargo-takip",
      breadcrumbs: breadcrumbItems,
    }),
    breadcrumbSchema(breadcrumbItems),
    serviceSchema({
      name: "Sipariş ve Kargo Takip",
      description,
      path: "/kargo-takip",
      serviceType: "Order tracking support",
    }),
    faqPageSchema(faqItems),
  ]);

  return (
    <GuestLayout>
      <SeoHead data={schema} />
      <main className="kgm-page-shell kgm-page-shell--small">
        <section className="kgm-compact-panel">
          <div className="kgm-section-title kgm-section-title--compact">
            <span><PackageSearch size={15} /> Kargo Takip</span>
            <h1>Siparişinizin teslimat durumunu takip edin</h1>
            <p>
              Kargo takip bilgileri siparişiniz hazırlandıktan sonra hesap alanındaki sipariş detayında
              güncellenir. Teslimat süreci, seçilen kargo firması ve adres uygunluğuna göre ilerler.
            </p>
          </div>

          <div className="grid gap-3 sm:grid-cols-2">
            <Link className="primary-action" href="/account#orders">
              <UserRound size={16} />
              Siparişlerime Git
            </Link>
            <Link className="secondary-action" href="/products">
              <Search size={16} />
              Ürünleri İncele
            </Link>
          </div>
        </section>

        <section className="kgm-compact-panel">
          <div className="kgm-section-title kgm-section-title--compact">
            <span><PackageSearch size={15} /> Sık Sorulanlar</span>
            <h2>Kargo takip desteği</h2>
          </div>
          <div className="kgm-public-page__sections">
            {faqItems.map((item) => (
              <article key={item.question} className="kgm-public-page__section">
                <h3>{item.question}</h3>
                <p>{item.answer}</p>
              </article>
            ))}
          </div>
        </section>
      </main>
    </GuestLayout>
  );
}
