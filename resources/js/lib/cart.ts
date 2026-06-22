export type CartProduct = {
  id: number;
  name: string;
  slug: string;
  brand?: string | null;
  price_cents: number;
  price: string;
  stock_quantity: number;
  unit_name?: string | null;
  image_url?: string | null;
};

export type CartLineItem = {
  id: number;
  quantity: number;
  line_total_cents: number;
  product: CartProduct;
};

export type AppliedCoupon = {
  code: string;
  discount_type: "fixed" | "percent";
  discount_value: number;
  discount_cents: number;
  total_cents: number;
};

export type CartData = {
  customer_uid?: string | null;
  sync_version?: number;
  cart_token: string | null;
  items: CartLineItem[];
  applied_coupon: AppliedCoupon | null;
  subtotal_cents: number;
  total_cents: number;
};

export const emptyCart: CartData = {
  customer_uid: null,
  sync_version: 0,
  cart_token: null,
  items: [],
  applied_coupon: null,
  subtotal_cents: 0,
  total_cents: 0,
};

export function formatCartMoney(valueInCents: number) {
  const safeValue = Number.isFinite(valueInCents) ? valueInCents : 0;

  return new Intl.NumberFormat("tr-TR", {
    style: "currency",
    currency: "TRY",
  }).format(safeValue / 100);
}

export function cartItemCount(items: CartLineItem[]) {
  return items.reduce((total, item) => total + item.quantity, 0);
}

export function normalizeCart(cart?: Partial<CartData> | null): CartData {
  const items = (cart?.items ?? []).map((item) => ({
    ...item,
    product: {
      ...item.product,
      image_url: productImageUrl(item.product?.image_url),
    },
  }));

  return {
    customer_uid: cart?.customer_uid ?? null,
    sync_version: cart?.sync_version ?? 0,
    cart_token: cart?.cart_token ?? null,
    items,
    applied_coupon: cart?.applied_coupon ?? null,
    subtotal_cents: cart?.subtotal_cents ?? 0,
    total_cents: cart?.total_cents ?? 0,
  };
}
import { productImageUrl } from "@/lib/media";
