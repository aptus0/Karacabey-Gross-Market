"use client";

import { Check, Minus, Plus, ShoppingCart } from "lucide-react";
import { useEffect, useState } from "react";
import { Button } from "@/app/_components/ui/button";
import { cn } from "@/lib/utils";
import { extractErrorMessage } from "@/lib/api";
import { useCartStore } from "@/lib/cart-store";
import type { CartProduct } from "@/lib/cart";
import { track } from "@/lib/tracking";

type AddToCartButtonProps = {
  productSlug: string;
  productId?: number;
  quantity?: number;
  className?: string;
  label?: string;
  compact?: boolean;
  openSheetOnAdd?: boolean;
  disabled?: boolean;
  optimisticProduct?: CartProduct;
};

export function AddToCartButton({
  productSlug,
  productId,
  quantity = 1,
  className,
  label = "Sepete Ekle",
  compact = false,
  openSheetOnAdd = false,
  disabled = false,
  optimisticProduct,
}: AddToCartButtonProps) {
  const addItem = useCartStore((state) => state.addItem);
  const addItemBySlug = useCartStore((state) => state.addItemBySlug);
  const updateItemQuantity = useCartStore((state) => state.updateItemQuantity);
  const removeItem = useCartStore((state) => state.removeItem);
  const items = useCartStore((state) => state.items);

  const [isPending, setIsPending] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState(false);

  const cartItem = items.find((item) => (
    typeof productId === "number"
      ? item.product?.id === productId
      : item.product?.slug === productSlug
  ));
  const cartQty = cartItem?.quantity ?? 0;
  const cartItemId = cartItem?.id ?? null;
  const unitLabel = (cartItem?.product.unit_name ?? optimisticProduct?.unit_name)?.trim() || "adet";
  const inCart = cartQty > 0;

  useEffect(() => {
    if (!success) return;
    const timer = setTimeout(() => setSuccess(false), 1500);
    return () => clearTimeout(timer);
  }, [success]);

  async function handleAddToCart() {
    setError(null);
    setIsPending(true);

    const trackAdd = () => {
      track("add_to_cart", {
        product_slug: productSlug,
        product_id: productId ?? null,
        quantity,
        placement: compact ? "product_card" : "product_detail",
      }, {
        product_id: productId ?? productSlug,
      });
    };

    try {
      if (optimisticProduct) {
        const syncPromise = addItem(optimisticProduct, quantity, { openSheet: openSheetOnAdd });
        setSuccess(true);
        setIsPending(false);
        trackAdd();

        void syncPromise.catch((caughtError) => {
          setSuccess(false);
          setError(extractErrorMessage(caughtError, "Ürün sepete eklenemedi."));
        });

        return;
      }

      await addItemBySlug(productSlug, quantity, { openSheet: openSheetOnAdd }, productId);
      trackAdd();
      setSuccess(true);
    } catch (caughtError) {
      setError(extractErrorMessage(caughtError, "Ürün sepete eklenemedi."));
    } finally {
      setIsPending(false);
    }
  }

  async function handleIncrease() {
    if (!cartItemId) return;
    setIsPending(true);
    try {
      if (cartItemId < 0) {
        if (optimisticProduct) {
          await addItem(optimisticProduct, 1, { openSheet: false });
        } else {
          await addItemBySlug(productSlug, 1, { openSheet: false }, productId);
        }
      } else {
        await updateItemQuantity(cartItemId, cartQty + 1);
      }
    } catch {
      // sessiz
    } finally {
      setIsPending(false);
    }
  }

  async function handleDecrease() {
    if (!cartItemId) return;
    setIsPending(true);
    try {
      if (cartQty <= 1) {
        await removeItem(cartItemId);
      } else {
        await updateItemQuantity(cartItemId, cartQty - 1);
      }
    } catch {
      // sessiz
    } finally {
      setIsPending(false);
    }
  }

  // ── Compact mode (inside card overlay) ───────────────────────────────────
  if (compact) {
    return (
      <button
        type="button"
        onClick={handleAddToCart}
        disabled={isPending || success || disabled}
        className={cn("product-card__add-btn", inCart && "product-card__add-btn--in-cart", disabled && "opacity-60 cursor-not-allowed", className)}
      >
        {success ? (
          <>
            <Check size={14} />
            Eklendi
          </>
        ) : inCart ? (
          <>
            <Check size={14} />
            Sepette ({cartQty})
          </>
        ) : isPending ? (
          <>
            <ShoppingCart size={14} className="animate-bounce" />
            Ekleniyor…
          </>
        ) : (
          <>
            <ShoppingCart size={14} />
            {label}
          </>
        )}
      </button>
    );
  }

  // ── Full mode (product detail, checkout summary etc.) ─────────────────────
  if (inCart) {
    return (
      <div className={cn("grid gap-2", className)}>
        <div className="flex h-11 items-center overflow-hidden rounded-xl border border-[#FF7A00] bg-[#FFF5EA]">
          <button
            type="button"
            onClick={handleDecrease}
            disabled={isPending || disabled}
            className="flex h-full w-11 shrink-0 items-center justify-center text-[#FF7A00] transition hover:bg-[#FFE4C4] disabled:opacity-50"
            aria-label="Azalt"
          >
            <Minus size={16} />
          </button>
          <span className="flex-1 text-center text-sm font-black text-[#2B2F36]">
            {isPending ? "…" : cartQty}
          </span>
          <button
            type="button"
            onClick={handleIncrease}
            disabled={isPending || disabled}
            className="flex h-full w-11 shrink-0 items-center justify-center text-[#FF7A00] transition hover:bg-[#FFE4C4] disabled:opacity-50"
            aria-label="Artır"
          >
            <Plus size={16} />
          </button>
        </div>
        <p className="text-center text-xs font-semibold text-[#FF7A00]">Sepette {cartQty} {unitLabel}</p>
      </div>
    );
  }

  return (
    <div className="grid gap-2">
      <Button
        type="button"
        className={cn(
          "h-11 rounded-xl transition-all duration-300",
          success ? "bg-green-500 hover:bg-green-500" : "",
          className,
        )}
        onClick={handleAddToCart}
        disabled={isPending || success || disabled}
      >
        {success ? (
          <>
            <Check size={17} />
            Eklendi!
          </>
        ) : isPending ? (
          <>
            <ShoppingCart size={17} className="animate-bounce" />
            Ekleniyor…
          </>
        ) : (
          <>
            <ShoppingCart size={17} />
            {label}
          </>
        )}
      </Button>
      {error ? <p className="text-xs font-semibold text-[#A32A18]">{error}</p> : null}
    </div>
  );
}
