import type { KgmCartItem } from "@/lib/catalog";
import { formatPrice } from "@/lib/catalog";

type CartItemProps = {
  item: KgmCartItem;
};

export function CartItem({ item }: CartItemProps) {
  return (
    <li className="cart-line">
      <div>
        <strong>{item.name}</strong>
        <span>{item.quantity} x {item.unit}</span>
      </div>
      <strong>{formatPrice(item.price * item.quantity)}</strong>
    </li>
  );
}
