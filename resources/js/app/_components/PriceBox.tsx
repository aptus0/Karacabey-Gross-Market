import { formatPrice } from "@/lib/catalog";

type PriceBoxProps = {
  price: number;
  oldPrice?: number;
  unit: string;
};

export function PriceBox({ price, oldPrice, unit }: PriceBoxProps) {
  return (
    <div className="price-box">
      <strong>{formatPrice(price)}</strong>
      <span>/ {unit}</span>
      {oldPrice ? <s>{formatPrice(oldPrice)}</s> : null}
    </div>
  );
}
