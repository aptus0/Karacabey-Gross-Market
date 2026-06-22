"use client";

import Link from "next/link";
import { useEffect, type ReactNode } from "react";
import {
  AlertTriangle,
  ArrowRight,
  Loader2,
  Lock,
  Minus,
  Plus,
  ShoppingBag,
  Sparkles,
  Trash2,
  Truck,
  X,
} from "lucide-react";
import { CouponInput } from "@/app/_components/CouponInput";
import {
  Sheet,
  SheetContent,
  SheetTitle,
} from "@/app/_components/ui/sheet";
import {
  cartItemCount,
  formatCartMoney,
  type AppliedCoupon,
  type CartLineItem,
} from "@/lib/cart";
import { useCartStore } from "@/lib/cart-store";
import { productImageUrl } from "@/lib/media";
import { FREE_SHIPPING_CENTS } from "@/lib/shipping-policy";
import { track } from "@/lib/tracking";
import { cn } from "@/lib/utils";

type AsyncVoid = () => Promise<unknown> | void;
type QuantityUpdater = (itemId: number, quantity: number) => Promise<unknown> | void;
type ItemRemover = (itemId: number) => Promise<unknown> | void;

function CartSheetHeader({
  isEmpty,
  count,
  subtotal,
  onClose,
}: {
  isEmpty: boolean;
  count: number;
  subtotal: number;
  onClose: () => void;
}) {
  return (
    <header className="kgm-luxury-drawer__header flex shrink-0 items-center justify-between gap-4 border-b border-slate-200 bg-white px-5 py-4">
      <div className="kgm-luxury-drawer__title flex min-w-0 items-center gap-3">
        <span
          className="kgm-luxury-drawer__title-badge inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-orange-600 text-white shadow-sm"
          aria-hidden="true"
        >
          <ShoppingBag size={18} strokeWidth={2.25} />
        </span>
        <div className="min-w-0">
          <h2 className="truncate text-xl font-black tracking-tight text-slate-950">Sepetiniz</h2>
          <p className="mt-0.5 truncate text-sm font-semibold text-slate-500">
            {isEmpty ? "Henüz ürün eklemediniz" : `${count} ürün - ${formatCartMoney(subtotal)}`}
          </p>
        </div>
      </div>
      <button
        type="button"
        onClick={onClose}
        className="kgm-luxury-drawer__close inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-orange-500/30"
        aria-label="Sepeti kapat"
      >
        <X size={18} />
      </button>
    </header>
  );
}

function FreeShippingMeter({ subtotal }: { subtotal: number }) {
  const progress = Math.min(100, Math.round((subtotal / FREE_SHIPPING_CENTS) * 100));
  const remaining = Math.max(0, FREE_SHIPPING_CENTS - subtotal);
  const reached = subtotal >= FREE_SHIPPING_CENTS;

  return (
    <section
      className={cn(
        "kgm-luxury-drawer__progress m-4 rounded-lg border px-4 py-3",
        reached
          ? "is-reached border-emerald-200 bg-emerald-50 text-emerald-900"
          : "border-orange-100 bg-orange-50 text-orange-900",
      )}
    >
      <div className="kgm-luxury-drawer__progress-row flex items-center gap-3">
        <span
          className="kgm-luxury-drawer__progress-icon inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white text-orange-600 shadow-sm"
          aria-hidden="true"
        >
          {reached ? <Sparkles size={15} strokeWidth={2.25} /> : <Truck size={15} strokeWidth={2.25} />}
        </span>
        <p className="text-sm font-semibold leading-5">
          {reached ? (
            <>
              Tebrikler! <strong>Ücretsiz kargo</strong> kazandınız.
            </>
          ) : (
            <>
              <strong>{formatCartMoney(remaining)}</strong> daha eklerseniz{" "}
              <strong>kargo bedava</strong>.
            </>
          )}
        </p>
      </div>
      <div
        className="kgm-luxury-drawer__progress-track mt-3 h-2 overflow-hidden rounded-full bg-white/80"
        role="progressbar"
        aria-valuenow={progress}
        aria-valuemin={0}
        aria-valuemax={100}
      >
        <div
          className="kgm-luxury-drawer__progress-fill h-full rounded-full bg-orange-500 transition-[width] duration-300"
          style={{ width: `${progress}%` }}
        />
      </div>
    </section>
  );
}

function CartNotice({
  tone,
  icon,
  children,
  actionLabel,
  onAction,
}: {
  tone: "waiting" | "error";
  icon: ReactNode;
  children: ReactNode;
  actionLabel?: string;
  onAction?: AsyncVoid;
}) {
  return (
    <div
      className={cn(
        "kgm-luxury-drawer__notice mx-4 mb-3 flex items-center gap-3 rounded-lg border px-4 py-3 text-sm font-semibold",
        tone === "waiting"
          ? "is-waiting border-orange-200 bg-orange-50 text-orange-900"
          : "is-error border-red-200 bg-red-50 text-red-700",
      )}
      role="status"
      aria-live="polite"
    >
      <span
        className="kgm-luxury-drawer__notice-icon inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white shadow-sm"
        aria-hidden="true"
      >
        {icon}
      </span>
      <p className="min-w-0 flex-1 leading-5">{children}</p>
      {actionLabel && onAction ? (
        <button
          type="button"
          onClick={() => void onAction()}
          className="shrink-0 rounded-lg border border-orange-200 bg-white px-3 py-2 text-xs font-black text-orange-700 transition hover:bg-orange-50 focus:outline-none focus:ring-2 focus:ring-orange-500/30"
        >
          {actionLabel}
        </button>
      ) : null}
    </div>
  );
}

function CartEmptyState({ onClose }: { onClose: () => void }) {
  return (
    <div className="flex h-full flex-col items-center justify-center gap-7 px-6 py-12 text-center">
      <div className="flex h-24 w-24 items-center justify-center rounded-full bg-slate-50 text-slate-300 ring-1 ring-slate-100">
        <ShoppingBag size={40} strokeWidth={1.5} />
      </div>
      <div className="grid gap-2">
        <h3 className="text-xl font-black tracking-tight text-slate-950">Sepetiniz boş</h3>
        <p className="mx-auto max-w-xs text-sm font-medium leading-6 text-slate-500">
          İhtiyacınız olan ürünler bir tık uzağınızda. Karacabey Gross seçkisini hemen keşfedin.
        </p>
      </div>
      <Link
        href="/products"
        onClick={onClose}
        className="inline-flex min-h-12 w-full max-w-xs items-center justify-center gap-2 rounded-lg bg-orange-600 px-4 text-sm font-black text-white shadow-sm transition hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-500/40"
      >
        Ürünleri keşfet
        <ArrowRight size={16} />
      </Link>
    </div>
  );
}

function CartLineRow({
  item,
  disabled,
  onUpdateQuantity,
  onRemove,
}: {
  item: CartLineItem;
  disabled: boolean;
  onUpdateQuantity: QuantityUpdater;
  onRemove: ItemRemover;
}) {
  const unitPriceCents =
    item.quantity > 0
      ? Math.round(item.line_total_cents / item.quantity)
      : item.product.price_cents;
  const imageUrl = productImageUrl(item.product.image_url);

  return (
    <li className="kgm-luxury-line grid grid-cols-[76px_minmax(0,1fr)] gap-3 rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
      <div className="kgm-luxury-line__media flex h-[76px] w-[76px] items-center justify-center overflow-hidden rounded-lg bg-slate-50 text-slate-300 ring-1 ring-slate-100">
        {imageUrl ? (
          // eslint-disable-next-line @next/next/no-img-element
          <img
            src={imageUrl}
            alt={item.product.name}
            loading="lazy"
            className="h-full w-full object-contain p-1"
          />
        ) : (
          <ShoppingBag size={22} strokeWidth={1.5} />
        )}
      </div>
      <div className="kgm-luxury-line__body grid min-w-0 gap-3">
        <div className="kgm-luxury-line__top flex items-start justify-between gap-3">
          <div className="kgm-luxury-line__info min-w-0">
            <span className="kgm-luxury-line__brand block truncate text-[11px] font-black uppercase tracking-wide text-orange-600">
              {item.product.brand ?? "Karacabey Gross Market"}
            </span>
            <h4 className="kgm-luxury-line__name mt-1 line-clamp-2 text-sm font-black leading-5 text-slate-950">
              {item.product.name}
            </h4>
            <span className="kgm-luxury-line__unit mt-1 block text-xs font-semibold text-slate-500">
              {formatCartMoney(unitPriceCents)} / {item.product.unit_name?.trim() || "adet"}
            </span>
          </div>
          <button
            type="button"
            onClick={() => void onRemove(item.id)}
            disabled={disabled}
            className="kgm-luxury-line__remove inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-slate-400 transition hover:bg-red-50 hover:text-red-600 disabled:cursor-not-allowed disabled:opacity-45"
            aria-label={`${item.product.name} ürününü kaldır`}
            title="Ürünü sepetten kaldır"
          >
            <Trash2 size={15} strokeWidth={2} />
          </button>
        </div>
        <div className="kgm-luxury-line__bottom flex items-center justify-between gap-3">
          <div className="kgm-luxury-qty grid h-10 grid-cols-[36px_44px_36px] overflow-hidden rounded-lg border border-slate-200 bg-white">
            <button
              type="button"
              onClick={() => void onUpdateQuantity(item.id, item.quantity - 1)}
              disabled={disabled || item.quantity <= 1}
              className="inline-flex items-center justify-center text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-45"
              aria-label="Adeti azalt"
            >
              <Minus size={13} strokeWidth={2.5} />
            </button>
            <span className="inline-flex items-center justify-center border-x border-slate-200 text-sm font-black text-slate-950" aria-live="polite">
              {item.quantity}
            </span>
            <button
              type="button"
              onClick={() => void onUpdateQuantity(item.id, item.quantity + 1)}
              disabled={disabled}
              className="inline-flex items-center justify-center text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-45"
              aria-label="Adeti artır"
            >
              <Plus size={13} strokeWidth={2.5} />
            </button>
          </div>
          <strong className="kgm-luxury-line__total whitespace-nowrap text-base font-black text-slate-950">
            {formatCartMoney(item.line_total_cents)}
          </strong>
        </div>
      </div>
    </li>
  );
}

function CartItemsList({
  items,
  rowDisabled,
  isBusy,
  onUpdateQuantity,
  onRemove,
  onClear,
}: {
  items: CartLineItem[];
  rowDisabled: (item: CartLineItem) => boolean;
  isBusy: boolean;
  onUpdateQuantity: QuantityUpdater;
  onRemove: ItemRemover;
  onClear: AsyncVoid;
}) {
  return (
    <ul className="kgm-luxury-drawer__items grid gap-3 p-4">
      {items.map((item) => (
        <CartLineRow
          key={item.id}
          item={item}
          disabled={rowDisabled(item)}
          onUpdateQuantity={onUpdateQuantity}
          onRemove={onRemove}
        />
      ))}

      {items.length > 1 ? (
        <li className="kgm-luxury-drawer__clear-row flex justify-end">
          <button
            type="button"
            onClick={() => void onClear()}
            disabled={isBusy}
            className="kgm-luxury-drawer__clear inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-black text-slate-600 transition hover:border-red-200 hover:bg-red-50 hover:text-red-600 disabled:cursor-not-allowed disabled:opacity-45"
          >
            <Trash2 size={13} strokeWidth={2} />
            Sepeti temizle
          </button>
        </li>
      ) : null}
    </ul>
  );
}

function CartSummaryFooter({
  subtotal,
  total,
  discountCents,
  appliedCoupon,
  isBusy,
  hasPendingSync,
  onApplyCoupon,
  onClearCoupon,
  onClose,
}: {
  subtotal: number;
  total: number;
  discountCents: number;
  appliedCoupon: AppliedCoupon | null;
  isBusy: boolean;
  hasPendingSync: boolean;
  onApplyCoupon: (code: string) => Promise<unknown> | void;
  onClearCoupon: () => Promise<unknown> | void;
  onClose: () => void;
}) {
  return (
    <footer className="kgm-luxury-drawer__footer mt-auto grid shrink-0 gap-3 border-t border-slate-200 bg-white p-4">
      <div className="kgm-luxury-drawer__coupon">
        <CouponInput
          appliedCoupon={appliedCoupon}
          onApply={onApplyCoupon}
          onRemove={onClearCoupon}
          disabled={isBusy}
        />
      </div>

      <div className="kgm-luxury-drawer__totals grid gap-2 rounded-lg bg-slate-50 p-4">
        <div className="kgm-luxury-drawer__totals-line flex items-center justify-between gap-3 text-sm font-semibold text-slate-600">
          <span>Ara toplam</span>
          <strong className="text-slate-950">{formatCartMoney(subtotal)}</strong>
        </div>
        {discountCents > 0 ? (
          <div className="kgm-luxury-drawer__totals-line is-discount flex items-center justify-between gap-3 text-sm font-semibold text-emerald-700">
            <span>İndirim</span>
            <strong>-{formatCartMoney(discountCents)}</strong>
          </div>
        ) : null}
        <div className="kgm-luxury-drawer__totals-grand flex items-center justify-between gap-3 border-t border-slate-200 pt-2">
          <span className="text-sm font-black text-slate-950">Toplam</span>
          <strong className="text-2xl font-black tracking-tight text-slate-950">{formatCartMoney(total)}</strong>
        </div>
        <p className="kgm-luxury-drawer__totals-note text-xs font-semibold text-slate-500">
          Kargo ve vergiler ödeme sayfasında hesaplanır.
        </p>
      </div>

      <Link
        href={hasPendingSync ? "/sepet" : "/checkout"}
        onClick={onClose}
        aria-disabled={hasPendingSync}
        className={cn(
          "kgm-luxury-drawer__checkout flex min-h-12 items-center justify-center gap-3 rounded-lg px-4 text-sm font-black text-white shadow-sm transition focus:outline-none focus:ring-2 focus:ring-orange-500/40",
          hasPendingSync
            ? "is-disabled bg-orange-400"
            : "bg-orange-600 hover:bg-orange-700",
        )}
      >
        <span>{hasPendingSync ? "Sepet eşitleniyor" : "Ödemeye geç"}</span>
        <span className="kgm-luxury-drawer__checkout-amount rounded-md bg-white/15 px-2 py-1">
          {formatCartMoney(total)}
        </span>
        <ArrowRight size={16} strokeWidth={2.4} />
      </Link>

      <div className="kgm-luxury-drawer__footer-row grid grid-cols-2 gap-2">
        <Link
          href="/sepet"
          onClick={onClose}
          className="kgm-luxury-drawer__view-cart inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-200 bg-white px-3 text-sm font-black text-slate-700 transition hover:bg-slate-50"
        >
          Sepete git
        </Link>
        <button
          type="button"
          onClick={onClose}
          className="kgm-luxury-drawer__continue inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-200 bg-white px-3 text-sm font-black text-slate-700 transition hover:bg-slate-50"
        >
          Alışverişe devam et
        </button>
      </div>

      <div className="kgm-luxury-drawer__trust flex items-center justify-center gap-2 text-xs font-bold text-slate-400">
        <Lock size={12} strokeWidth={2.25} />
        <span>256-bit SSL - PayTR güvenli ödeme - Hızlı iade</span>
      </div>
    </footer>
  );
}

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

  useEffect(() => {
    if (!isOpen) return;
    track("view_cart", {
      item_count: count,
      subtotal_cents: subtotal,
      total_cents: total,
      has_coupon: Boolean(appliedCoupon),
    });
  }, [appliedCoupon, count, isOpen, subtotal, total]);

  const rowDisabled = (item: CartLineItem) => {
    const isLocalPendingItem = item.id < 0;
    return isBusy || isLocalPendingItem || hasPendingSync;
  };

  return (
    <Sheet open={isOpen} onOpenChange={(open) => (open ? openSheet() : closeSheet())}>
      <SheetContent
        side="right"
        hideClose
        className="kgm-luxury-drawer w-full max-w-[460px] overflow-hidden border-l border-slate-200 bg-white shadow-2xl"
        aria-describedby={undefined}
      >
        <SheetTitle className="sr-only">Sepetiniz</SheetTitle>
        <CartSheetHeader
          isEmpty={isEmpty}
          count={count}
          subtotal={subtotal}
          onClose={closeSheet}
        />

        {!isEmpty ? <FreeShippingMeter subtotal={subtotal} /> : null}

        {hasPendingSync ? (
          <CartNotice
            tone="waiting"
            icon={<Loader2 size={14} strokeWidth={2.5} className="animate-spin" />}
            actionLabel="Tekrar dene"
            onAction={() => flushPendingCart().catch(() => undefined)}
          >
            Sepetiniz cihazda korundu. Bağlantı hazır olunca otomatik eşitlenecek.
          </CartNotice>
        ) : null}

        {error ? (
          <CartNotice
            tone={isServiceWaiting ? "waiting" : "error"}
            icon={
              isServiceWaiting ? (
                <Loader2 size={14} strokeWidth={2.5} className="animate-spin" />
              ) : (
                <AlertTriangle size={15} strokeWidth={2.3} />
              )
            }
          >
            {error}
          </CartNotice>
        ) : null}

        <div className="kgm-luxury-drawer__body min-h-0 flex-1 overflow-y-auto bg-slate-50/70">
          {isEmpty ? (
            <CartEmptyState onClose={closeSheet} />
          ) : (
            <CartItemsList
              items={items}
              rowDisabled={rowDisabled}
              isBusy={isBusy}
              onUpdateQuantity={(itemId, quantity) => updateItemQuantity(itemId, quantity).catch(() => undefined)}
              onRemove={(itemId) => removeItem(itemId).catch(() => undefined)}
              onClear={() => clearCart().catch(() => undefined)}
            />
          )}
        </div>

        {!isEmpty ? (
          <CartSummaryFooter
            subtotal={subtotal}
            total={total}
            discountCents={discountCents}
            appliedCoupon={appliedCoupon}
            isBusy={isBusy}
            hasPendingSync={hasPendingSync}
            onApplyCoupon={applyCoupon}
            onClearCoupon={clearCoupon}
            onClose={closeSheet}
          />
        ) : null}
      </SheetContent>
    </Sheet>
  );
}
