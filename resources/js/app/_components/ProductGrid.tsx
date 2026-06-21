import type { KgmProduct } from "@/lib/catalog";
import { ProductCard } from "@/app/_components/ProductCard";

type ProductGridProps = {
  products: KgmProduct[];
};

export function ProductGrid({ products }: ProductGridProps) {
  return (
    <div className="product-grid">
      {products.map((product, index) => (
        <ProductCard key={`${product.id ?? product.slug}-${product.slug}`} product={product} priority={index < 6} />
      ))}
    </div>
  );
}
