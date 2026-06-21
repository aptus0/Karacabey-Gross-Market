"use client";

import Link from "next/link";
import { ArrowRight, ShoppingBasket, Trash2 } from "lucide-react";
import { cartItemCount, formatCartMoney } from "@/lib/cart";
import { useCartStore } from "@/lib/cart-store";

export function HomeCartSummary() {
  const items = useCartStore((state) => state.items);
  const totalCents = useCartStore((state) => state.total_cents);
  const removeItem = useCartStore((state) => state.removeItem);
  const count = cartItemCount(items);
  const previewItems = items.slice(0, 3);

  return (
    <aside className="kgm-home-cart-card hidden xl:block">
      <div className="mb-3 flex items-center justify-between gap-3">
        <div>
          <p className="text-[11px] font-black uppercase tracking-[0.16em] text-orange-600">Sepetim</p>
          <h3 className="mt-1 text-base font-black text-slate-950">{count} ürün</h3>
        </div>
        <span className="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-orange-50 text-orange-600 ring-1 ring-orange-100">
          <ShoppingBasket size={18} />
        </span>
      </div>

      {previewItems.length > 0 ? (
        <div className="grid gap-2.5">
          {previewItems.map((item) => (
            <div key={item.id} className="kgm-home-cart-line">
              <div className="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-orange-50 ring-1 ring-orange-100">
                {item.product?.image_url ? (
                  // eslint-disable-next-line @next/next/no-img-element
                  <img src={item.product.image_url} alt={item.product.name} className="h-full w-full object-cover" />
                ) : (
                  <span className="text-[10px] font-black text-orange-700">KGM</span>
                )}
              </div>
              <div className="min-w-0 flex-1">
                <p className="truncate text-xs font-black text-slate-900">{item.product?.name ?? "Ürün"}</p>
                <p className="text-[11px] font-bold text-slate-500">{item.quantity} adet · {formatCartMoney(item.line_total_cents)}</p>
              </div>
              <button
                type="button"
                className="rounded-md p-1.5 text-slate-400 transition hover:bg-orange-50 hover:text-orange-600"
                aria-label="Sepetten kaldır"
                onClick={() => void removeItem(item.id)}
              >
                <Trash2 size={14} />
              </button>
            </div>
          ))}
        </div>
      ) : (
        <div className="rounded-lg border border-dashed border-orange-200 bg-orange-50/60 p-3 text-xs font-bold leading-5 text-slate-600">
          Sepetin şu an boş. Ürün eklediğinde burada görünecek.
        </div>
      )}

      <div className="mt-4 border-t border-slate-100 pt-4">
        <div className="flex items-center justify-between text-sm font-bold text-slate-500">
          <span>Toplam</span>
          <strong className="text-lg font-black text-slate-950">{formatCartMoney(totalCents)}</strong>
        </div>
        <Link
          href={count > 0 ? "/checkout" : "/products"}
          className="mt-3 inline-flex h-11 w-full items-center justify-center gap-2 rounded-lg bg-orange-600 text-sm font-black text-white transition hover:bg-orange-700"
        >
          {count > 0 ? "Sepete Git" : "Alışverişe Başla"}
          <ArrowRight size={16} />
        </Link>
      </div>
    </aside>
  );
}
