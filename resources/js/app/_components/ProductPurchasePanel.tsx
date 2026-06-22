"use client";

import { useMemo, useState } from "react";
import { Minus, Plus, ShieldCheck } from "lucide-react";
import { AddToCartButton } from "@/app/_components/AddToCartButton";
import { FavoriteButton } from "@/app/_components/FavoriteButton";
import type { CartProduct } from "@/lib/cart";
import { cn } from "@/lib/utils";

type ProductPurchasePanelProps = {
  productSlug: string;
  productId?: number;
  stock?: number;
  optimisticProduct?: CartProduct;
};

function StockStatusLine({ outOfStock, label }: { outOfStock: boolean; label: string }) {
  return (
    <div className="kgm-product-buybox__stockline flex items-center justify-between gap-4 rounded-lg bg-slate-50 px-3 py-3">
      <span className="text-xs font-bold uppercase tracking-wide text-slate-500">Stok durumu</span>
      <strong
        className={cn(
          "kgm-product-stock inline-flex items-center rounded-full px-2.5 py-1 text-sm font-black",
          outOfStock
            ? "kgm-product-stock--muted bg-slate-200 text-slate-600"
            : "bg-orange-50 text-orange-600",
        )}
      >
        {label}
      </strong>
    </div>
  );
}

function QuantitySelector({
  quantity,
  maxQuantity,
  outOfStock,
  onDecrease,
  onIncrease,
}: {
  quantity: number;
  maxQuantity: number;
  outOfStock: boolean;
  onDecrease: () => void;
  onIncrease: () => void;
}) {
  return (
    <label className="kgm-product-buybox__quantity grid gap-2">
      <span className="text-xs font-bold uppercase tracking-wide text-slate-500">Adet</span>
      <div
        className="kgm-qty-mini grid h-12 grid-cols-[48px_minmax(64px,1fr)_48px] overflow-hidden rounded-lg border border-slate-200 bg-white"
        aria-label="Adet seçimi"
      >
        <button
          type="button"
          onClick={onDecrease}
          disabled={quantity <= 1}
          aria-label="Adeti azalt"
          className="inline-flex items-center justify-center text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-45"
        >
          <Minus size={14} strokeWidth={2.5} />
        </button>
        <strong className="inline-flex items-center justify-center border-x border-slate-200 text-base font-black text-slate-950">
          {quantity}
        </strong>
        <button
          type="button"
          onClick={onIncrease}
          disabled={quantity >= maxQuantity || outOfStock}
          aria-label="Adeti artır"
          className="inline-flex items-center justify-center text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-45"
        >
          <Plus size={14} strokeWidth={2.5} />
        </button>
      </div>
    </label>
  );
}

function SecureShoppingNote() {
  return (
    <div className="kgm-product-buybox__secure flex items-start gap-3 rounded-lg border border-emerald-100 bg-emerald-50 p-3 text-emerald-900">
      <ShieldCheck size={20} className="mt-0.5 shrink-0" />
      <div className="grid gap-0.5">
        <strong className="text-sm font-black">Güvenli Alışveriş</strong>
        <span className="text-xs font-semibold text-emerald-800">SSL sertifikası ile korunmaktadır.</span>
      </div>
    </div>
  );
}

export function ProductPurchasePanel({ productSlug, productId, stock = 99, optimisticProduct }: ProductPurchasePanelProps) {
  const [quantity, setQuantity] = useState(1);
  const safeStock = Math.max(0, stock);
  const maxQuantity = Math.min(99, Math.max(1, safeStock || 1));
  const outOfStock = safeStock <= 0;

  const stockLabel = useMemo(() => {
    if (outOfStock) return "Stok yok";
    if (safeStock <= 5) return `${safeStock} adet kaldı`;
    return "Stokta";
  }, [outOfStock, safeStock]);

  return (
    <aside
      className="kgm-product-buybox grid gap-4 rounded-lg border border-slate-200 bg-white p-4 shadow-sm lg:sticky lg:top-28"
      aria-label="Sepete ekleme alanı"
    >
      <StockStatusLine outOfStock={outOfStock} label={stockLabel} />

      <QuantitySelector
        quantity={quantity}
        maxQuantity={maxQuantity}
        outOfStock={outOfStock}
        onDecrease={() => setQuantity((current) => Math.max(1, current - 1))}
        onIncrease={() => setQuantity((current) => Math.min(maxQuantity, current + 1))}
      />

      <AddToCartButton
        productSlug={productSlug}
        productId={productId}
        optimisticProduct={optimisticProduct}
        quantity={quantity}
        className="kgm-product-buybox__button min-h-12 w-full rounded-lg bg-orange-600 text-base font-black text-white shadow-sm transition hover:bg-orange-700 focus:ring-2 focus:ring-orange-500/40 disabled:cursor-not-allowed disabled:opacity-60"
        disabled={outOfStock}
        label={outOfStock ? "Stok Yok" : "Sepete Ekle"}
        openSheetOnAdd={false}
      />

      <FavoriteButton
        productSlug={productSlug}
        className="kgm-product-favorite-wide min-h-11 w-full rounded-lg border border-orange-200 bg-white text-sm font-black text-orange-600 transition hover:bg-orange-50 focus:ring-2 focus:ring-orange-500/30"
        iconSize={17}
        label="Favorilere Ekle"
      />

      <SecureShoppingNote />
    </aside>
  );
}
