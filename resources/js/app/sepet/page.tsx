import type { Metadata } from "next";
import Link from "next/link";
import { ArrowRight, BadgeCheck, CreditCard, PackageCheck, ShieldCheck, ShoppingCart, Truck } from "lucide-react";
import { CheckoutSummary } from "@/app/_components/CheckoutSummary";
import { AppLayout } from "@/app/_layouts/AppLayout";
import { formatCartMoney } from "@/lib/cart";
import { buildMetadata } from "@/lib/seo";
import { FREE_SHIPPING_CENTS } from "@/lib/shipping-policy";

export const metadata: Metadata = buildMetadata({
  title: "Sepet",
  description: "Karacabey Gross Market sepet sayfası.",
  path: "/sepet",
  robots: { index: false, follow: false },
});

export default function CartPage() {
  return (
    <AppLayout>
      <main className="kgm-cart-page kgm-cart-v3 kgm-cart-stable">
        <section className="kgm-cart-stable__topbar kgm-cart-v3__topbar">
          <div>
            <span><ShoppingCart size={15} /> Sepet</span>
            <h1>Siparişini netleştir.</h1>
            <p>Ürünleri ve kuponu hızlıca kontrol et. Ödeme öncesinde stok, fiyat ve teslimat bilgisi sunucuda tekrar doğrulanır.</p>
          </div>
          <div className="kgm-cart-v3__badges" aria-label="Sepet avantajları">
            <span><Truck size={15} /> {formatCartMoney(FREE_SHIPPING_CENTS)} üzeri ücretsiz kargo</span>
            <span><ShieldCheck size={15} /> PayTR güvenli ödeme</span>
          </div>
        </section>

        <div className="kgm-cart-v3__grid">
          <CheckoutSummary
            editable
            title="Ürünler"
            description="Sepetteki ürünleri hızlıca güncelle."
            className="kgm-cart-summary--v3"
          />

          <aside className="kgm-cart-stable__side kgm-cart-v3__side" aria-label="Ödeme özeti">
            <div className="kgm-cart-v3__side-head">
              <span><CreditCard size={18} /></span>
              <div>
                <h2>Sonraki adım</h2>
                <p>Adres ve teslimat bilgisini tamamlayıp güvenli ödemeye geç.</p>
              </div>
            </div>

            <Link href="/checkout" className="kgm-cart-v3__checkout">
              Ödemeye devam et
              <ArrowRight size={16} />
            </Link>

            <div className="kgm-cart-v3__checks">
              <span><PackageCheck size={15} /> Stok ödeme öncesi doğrulanır</span>
              <span><BadgeCheck size={15} /> KDV dahil fiyatlar</span>
              <span><Truck size={15} /> {formatCartMoney(FREE_SHIPPING_CENTS)} üzeri kargo ücretsiz</span>
              <span><ShieldCheck size={15} /> 3D Secure destekli ödeme</span>
            </div>

            <div className="kgm-cart-v3__links">
              <Link href="/kargo-hesaplama">Kargo hesaplama</Link>
              <Link href="/teslimat-bolgeleri">Teslimat bölgeleri</Link>
              <Link href="/products">Alışverişe devam et</Link>
            </div>
          </aside>
        </div>
      </main>
    </AppLayout>
  );
}
