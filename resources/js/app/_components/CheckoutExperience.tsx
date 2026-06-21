"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { ArrowLeft, ArrowRight, PackageCheck, ReceiptText, Search, ShieldCheck, ShoppingBasket, Truck } from "lucide-react";
import { CheckoutForm } from "@/app/_components/CheckoutForm";
import { CheckoutSummary } from "@/app/_components/CheckoutSummary";
import { KgmLogo } from "@/app/_components/KgmLogo";
import { PaymentBrandStrip } from "@/app/_components/PaymentBrandStrip";
// cart store is used below for items/subtotal
import { useCartStore } from "@/lib/cart-store";
import { track } from "@/lib/tracking";

export function CheckoutExperience() {
  const items = useCartStore((state) => state.items);
  const subtotalCents = useCartStore((state) => state.subtotal_cents);
  const totalCents = useCartStore((state) => state.total_cents);
  const cartToken = useCartStore((state) => state.cart_token);
  const isHydrated = useCartStore((state) => state.isHydrated);
  const storageReady = useCartStore((state) => state.storageReady);
  const status = useCartStore((state) => state.status);
  const appliedCoupon = useCartStore((state) => state.applied_coupon);
  const initializeCart = useCartStore((state) => state.initialize);

  // States to hold selected shipping quote details
  const [shippingCents, setShippingCents] = useState<number>(0);
  const [shippingCarrierName, setShippingCarrierName] = useState<string>("");
  // payableCents is computed in CheckoutSummary

  useEffect(() => {
    if (storageReady && !isHydrated) {
      initializeCart().catch(() => undefined);
    }
  }, [initializeCart, isHydrated, storageReady]);

  useEffect(() => {
    if (!isHydrated || items.length === 0) return;
    track("begin_checkout", {
      item_count: items.length,
      subtotal_cents: subtotalCents,
      total_cents: totalCents,
      has_coupon: Boolean(appliedCoupon),
    });
  }, [appliedCoupon, isHydrated, items.length, subtotalCents, totalCents]);

  if (isHydrated && items.length === 0) {
    return (
      <main className="kgm-checkout-stripe kgm-checkout-stripe--v2 kgm-checkout-stripe--empty kgm-checkout-page--empty">
        <section className="kgm-checkout-empty">
          <div className="kgm-checkout-empty__content">
            <KgmLogo variant="header" className="kgm-checkout-empty__brand" />
            <span className="kgm-checkout-empty__eyebrow"><ShoppingBasket size={15} /> Sepet hazır değil</span>
            <h1>Checkout&apos;a başlamadan önce sepetini dolduralım.</h1>
            <p>
              Sepetinde ürün olmadığında ödeme adımını kilitli tutuyoruz. Ürünleri keşfedip sepete ekledikten sonra
              teslimat, kargo ve PayTR güvenli ödeme adımları burada açılır.
            </p>
            <div className="kgm-checkout-empty__actions">
              <Link className="primary-action" href="/products">
                <Search size={16} /> Ürünlere git <ArrowRight size={16} />
              </Link>
              <Link className="secondary-action" href="/kampanyalar">Kampanyalara bak</Link>
            </div>
          </div>

          <div className="kgm-checkout-empty__panel" aria-label="Checkout özeti">
            <div className="kgm-checkout-empty__cart">
              <span><PackageCheck size={18} /></span>
              <div>
                <strong>Sepet bekleniyor</strong>
                <small>Ürün eklenince sipariş özeti otomatik hazırlanır.</small>
              </div>
            </div>
            <ul>
              <li><ShieldCheck size={16} /> PayTR + 3D Secure ödeme</li>
              <li><Truck size={16} /> Karacabey teslimat ve kargo seçimi</li>
              <li><ReceiptText size={16} /> Fatura bilgileri tek ekranda</li>
            </ul>
            <PaymentBrandStrip />
          </div>
        </section>
      </main>
    );
  }

  return (
    <main className="min-h-screen bg-slate-50">
      <header className="border-b border-slate-200 bg-white">
        <div className="mx-auto flex max-w-6xl items-center justify-between px-4 py-3 sm:px-6">
          <KgmLogo variant="header" className="h-7 w-auto text-slate-900" />
          <Link href="/sepet" className="flex items-center gap-1.5 text-xs font-semibold text-slate-600 transition-colors hover:text-orange-600">
            <ArrowLeft size={14} /> Sepete dön
          </Link>
        </div>
      </header>

      <div className="mx-auto grid max-w-6xl grid-cols-1 gap-5 px-3 py-4 sm:px-6 sm:py-6 lg:grid-cols-[minmax(0,1fr)_390px] lg:gap-7">
        <h1 className="sr-only">Ödeme ve Sipariş</h1>

        <section aria-labelledby="summary-heading" className="order-1 lg:order-2">
          <div className="lg:sticky lg:top-5">
            <div className="mb-3 hidden items-center justify-between lg:flex">
              <h2 id="summary-heading" className="text-sm font-semibold text-slate-900">Sipariş özeti</h2>
              <span className="text-xs text-slate-500">{items.length} kalem</span>
            </div>

            <div>
              <CheckoutSummary
                editable
                title="Sipariş detayları"
                description="Sepetinizdeki ürünler ve kargo durumu"
                shippingCents={shippingCents}
                shippingCarrierName={shippingCarrierName}
              />
            </div>

            <div className="mt-3 grid gap-2 rounded-xl border border-slate-200 bg-white p-3 text-xs text-slate-600 shadow-sm">
              <div className="flex items-center gap-2">
                <ShieldCheck size={15} className="shrink-0 text-green-600" />
                <span>PayTR ve 3D Secure koruması</span>
              </div>
              <div className="flex items-center gap-2">
                <Truck size={15} className="shrink-0 text-slate-400" />
                <span>Seçtiğin kargo ile hızlı gönderim</span>
              </div>
              <div className="flex items-center gap-2">
                <ReceiptText size={15} className="shrink-0 text-slate-400" />
                <span>E-fatura e-posta adresine gönderilir</span>
              </div>
            </div>

            <div className="mt-3">
              <PaymentBrandStrip />
            </div>
          </div>
        </section>

        <section aria-labelledby="payment-heading" className="order-2 pb-16 lg:order-1">
          <div className="mb-4">
            <span className="text-xs font-semibold uppercase tracking-wider text-orange-600">Güvenli checkout</span>
            <h2 id="payment-heading" className="mt-1 text-xl font-bold tracking-tight text-slate-950">Teslimat bilgilerini tamamla</h2>
            <p className="mt-1 text-sm text-slate-500">Bilgilerini kontrol et, ardından PayTR ile ödemeye geç.</p>
          </div>
            <CheckoutForm
              items={items.map((item) => ({ productId: item.product.id, quantity: item.quantity }))}
              subtotalCents={subtotalCents}
              cartToken={cartToken}
              couponCode={appliedCoupon?.code ?? null}
              disabled={status === "loading"}
              onShippingChange={(cents, name) => {
                setShippingCents(cents);
                setShippingCarrierName(name);
              }}
            />
        </section>
      </div>
    </main>
  );
}
