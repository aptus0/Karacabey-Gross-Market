"use client";

import { useMemo, useState } from "react";
import { Minus, Plus, ShieldCheck } from "lucide-react";
import { AddToCartButton } from "@/app/_components/AddToCartButton";
import { FavoriteButton } from "@/app/_components/FavoriteButton";
import type { CartProduct } from "@/lib/cart";

type ProductPurchasePanelProps = {
  productSlug: string;
  productId?: number;
  stock?: number;
  optimisticProduct?: CartProduct;
};

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
    <aside className="kgm-product-buybox" aria-label="Sepete ekleme alanı">
      <div className="kgm-product-buybox__stockline">
        <span>Stok durumu</span>
        <strong className={outOfStock ? "kgm-product-stock kgm-product-stock--muted" : "kgm-product-stock"}>
          {stockLabel}
        </strong>
      </div>

      <label className="kgm-product-buybox__quantity">
        <span>Adet</span>
        <div className="kgm-qty-mini" aria-label="Adet seçimi">
          <button type="button" onClick={() => setQuantity((current) => Math.max(1, current - 1))} disabled={quantity <= 1} aria-label="Adeti azalt">
            <Minus size={14} strokeWidth={2.5} />
          </button>
          <strong>{quantity}</strong>
          <button type="button" onClick={() => setQuantity((current) => Math.min(maxQuantity, current + 1))} disabled={quantity >= maxQuantity || outOfStock} aria-label="Adeti artır">
            <Plus size={14} strokeWidth={2.5} />
          </button>
        </div>
      </label>

      <AddToCartButton
        productSlug={productSlug}
        productId={productId}
        optimisticProduct={optimisticProduct}
        quantity={quantity}
        className="kgm-product-buybox__button"
        disabled={outOfStock}
        label={outOfStock ? "Stok Yok" : "Sepete Ekle"}
        openSheetOnAdd={false}
      />

      <FavoriteButton
        productSlug={productSlug}
        className="kgm-product-favorite-wide"
        iconSize={17}
        label="Favorilere Ekle"
      />

      <div className="kgm-product-buybox__secure">
        <ShieldCheck size={20} />
        <div>
          <strong>Güvenli Alışveriş</strong>
          <span>SSL sertifikası ile korunmaktadır.</span>
        </div>
      </div>
    </aside>
  );
}
