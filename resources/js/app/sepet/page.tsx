import type { Metadata } from "next";
import Link from "next/link";
import { ArrowRight, BadgeCheck, Clock3, CreditCard, PackageCheck, ShieldCheck, Truck } from "lucide-react";
import { CheckoutSummary } from "@/app/_components/CheckoutSummary";
import { AppLayout } from "@/app/_layouts/AppLayout";
import { buildMetadata } from "@/lib/seo";

export const metadata: Metadata = buildMetadata({
  title: "Sepet",
  description: "Karacabey Gross Market sepet sayfası.",
  path: "/sepet",
  robots: { index: false, follow: false },
});

export default function CartPage() {
  const assuranceItems = [
    { icon: <PackageCheck size={18} />, label: "Canlı stok", text: "Sepetteki adetler sunucuda tekrar doğrulanır." },
    { icon: <Truck size={18} />, label: "Teslimat", text: "Adres ve kargo seçimi ödeme adımında netleşir." },
    { icon: <ShieldCheck size={18} />, label: "Güvenli ödeme", text: "PayTR ve 3D Secure destekli checkout." },
  ];

  return (
    <AppLayout>
      <main className="kgm-cart-page">
        <section className="kgm-cart-page__head">
          <div>
            <span>Sepet operasyonu</span>
            <h1>Siparişini netleştir, güvenli ödeme adımına geç.</h1>
            <p>Ürün adetlerini, paketleri ve kuponunu burada kontrol et. Stok ve fiyatlar ödeme öncesinde sunucuda yeniden doğrulanır.</p>
          </div>
          <div className="kgm-cart-page__head-actions">
            <Link href="/products" className="kgm-cart-page__secondary-link">Alışverişe devam et</Link>
            <Link href="/checkout" className="kgm-cart-page__primary-link">
              Ödemeye geç
              <ArrowRight size={16} />
            </Link>
          </div>
        </section>

        <section className="kgm-cart-assurance" aria-label="Sepet güvence adımları">
          {assuranceItems.map((item) => (
            <div key={item.label}>
              <span aria-hidden="true">{item.icon}</span>
              <strong>{item.label}</strong>
              <p>{item.text}</p>
            </div>
          ))}
        </section>

        <div className="kgm-cart-page__grid">
          <CheckoutSummary
            editable
            title="Sepetteki ürünler"
            description="Paket, adet ve indirim bilgilerini ödeme öncesinde son kez kontrol edin."
          />
          <aside className="kgm-cart-next-card">
            <div className="kgm-cart-next-card__top">
              <span><CreditCard size={18} /></span>
              <div>
                <h2>Sonraki adım</h2>
                <p>Adres, teslimat ve ödeme bilgilerini tamamlayıp siparişi güvenle oluştur.</p>
              </div>
            </div>

            <Link href="/checkout" className="kgm-cart-next-card__checkout">
              Ödemeye devam et
              <ArrowRight size={16} />
            </Link>

            <div className="kgm-cart-next-card__checks" aria-label="Checkout kontrol listesi">
              <span><BadgeCheck size={15} /> KDV dahil fiyatlar</span>
              <span><ShieldCheck size={15} /> Güvenli ödeme</span>
              <span><Clock3 size={15} /> Hızlı sipariş akışı</span>
            </div>

            <div className="kgm-cart-next-card__links">
              <Link href="/kargo-hesaplama">Kargo hesaplama</Link>
              <Link href="/teslimat-bolgeleri">Teslimat bölgeleri</Link>
              <Link href="/kampanyalar">Kampanyalar</Link>
            </div>
          </aside>
        </div>
      </main>
    </AppLayout>
  );
}
