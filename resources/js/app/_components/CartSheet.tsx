"use client";

import Link from "next/link";
import { useEffect } from "react";
import {
  ArrowRight,
  Loader2,
  Lock,
  Minus,
  Plus,
  ShoppingBag,
  Sparkles,
  Trash2,
  Truck,
} from "lucide-react";
import { CouponInput } from "@/app/_components/CouponInput";
import {
  Sheet,
  SheetContent,
  SheetTitle,
} from "@/app/_components/ui/sheet";
import { cartItemCount, formatCartMoney } from "@/lib/cart";
import { useCartStore } from "@/lib/cart-store";
import { FREE_SHIPPING_CENTS } from "@/lib/shipping-policy";
import { track } from "@/lib/tracking";
import { productImageUrl } from "@/lib/media";

export function CartSheet() {
  const isOpen = useCartStore((state) => state.isSheetOpen);
  const items = useCartStore((state) => state.items);
  const openSheet = useCartStore((state) => state.openSheet);
  const closeSheet = useCartStore((state) => state.closeSheet);
  const subtotal = useCartStore((state) => state.subtotal_cents);
  const total = useCartStore((state) => state.total_cents);
  const appliedCoupon = useCartStore((state) => state.applied_coupon);
  const status = useCartStore((state) => state.status);
  const error = useCartStore((state) => state.error);
  const pendingOutboxCount = useCartStore((state) => state.pendingOutboxCount);
  const flushPendingCart = useCartStore((state) => state.flushPendingMutations);
  const updateItemQuantity = useCartStore((state) => state.updateItemQuantity);
  const removeItem = useCartStore((state) => state.removeItem);
  const applyCoupon = useCartStore((state) => state.applyCoupon);
  const clearCoupon = useCartStore((state) => state.removeCoupon);
  const clearCart = useCartStore((state) => state.clearCart);
  const count = useCartStore((state) => cartItemCount(state.items));

  const isEmpty = items.length === 0;
  const isBusy = status === "updating";
  const discountCents = appliedCoupon?.discount_cents ?? 0;
  const isServiceWaiting = Boolean(error && /bekleyin|yoğun/i.test(error));
  const hasPendingSync = pendingOutboxCount > 0;

  const freeShippingProgress = Math.min(
    100,
    Math.round((subtotal / FREE_SHIPPING_CENTS) * 100),
  );
  const freeShippingRemaining = Math.max(0, FREE_SHIPPING_CENTS - subtotal);
  const freeShippingReached = subtotal >= FREE_SHIPPING_CENTS;

  useEffect(() => {
    if (!isOpen) return;
    track("view_cart", {
      item_count: count,
      subtotal_cents: subtotal,
      total_cents: total,
      has_coupon: Boolean(appliedCoupon),
    });
  }, [appliedCoupon, count, isOpen, subtotal, total]);

  return (
    <Sheet open={isOpen} onOpenChange={(open) => (open ? openSheet() : closeSheet())}>
      <SheetContent
        side="right"
        hideClose
        className="kgm-luxury-drawer"
        aria-describedby={undefined}
      >
        <SheetTitle className="sr-only">Sepetiniz</SheetTitle>
        <header className="kgm-luxury-drawer__header">
          <div className="kgm-luxury-drawer__title">
            <span className="kgm-luxury-drawer__title-badge" aria-hidden="true">
              <ShoppingBag size={16} strokeWidth={2.25} />
            </span>
            <div>
              <h2>Sepetiniz</h2>
              <p>
                {isEmpty
                  ? "Henüz ürün eklemediniz"
                  : `${count} ürün · ${formatCartMoney(subtotal)}`}
              </p>
            </div>
          </div>
          <button
            type="button"
            onClick={closeSheet}
            className="kgm-luxury-drawer__close"
            aria-label="Sepeti kapat"
          >
            <svg
              width="14"
              height="14"
              viewBox="0 0 14 14"
              fill="none"
              xmlns="http://www.w3.org/2000/svg"
            >
              <path
                d="M1 1L13 13M13 1L1 13"
                stroke="currentColor"
                strokeWidth="1.8"
                strokeLinecap="round"
              />
            </svg>
          </button>
        </header>

        {!isEmpty ? (
          <div
            className={`kgm-luxury-drawer__progress ${
              freeShippingReached ? "is-reached" : ""
            }`}
          >
            <div className="kgm-luxury-drawer__progress-row">
              <span className="kgm-luxury-drawer__progress-icon" aria-hidden="true">
                {freeShippingReached ? (
                  <Sparkles size={14} strokeWidth={2.25} />
                ) : (
                  <Truck size={14} strokeWidth={2.25} />
                )}
              </span>
              <p>
                {freeShippingReached ? (
                  <>
                    Tebrikler! <strong>Ücretsiz kargo</strong> kazandınız.
                  </>
                ) : (
                  <>
                    <strong>{formatCartMoney(freeShippingRemaining)}</strong> daha
                    eklerseniz <strong>kargo bedava</strong>.
                  </>
                )}
              </p>
            </div>
            <div
              className="kgm-luxury-drawer__progress-track"
              role="progressbar"
              aria-valuenow={freeShippingProgress}
              aria-valuemin={0}
              aria-valuemax={100}
            >
              <div
                className="kgm-luxury-drawer__progress-fill"
                style={{ width: `${freeShippingProgress}%` }}
              />
            </div>
          </div>
        ) : null}

        {hasPendingSync ? (
          <div className="kgm-luxury-drawer__notice is-waiting" role="status" aria-live="polite">
            <span className="kgm-luxury-drawer__notice-icon" aria-hidden="true">
              <Loader2 size={14} strokeWidth={2.5} className="animate-spin" />
            </span>
            <p>Sepetiniz cihazda korundu. Bağlantı hazır olunca otomatik eşitlenecek.</p>
            <button type="button" onClick={() => void flushPendingCart().catch(() => undefined)}>Tekrar dene</button>
          </div>
        ) : null}

        {error ? (
          <div
            className={`kgm-luxury-drawer__notice ${
              isServiceWaiting ? "is-waiting" : "is-error"
            }`}
            role="status"
            aria-live="polite"
          >
            <span className="kgm-luxury-drawer__notice-icon" aria-hidden="true">
              {isServiceWaiting ? (
                <Loader2 size={14} strokeWidth={2.5} className="animate-spin" />
              ) : (
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                  <path d="M7 1L13 12H1L7 1Z" stroke="currentColor" strokeWidth="1.6" strokeLinejoin="round" />
                  <path d="M7 6V8.5" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" />
                  <circle cx="7" cy="10.5" r="0.8" fill="currentColor" />
                </svg>
              )}
            </span>
            <p>{error}</p>
          </div>
        ) : null}

        <div className="kgm-luxury-drawer__body">
          {isEmpty ? (
            <div className="flex h-full flex-col items-center justify-center space-y-7 px-6 text-center">
              <div className="flex h-28 w-28 items-center justify-center rounded-full bg-slate-50 text-slate-300 ring-1 ring-slate-100 shadow-[0_2px_10px_-3px_rgba(0,0,0,0.05)]">
                <ShoppingBag size={44} strokeWidth={1.5} />
              </div>
              <div className="space-y-2.5">
                <h3 className="text-xl font-semibold tracking-tight text-slate-900">Sepetiniz Boş</h3>
                <p className="mx-auto max-w-xs text-sm leading-relaxed text-slate-500">
                  İhtiyacınız olan ürünler bir tık uzağınızda. Karacabey Gross seçkisini hemen keşfedin.
                </p>
              </div>
              <Link
                href="/products"
                onClick={closeSheet}
                className="group relative flex w-[85%] items-center justify-center gap-2 overflow-hidden rounded-xl bg-orange-600 px-4 py-3.5 text-sm font-semibold text-white shadow-md transition-all hover:bg-orange-700 hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-orange-600/30 active:scale-[0.98] active:shadow-sm"
              >
                <span>Ürünleri Keşfet</span>
                <ArrowRight size={16} className="transition-transform group-hover:translate-x-1" />
              </Link>
            </div>
          ) : (
            <ul className="kgm-luxury-drawer__items">
              {items.map((item) => {
                const unitPriceCents =
                  item.quantity > 0
                    ? Math.round(item.line_total_cents / item.quantity)
                    : item.product.price_cents;
                const imageUrl = productImageUrl(item.product.image_url);
                const isLocalPendingItem = item.id < 0;
                const rowDisabled = isBusy || isLocalPendingItem || hasPendingSync;
                return (
                  <li key={item.id} className="kgm-luxury-line">
                    <div className="kgm-luxury-line__media">
                      {imageUrl ? (
                        // eslint-disable-next-line @next/next/no-img-element
                        <img
                          src={imageUrl}
                          alt={item.product.name}
                          loading="lazy"
                        />
                      ) : (
                        <ShoppingBag size={22} strokeWidth={1.5} />
                      )}
                    </div>
                    <div className="kgm-luxury-line__body">
                      <div className="kgm-luxury-line__top">
                        <div className="kgm-luxury-line__info">
                          <span className="kgm-luxury-line__brand">
                            {item.product.brand ?? "Karacabey Gross"}
                          </span>
                          <h4 className="kgm-luxury-line__name">
                            {item.product.name}
                          </h4>
                          <span className="kgm-luxury-line__unit">
                            {formatCartMoney(unitPriceCents)} / {item.product.unit_name?.trim() || "adet"}
                          </span>
                        </div>
                        <button
                          type="button"
                          onClick={() => void removeItem(item.id).catch(() => undefined)}
                          disabled={rowDisabled}
                          className="kgm-luxury-line__remove"
                          aria-label={`${item.product.name} ürününü kaldır`}
                          title="Ürünü sepetten kaldır"
                        >
                          <Trash2 size={14} strokeWidth={2} />
                        </button>
                      </div>
                      <div className="kgm-luxury-line__bottom">
                        <div className="kgm-luxury-qty">
                          <button
                            type="button"
                            onClick={() =>
                              void updateItemQuantity(item.id, item.quantity - 1).catch(
                                () => undefined,
                              )
                            }
                            disabled={rowDisabled || item.quantity <= 1}
                            aria-label="Adeti azalt"
                          >
                            <Minus size={13} strokeWidth={2.5} />
                          </button>
                          <span aria-live="polite">{item.quantity}</span>
                          <button
                            type="button"
                            onClick={() =>
                              void updateItemQuantity(item.id, item.quantity + 1).catch(
                                () => undefined,
                              )
                            }
                            disabled={rowDisabled}
                            aria-label="Adeti artır"
                          >
                            <Plus size={13} strokeWidth={2.5} />
                          </button>
                        </div>
                        <strong className="kgm-luxury-line__total">
                          {formatCartMoney(item.line_total_cents)}
                        </strong>
                      </div>
                    </div>
                  </li>
                );
              })}

              {items.length > 1 ? (
                <li className="kgm-luxury-drawer__clear-row">
                  <button
                    type="button"
                    onClick={() => void clearCart().catch(() => undefined)}
                    disabled={isBusy}
                    className="kgm-luxury-drawer__clear"
                  >
                    <Trash2 size={13} strokeWidth={2} />
                    Sepeti temizle
                  </button>
                </li>
              ) : null}
            </ul>
          )}
        </div>

        {!isEmpty ? (
          <footer className="kgm-luxury-drawer__footer">
            <div className="kgm-luxury-drawer__coupon">
              <CouponInput
                appliedCoupon={appliedCoupon}
                onApply={applyCoupon}
                onRemove={clearCoupon}
                disabled={isBusy}
              />
            </div>

            <div className="kgm-luxury-drawer__totals">
              <div className="kgm-luxury-drawer__totals-line">
                <span>Ara toplam</span>
                <strong>{formatCartMoney(subtotal)}</strong>
              </div>
              {discountCents > 0 ? (
                <div className="kgm-luxury-drawer__totals-line is-discount">
                  <span>İndirim</span>
                  <strong>-{formatCartMoney(discountCents)}</strong>
                </div>
              ) : null}
              <div className="kgm-luxury-drawer__totals-grand">
                <span>Toplam</span>
                <strong>{formatCartMoney(total)}</strong>
              </div>
              <p className="kgm-luxury-drawer__totals-note">
                Kargo ve vergiler ödeme sayfasında hesaplanır.
              </p>
            </div>

            <Link
              href={hasPendingSync ? "/sepet" : "/checkout"}
              onClick={closeSheet}
              aria-disabled={hasPendingSync}
              className={`kgm-luxury-drawer__checkout${hasPendingSync ? " is-disabled" : ""}`}
            >
              <span>{hasPendingSync ? "Sepet eşitleniyor" : "Ödemeye geç"}</span>
              <span className="kgm-luxury-drawer__checkout-amount">
                {formatCartMoney(total)}
              </span>
              <ArrowRight size={16} strokeWidth={2.4} />
            </Link>

            <div className="kgm-luxury-drawer__footer-row">
              <Link
                href="/sepet"
                onClick={closeSheet}
                className="kgm-luxury-drawer__view-cart"
              >
                Sepete git
              </Link>
              <button
                type="button"
                onClick={closeSheet}
                className="kgm-luxury-drawer__continue"
              >
                Alışverişe devam et
              </button>
            </div>

            <div className="kgm-luxury-drawer__trust">
              <Lock size={12} strokeWidth={2.25} />
              <span>256-bit SSL · PayTR güvenli ödeme · Hızlı iade</span>
            </div>
          </footer>
        ) : null}
      </SheetContent>
    </Sheet>
  );
}
