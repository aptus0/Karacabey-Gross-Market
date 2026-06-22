import Link from "next/link";
import type { KgmProduct } from "@/lib/catalog";
import { Package } from "lucide-react";
import { AddToCartButton } from "@/app/_components/AddToCartButton";
import { FavoriteButton } from "@/app/_components/FavoriteButton";
import { formatPrice } from "@/lib/catalog";
import { cn } from "@/lib/utils";
import type { CartProduct } from "@/lib/cart";
import { productImageUrl } from "@/lib/media";

type ProductCardProps = {
  product: KgmProduct;
  priority?: boolean;
};

export function ProductCard({ product, priority = false }: ProductCardProps) {
  const imageUrl = productImageUrl(product.image);
  const isFallbackImage = !imageUrl;
  const outOfStock = product.stock === 0;
  const hasDiscount = Boolean(product.oldPrice && product.oldPrice > product.price);
  const discountPercent = hasDiscount && product.oldPrice
    ? Math.max(1, Math.round(((product.oldPrice - product.price) / product.oldPrice) * 100))
    : null;

  return (
    <article className="product-card product-card--phase10">
      <div className="product-card__media">
        <Link
          className={cn("product-card__img-link", isFallbackImage && "product-card__img-link--fallback")}
          href={`/product/${product.slug}`}
          tabIndex={-1}
        >
          {imageUrl ? (
            // eslint-disable-next-line @next/next/no-img-element
            <img
              src={imageUrl}
              alt={product.name}
              loading={priority ? "eager" : "lazy"}
            />
          ) : (
            <span className="product-card__placeholder"><Package size={28} /></span>
          )}
        </Link>

        {hasDiscount ? (
          <span className="product-card__badge product-card__badge--sale">%{discountPercent}</span>
        ) : outOfStock ? (
          <span className="product-card__badge product-card__badge--out">Tükendi</span>
        ) : product.badge ? (
          <span className="product-card__badge">{product.badge}</span>
        ) : null}

        <div className="product-card__fav">
          <FavoriteButton productSlug={product.slug} />
        </div>
      </div>

      <div className="product-card__body">
        {product.brand ? <p className="product-card__brand">{product.brand}</p> : null}
        <Link className="product-card__name-link" href={`/product/${product.slug}`}>
          <h3 className="product-card__name">{product.name}</h3>
        </Link>
        <div className="product-card__unit-row">
          <span>{product.unit}</span>
          {outOfStock ? <small>Stokta yok</small> : null}
        </div>
        <div className="product-card__price product-card__price--phase10">
          <strong>{formatPrice(product.price)}</strong>
          {product.oldPrice ? <s>{formatPrice(product.oldPrice)}</s> : null}
        </div>
        <AddToCartButton
          productSlug={product.slug}
          productId={typeof product.id === "number" ? product.id : undefined}
          optimisticProduct={toCartProduct(product)}
          compact
          openSheetOnAdd={false}
          disabled={outOfStock}
          label={outOfStock ? "Stokta Yok" : "Sepete Ekle"}
        />
      </div>
    </article>
  );
}

function toCartProduct(product: KgmProduct): CartProduct | undefined {
  if (typeof product.id !== "number") return undefined;

  return {
    id: product.id,
    name: product.name,
    slug: product.slug,
    brand: product.brand,
    price_cents: Math.round(product.price * 100),
    price: product.price.toFixed(2),
    stock_quantity: product.stock,
    unit_name: product.unit,
    image_url: productImageUrl(product.image),
  };
}
