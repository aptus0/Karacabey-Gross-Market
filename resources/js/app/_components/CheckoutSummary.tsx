"use client";

import { useState } from "react";
import Image from "next/image";
import Link from "next/link";
import { ChevronDown, Minus, Plus, ShoppingBag, Trash2 } from "lucide-react";
import { CouponInput } from "@/app/_components/CouponInput";
import { Button } from "@/app/_components/ui/button";
import { formatCartMoney, type CartLineItem } from "@/lib/cart";
import { useCartStore } from "@/lib/cart-store";
import { cn } from "@/lib/utils";

type CheckoutSummaryProps = {
  items?: CartLineItem[];
  title?: string;
  description?: string;
  editable?: boolean;
  className?: string;
  shippingCents?: number;
  shippingCarrierName?: string;
};

export function CheckoutSummary({
  items,
  title = "Sepet",
  description,
  editable = false,
  className,
  shippingCents,
  shippingCarrierName,
}: CheckoutSummaryProps) {
  const [isOpen, setIsOpen] = useState(false);
  const storeItems = useCartStore((state) => state.items);
  const appliedCoupon = useCartStore((state) => state.applied_coupon);
  const subtotal = useCartStore((state) => state.subtotal_cents);
  const total = useCartStore((state) => state.total_cents);
  const status = useCartStore((state) => state.status);
  const error = useCartStore((state) => state.error);
  const pendingOutboxCount = useCartStore((state) => state.pendingOutboxCount);
  const flushPendingCart = useCartStore((state) => state.flushPendingMutations);
  const updateItemQuantity = useCartStore((state) => state.updateItemQuantity);
  const removeItem = useCartStore((state) => state.removeItem);
  const applyCoupon = useCartStore((state) => state.applyCoupon);
  const clearCoupon = useCartStore((state) => state.removeCoupon);

  const resolvedItems = items ?? storeItems;
  const resolvedCoupon = items ? null : appliedCoupon;
  const resolvedSubtotal = items ? items.reduce((sum, item) => sum + item.line_total_cents, 0) : subtotal;
  const discountCents = resolvedCoupon?.discount_cents ?? 0;
  const resolvedTotal = items ? resolvedSubtotal : total;

  // Calculate dynamic grand total including shipping
  const dynamicTotal = resolvedTotal + (shippingCents ?? 0);

  async function handleQuantityChange(itemId: number, quantity: number) {
    await updateItemQuantity(itemId, quantity).catch(() => undefined);
  }

  async function handleItemRemoval(itemId: number) {
    await removeItem(itemId).catch(() => undefined);
  }

  if (resolvedItems.length === 0) {
    return (
      <section className={cn("kgm-cart-empty", className)}>
        <ShoppingBag size={22} />
        <h2>Sepet boş</h2>
        <p>Ürün ekleyerek devam edebilirsin.</p>
        <Link href="/products">Ürünleri keşfet</Link>
      </section>
    );
  }

  return (
    <section className={cn("kgm-cart-summary-wrapper", className)}>
      {/* Mobile Toggle Bar */}
      <button
        type="button"
        className="mobile-summary-toggle"
        onClick={() => setIsOpen(!isOpen)}
        aria-expanded={isOpen}
      >
        <span className="mobile-summary-toggle__left">
          <ShoppingBag size={18} />
          <span>{isOpen ? "Siparişi Gizle" : "Siparişi Göster"}</span>
          <ChevronDown size={16} className={cn("mobile-summary-toggle__chevron", isOpen && "is-open")} />
        </span>
        <strong className="mobile-summary-toggle__total">
          {formatCartMoney(dynamicTotal)}
        </strong>
      </button>

      {/* Main summary body */}
      <div className={cn("kgm-cart-summary-body", isOpen ? "is-open" : "is-collapsed")}>
        <div className="kgm-cart-summary__head">
          <h2>{title}</h2>
          {description ? <p>{description}</p> : null}
        </div>

        <ul className="kgm-cart-lines">
          {resolvedItems.map((item) => (
            <li key={item.id} className="kgm-cart-row">
              <div className="kgm-cart-row__image">
                {item.product.image_url ? (
                  <Image
                    src={item.product.image_url}
                    alt={item.product.name}
                    fill
                    sizes="52px"
                    className="object-contain"
                  />
                ) : (
                  <ShoppingBag size={18} />
                )}
              </div>

              <div className="kgm-cart-row__main">
                <div>
                  <h3>{item.product.name}</h3>
                  <span>
                    {item.product.brand ?? "Karacabey Gross"} · {item.product.unit_name?.trim() || "adet"}
                  </span>
                </div>
                <strong>{formatCartMoney(item.line_total_cents)}</strong>
              </div>

              {editable ? (
                <div className="kgm-cart-row__controls">
                  <div className="kgm-cart-row__actions">
                    <button
                      type="button"
                      onClick={() => void handleQuantityChange(item.id, item.quantity - 1)}
                      disabled={status === "updating" || item.quantity <= 1}
                      aria-label="Adeti azalt"
                    >
                      <Minus size={12} strokeWidth={2.5} />
                    </button>
                    <span aria-live="polite">{item.quantity}</span>
                    <button
                      type="button"
                      onClick={() => void handleQuantityChange(item.id, item.quantity + 1)}
                      disabled={status === "updating"}
                      aria-label="Adeti artır"
                    >
                      <Plus size={12} strokeWidth={2.5} />
                    </button>
                  </div>
                  <button
                    type="button"
                    onClick={() => void handleItemRemoval(item.id)}
                    disabled={status === "updating"}
                    aria-label="Ürünü kaldır"
                    className="kgm-cart-row__remove"
                    title="Ürünü sepetten kaldır"
                  >
                    <Trash2 size={12} />
                  </button>
                </div>
              ) : (
                <span className="kgm-cart-row__qty">{item.quantity} adet</span>
              )}
            </li>
          ))}
        </ul>

        <div className="kgm-cart-totalbox">
          {editable && !items ? (
            <CouponInput
              appliedCoupon={resolvedCoupon}
              onApply={applyCoupon}
              onRemove={clearCoupon}
              disabled={status === "updating"}
            />
          ) : null}

          <div className="kgm-cart-totalbox__line">
            <span>Ara toplam</span>
            <strong>{formatCartMoney(resolvedSubtotal)}</strong>
          </div>
          {discountCents > 0 ? (
            <div className="kgm-cart-totalbox__line kgm-cart-totalbox__line--discount">
              <span>İndirim</span>
              <strong>-{formatCartMoney(discountCents)}</strong>
            </div>
          ) : null}
          
          {shippingCents !== undefined ? (
            <div className="kgm-cart-totalbox__line kgm-cart-totalbox__shipping">
              <span>Kargo ({shippingCarrierName || "Kargo"})</span>
              <strong className={cn(shippingCents === 0 && "shipping-free-label")}>
                {shippingCents === 0 ? "Ücretsiz" : formatCartMoney(shippingCents)}
              </strong>
            </div>
          ) : null}
          
          <div className="kgm-cart-totalbox__grand">
            <span>Genel Toplam</span>
            <strong>{formatCartMoney(dynamicTotal)}</strong>
          </div>
        </div>

        {editable && pendingOutboxCount > 0 ? (
          <div className="kgm-cart-sync-notice" role="status" aria-live="polite">
            <strong>Sepet eşitleniyor</strong>
            <span>Bağlantı hazır olunca bekleyen işlemler otomatik kaydedilecek.</span>
            <button type="button" onClick={() => void flushPendingCart().catch(() => undefined)}>Tekrar dene</button>
          </div>
        ) : null}

        {editable ? (
          <Button
            type="button"
            variant="ghost"
            className="kgm-cart-clear"
            onClick={() => useCartStore.getState().clearCart()}
            disabled={status === "updating"}
          >
            Sepeti temizle
          </Button>
        ) : null}

        {error ? <p className="kgm-cart-error">{error}</p> : null}
      </div>
    </section>
  );
}
